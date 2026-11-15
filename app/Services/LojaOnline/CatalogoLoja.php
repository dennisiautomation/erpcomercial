<?php

namespace App\Services\LojaOnline;

use App\Models\Categoria;
use App\Models\ConfiguracaoLoja;
use App\Models\LojaOnline;
use App\Models\Produto;
use App\Scopes\EmpresaScope;
use App\Services\PromocaoService;
use App\Services\SaldoEstoque;
use App\Services\TabelaPrecoService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * O que a loja online mostra e por quanto (14/11/2026).
 *
 * Onde cada coisa vive: o PRODUTO, o PREÇO e o SALDO são os do ERP — aqui só se
 * lê. Entra na vitrine o produto ativo com `loja_online` marcado; o preço é o à
 * vista da loja de origem (tabela de preço + promoção vigente, a mesma conta do
 * PDV e do agente); o saldo é o do estoque escolhido na configuração da loja
 * online (ou o estoque de venda da loja de origem).
 *
 * Toda consulta filtra empresa_id na mão: a vitrine é pública, sem sessão do ERP.
 */
class CatalogoLoja
{
    private ?ConfiguracaoLoja $config = null;

    private ?int $estoqueId = null;

    private bool $estoqueResolvido = false;

    public function __construct(private readonly LojaOnline $loja)
    {
    }

    public function base(): Builder
    {
        return Produto::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $this->loja->empresa_id)
            ->where('status', 'ativo')
            ->where('loja_online', true);
    }

    /**
     * Base das LISTAS (vitrine, busca, fileiras): um cartão por grade — só o
     * produto principal representa as variações. Carrinho e página do produto
     * usam base(), que enxerga cada variação.
     */
    public function vitrine(): Builder
    {
        return $this->base()->where(fn (Builder $w) => $w->whereNull('grade_id')->orWhereColumn('grade_id', 'id'));
    }

    /** Estoque de onde a loja online vende (o escolhido, ou o de venda da loja de origem). */
    public function estoqueId(): ?int
    {
        if (! $this->estoqueResolvido) {
            $this->estoqueResolvido = true;
            $this->estoqueId = $this->loja->estoque_id
                ?: ($this->loja->unidade_id ? SaldoEstoque::estoqueDeVendaId((int) $this->loja->unidade_id) : null);
        }

        return $this->estoqueId;
    }

    /** @return array<int, float> produto_id => saldo no estoque da loja online */
    public function saldos(array $produtoIds): array
    {
        $estoque = $this->estoqueId();
        if (! $estoque || ! $this->loja->unidade_id || $produtoIds === []) {
            return [];
        }

        $porEstoque = SaldoEstoque::porProdutoEEstoqueDaUnidade((int) $this->loja->unidade_id, array_values($produtoIds));
        // Reserva (15/11/2026): o que os pedidos online já prometeram sai do saldo da vitrine.
        $reservado = ReservaLoja::porProduto($this->loja, array_values($produtoIds));
        $out = [];
        foreach ($produtoIds as $id) {
            $out[$id] = max(0.0, (float) ($porEstoque[$id][$estoque] ?? 0) - (float) ($reservado[$id] ?? 0));
        }

        return $out;
    }

    public function saldo(int $produtoId): float
    {
        return $this->saldos([$produtoId])[$produtoId] ?? 0.0;
    }

    private function config(): ConfiguracaoLoja
    {
        return $this->config ??= ConfiguracaoLoja::daUnidade((int) $this->loja->empresa_id, (int) $this->loja->unidade_id);
    }

    /**
     * Preço de venda na loja online: o à vista da loja de origem.
     *
     * @return array{preco: float, normal: float, cartao: float, promocao: bool}
     */
    public function precos(Produto $produto): array
    {
        $tabela = app(TabelaPrecoService::class);
        $atual = $tabela->precosDoProduto($produto, $this->config());
        $semPromo = $tabela->precosSemPromocao($produto, $this->config());

        $preco = round((float) $atual['dinheiro_pix'], 2);
        $normal = round((float) $semPromo['dinheiro_pix'], 2);

        return [
            'preco' => $preco,
            'normal' => $normal,
            'cartao' => round((float) $atual['credito'], 2),
            'promocao' => $preco < $normal - 0.004,
        ];
    }

    /**
     * Produtos prontos para a tela (cartão de vitrine).
     *
     * @param  Collection<int, Produto>  $produtos
     * @return Collection<int, array<string, mixed>>
     */
    public function paraVitrine(Collection $produtos, bool $agrupar = false): Collection
    {
        // Grade: no cartão da lista, o principal responde pelas variações (disponível se QUALQUER uma tiver saldo).
        $irmaos = collect();
        if ($agrupar && ($gradeIds = $produtos->pluck('grade_id')->filter()->unique()->values()->all())) {
            $irmaos = $this->base()->whereIn('grade_id', $gradeIds)->get()->groupBy('grade_id');
        }
        $saldos = $this->saldos($produtos->pluck('id')->merge($irmaos->flatten(1)->pluck('id'))->unique()->values()->all());
        $notas = $this->loja->opcao('avaliacoes') ? $this->notas($produtos->map(fn (Produto $p) => $p->grade_id ?: $p->id)->unique()->all()) : [];
        $categorias = Categoria::withoutGlobalScopes()
            ->whereIn('id', $produtos->pluck('categoria_id')->filter()->unique())
            ->pluck('nome', 'id');
        $semEstoque = (bool) $this->loja->opcao('vender_sem_estoque');
        // 2ª foto (aparece ao passar o mouse): a primeira foto adicional de cada produto
        $segundas = \App\Models\ProdutoFoto::whereIn('produto_id', $produtos->pluck('id'))->orderBy('ordem')->orderBy('id')
            ->get(['produto_id', 'caminho'])->unique('produto_id')->pluck('caminho', 'produto_id');
        $diasNovo = (int) $this->loja->opcao('selo_novo_dias');
        $freteGratisAcima = (float) $this->loja->opcao('frete_gratis_acima');

        return $produtos->map(function (Produto $p) use ($saldos, $categorias, $semEstoque, $segundas, $diasNovo, $freteGratisAcima, $irmaos, $notas) {
            $precos = $this->precos($p);
            $saldo = $saldos[$p->id] ?? 0.0;
            $grupo = $p->grade_id ? ($irmaos[$p->grade_id] ?? collect()) : collect();
            $nome = $p->descricao;
            $aPartir = false;
            if ($grupo->count() > 1) {
                $nome = GradeLoja::nomeComum($grupo->firstWhere('id', $p->grade_id) ?? $p);
                $saldo = (float) $grupo->sum(fn (Produto $v) => $saldos[$v->id] ?? 0.0);
                // preço do cartão: o menor entre as variações que dá para comprar
                $compraveis = $grupo->filter(fn (Produto $v) => $semEstoque || ($saldos[$v->id] ?? 0) >= 1);
                $todos = ($compraveis->isNotEmpty() ? $compraveis : $grupo)->map(fn (Produto $v) => $this->precos($v));
                $menor = $todos->sortBy('preco')->first();
                $aPartir = $todos->pluck('preco')->unique()->count() > 1;
                $precos = $menor ?: $precos;
            }
            $nota = $notas[$p->grade_id ?: $p->id] ?? null;

            return [
                'id' => $p->id,
                'nome' => $nome,
                'grade' => $grupo->count() > 1,
                'a_partir' => $aPartir,
                'nota' => $nota['media'] ?? null,
                'avaliacoes' => $nota['total'] ?? 0,
                'caminho' => 'p/' . $p->id . '-' . (Str::slug($p->descricao) ?: 'produto'),
                'foto' => LojaContexto::arquivo($p->foto),
                'foto2' => LojaContexto::arquivo($segundas[$p->id] ?? null),
                'novo' => $diasNovo > 0 && $p->created_at && $p->created_at->gt(now()->subDays($diasNovo)),
                'frete_gratis' => $freteGratisAcima > 0 && $precos['preco'] >= $freteGratisAcima && (bool) $this->loja->opcao('entrega'),
                'parcelas' => $this->parcelas($precos['preco']),
                'codigo' => $p->codigo_interno,
                'categoria' => $categorias[$p->categoria_id] ?? null,
                'destaque' => (bool) $p->loja_online_destaque,
                'saldo' => $saldo,
                'disponivel' => $semEstoque || $saldo >= 1,
            ] + $precos;
        })->values();
    }

    /**
     * Média e total das avaliações APROVADAS (a grade inteira divide as mesmas:
     * a avaliação é gravada no produto principal).
     *
     * @return array<int, array{media: float, total: int}>
     */
    public function notas(array $produtoIds): array
    {
        if ($produtoIds === []) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('loja_avaliacoes')
            ->where('empresa_id', $this->loja->empresa_id)->where('status', 'aprovada')->whereIn('produto_id', $produtoIds)
            ->groupBy('produto_id')->selectRaw('produto_id, AVG(nota) as media, COUNT(*) as total')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->produto_id => ['media' => round((float) $r->media, 1), 'total' => (int) $r->total]])->all();
    }

    /**
     * Seletores da página do produto (Tamanho, Cor…): cada opção leva para a
     * variação que mais se parece com a que está na tela.
     *
     * @return array<int, array{nome: string, opcoes: array<int, array{valor: string, atual: bool, url: string, disponivel: bool}>}>
     */
    public function grade(Produto $produto): array
    {
        if (! $produto->grade_id) {
            return [];
        }
        $variacoes = $this->base()->where('grade_id', $produto->grade_id)->orderBy('id')->get();
        if ($variacoes->count() < 2) {
            return [];
        }
        $saldos = $this->saldos($variacoes->pluck('id')->all());
        $semEstoque = (bool) $this->loja->opcao('vender_sem_estoque');
        $atual = GradeLoja::opcoes($produto);
        $principal = $variacoes->firstWhere('id', $produto->grade_id) ?? $variacoes->first();
        $nomes = GradeLoja::nomes($principal);

        $out = [];
        foreach ($nomes as $nome) {
            $valores = $variacoes->map(fn (Produto $v) => GradeLoja::opcoes($v)[$nome] ?? null)->filter()->unique()->values();
            $opcoes = [];
            foreach ($valores as $valor) {
                $alvo = array_merge($atual, [$nome => $valor]);
                // a variação com mais opções iguais às pedidas (e, no empate, a que tem saldo)
                $melhor = $variacoes->filter(fn (Produto $v) => (GradeLoja::opcoes($v)[$nome] ?? null) === $valor)
                    ->sortByDesc(fn (Produto $v) => count(array_intersect_assoc(GradeLoja::opcoes($v), $alvo)) * 10 + (($saldos[$v->id] ?? 0) >= 1 ? 1 : 0))
                    ->first();
                $exata = $melhor && GradeLoja::opcoes($melhor) == $alvo;
                $opcoes[] = [
                    'valor' => (string) $valor,
                    'atual' => ($atual[$nome] ?? null) === $valor,
                    'url' => 'p/' . $melhor->id . '-' . (Str::slug($melhor->descricao) ?: 'produto'),
                    'disponivel' => $exata ? ($semEstoque || ($saldos[$melhor->id] ?? 0) >= 1)
                        : $variacoes->contains(fn (Produto $v) => (GradeLoja::opcoes($v)[$nome] ?? null) === $valor && ($semEstoque || ($saldos[$v->id] ?? 0) >= 1)),
                ];
            }
            $out[] = ['nome' => $nome, 'opcoes' => $opcoes];
        }

        return $out;
    }

    /**
     * Parcelamento MOSTRADO no preço (o lojista liga em Configurações): até N vezes,
     * respeitando a parcela mínima. É informativo — quem parcela é o link de cartão.
     * A conta é sobre o preço que o pedido cobra de verdade.
     *
     * @return ?array{vezes: int, valor: float, sem_juros: bool}
     */
    public function parcelas(float $preco): ?array
    {
        $max = (int) $this->loja->opcao('parcelas_max');
        $minima = max(1.0, (float) $this->loja->opcao('parcela_minima'));
        if ($max < 2 || ! PagamentoLoja::meios($this->loja)['cartao']) {
            return null;
        }
        $vezes = (int) min($max, floor($preco / $minima));

        return $vezes >= 2 ? ['vezes' => $vezes, 'valor' => round($preco / $vezes, 2), 'sem_juros' => (bool) $this->loja->opcao('parcelas_sem_juros')] : null;
    }

    /** Sugestões da busca do cabeçalho (enquanto digita). */
    public function sugestoes(string $termo, int $quantas = 6): Collection
    {
        $termo = trim($termo);
        if (mb_strlen($termo) < 2) {
            return collect();
        }

        return collect($this->listar(['q' => $termo], $quantas)->items());
    }

    /** Menor e maior preço de cadastro entre os produtos da loja (para o filtro de preço). */
    public function faixaDePreco(): array
    {
        $r = $this->vitrine()->selectRaw('MIN(preco_venda) as menor, MAX(preco_venda) as maior')->first();

        return ['menor' => (float) floor((float) ($r->menor ?? 0)), 'maior' => (float) ceil((float) ($r->maior ?? 0))];
    }

    /** Fileira de produtos de um bloco "vitrine" da página inicial. */
    public function fileira(string $origem, ?int $categoriaId, int $quantidade): Collection
    {
        $quantidade = max(1, min(24, $quantidade));
        $q = $this->vitrine();

        match ($origem) {
            'destaques' => $q->where('loja_online_destaque', true)->orderByDesc('updated_at'),
            'promocoes' => $q->whereIn('id', $this->idsEmPromocao())->orderByDesc('id'),
            'categoria' => $q->whereIn('categoria_id', $this->categoriaComFilhas($categoriaId))->orderByDesc('id'),
            'todos' => $q->orderBy('descricao'),
            default => $q->orderByDesc('id'),
        };

        return $this->paraVitrine($q->limit($quantidade)->get(), true);
    }

    /** @return array<int, int> */
    public function idsEmPromocao(): array
    {
        if (! $this->loja->unidade_id) {
            return [];
        }

        return app(PromocaoService::class)
            ->itensDaLoja((int) $this->loja->empresa_id, (int) $this->loja->unidade_id)
            ->keys()->all();
    }

    /** @return array<int, int> */
    public function categoriaComFilhas(?int $categoriaId): array
    {
        if (! $categoriaId) {
            return [0];
        }
        $ids = [$categoriaId];
        $nivel = [$categoriaId];
        for ($i = 0; $i < 4 && $nivel !== []; $i++) {
            $nivel = Categoria::withoutGlobalScopes()
                ->where('empresa_id', $this->loja->empresa_id)
                ->whereIn('parent_id', $nivel)
                ->pluck('id')->all();
            $ids = array_merge($ids, $nivel);
        }

        return $ids;
    }

    /**
     * Categorias que têm produto na loja, com a contagem e uma foto de exemplo.
     *
     * @return Collection<int, array{id: int, nome: string, total: int, foto: ?string}>
     */
    public function categorias(): Collection
    {
        $contagem = $this->vitrine()->whereNotNull('categoria_id')
            ->selectRaw('categoria_id, COUNT(*) as total, MAX(foto) as foto')
            ->groupBy('categoria_id')
            ->get()->keyBy('categoria_id');

        if ($contagem->isEmpty()) {
            return collect();
        }

        return Categoria::withoutGlobalScopes()
            ->where('empresa_id', $this->loja->empresa_id)
            ->whereIn('id', $contagem->keys())
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (Categoria $c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'total' => (int) $contagem[$c->id]->total,
                'foto' => LojaContexto::arquivo($contagem[$c->id]->foto),
            ]);
    }

    /**
     * Lista de produtos com busca, categoria e ordem.
     *
     * @param  array{q?: ?string, categoria?: mixed, ordem?: ?string, promocao?: mixed}  $filtros
     */
    public function listar(array $filtros, int $porPagina = 24): LengthAwarePaginator
    {
        $q = $this->vitrine();

        $termo = trim((string) ($filtros['q'] ?? ''));
        if ($termo !== '') {
            $palavras = array_slice(array_filter(preg_split('/\s+/', mb_substr($termo, 0, 80))), 0, 6);
            $q->where(function (Builder $w) use ($termo, $palavras) {
                $w->where(function (Builder $nome) use ($palavras) {
                    foreach ($palavras as $p) {
                        $nome->where('descricao', 'like', '%' . addcslashes($p, '%_\\') . '%');
                    }
                })->orWhere('codigo_interno', $termo)->orWhere('codigo_barras', $termo)->orWhere('sku', $termo);
            });
        }

        if ($categoria = (int) ($filtros['categoria'] ?? 0)) {
            $q->whereIn('categoria_id', $this->categoriaComFilhas($categoria));
        }

        if (! empty($filtros['promocao'])) {
            $q->whereIn('id', $this->idsEmPromocao() ?: [0]);
        }
        if (is_numeric($filtros['preco_min'] ?? null)) {
            $q->where('preco_venda', '>=', (float) $filtros['preco_min']);
        }
        if (is_numeric($filtros['preco_max'] ?? null) && (float) $filtros['preco_max'] > 0) {
            $q->where('preco_venda', '<=', (float) $filtros['preco_max']);
        }
        if (! empty($filtros['destaque'])) {
            $q->where('loja_online_destaque', true);
        }

        match ($filtros['ordem'] ?? '') {
            'menor' => $q->orderBy('preco_venda')->orderBy('descricao'),
            'maior' => $q->orderByDesc('preco_venda')->orderBy('descricao'),
            'nome' => $q->orderBy('descricao'),
            'novos' => $q->orderByDesc('id'),
            default => $q->orderByDesc('loja_online_destaque')->orderByDesc('id'),
        };

        $pagina = $q->paginate($porPagina)->withQueryString();
        $pagina->setCollection($this->paraVitrine($pagina->getCollection(), true));

        return $pagina;
    }

    public function produto(int $id): ?Produto
    {
        return $this->base()->with('fotos')->whereKey($id)->first();
    }

    /** Fotos do produto na ordem da galeria: capa primeiro. */
    public function galeria(Produto $produto): array
    {
        return collect([$produto->foto])
            ->merge($produto->fotos->pluck('caminho'))
            ->filter()->unique()
            ->map(fn ($c) => LojaContexto::arquivo($c))
            ->values()->all();
    }

    /** Outros produtos da mesma categoria (ou os mais novos) para a página do produto. */
    public function relacionados(Produto $produto, int $quantidade = 4): Collection
    {
        $q = $this->vitrine()->where('id', '!=', $produto->grade_id ?: $produto->id);
        if ($produto->categoria_id) {
            $q->orderByRaw('categoria_id = ? DESC', [$produto->categoria_id]);
        }

        return $this->paraVitrine($q->orderByDesc('id')->limit($quantidade)->get(), true);
    }

    public function totalNaLoja(): int
    {
        return $this->vitrine()->count();
    }
}
