<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Services\LojaOnline\CarrinhoLoja;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\LojaContexto;
use App\Services\LojaOnline\PagamentoLoja;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Vitrine pública da loja online (14/11/2026): página inicial em blocos, lista
 * de produtos, página do produto e páginas de apoio. Só LEITURA do ERP.
 */
class VitrineController extends Controller
{
    public function __construct(private readonly LojaContexto $ctx)
    {
    }

    private function comum(CatalogoLoja $catalogo): array
    {
        return [
            'categoriasMenu' => $catalogo->categorias(),
            'carrinhoTotal' => (new CarrinhoLoja($this->ctx->loja, $catalogo))->quantidadeTotal(),
            'temOfertas' => $catalogo->idsEmPromocao() !== [],
        ];
    }

    public function home()
    {
        $loja = $this->ctx->loja;
        $catalogo = new CatalogoLoja($loja);
        $blocos = $this->ctx->rascunho ? $loja->rascunhoEfetivo()['blocos'] : $loja->blocosEfetivos();
        $categorias = $catalogo->categorias();

        // Cada bloco recebe os dados de que precisa — a view só desenha.
        $montados = [];
        $mosaico = null;
        $comFoto = null;
        $proximoProduto = 0;
        foreach ($blocos as $bloco) {
            if (($bloco['tipo'] ?? '') === 'vitrine') {
                $bloco['produtos'] = $catalogo->fileira((string) ($bloco['origem'] ?? 'novidades'),
                    isset($bloco['categoria_id']) ? (int) $bloco['categoria_id'] : null, (int) ($bloco['quantidade'] ?? 8));
                if ($bloco['produtos']->isEmpty() && ! $this->ctx->rascunho) {
                    continue; // fileira vazia não aparece para o público
                }
            } elseif (($bloco['tipo'] ?? '') === 'categorias') {
                $bloco['categorias'] = $categorias;
                if ($categorias->isEmpty() && ! $this->ctx->rascunho) {
                    continue;
                }
            } elseif (in_array($bloco['tipo'] ?? '', ['carrossel', 'banners'], true)) {
                // Banner sem imagem ganha a foto de um produto de verdade (um diferente por banner)
                $comFoto ??= $catalogo->paraVitrine(
                    $catalogo->vitrine()->whereNotNull('foto')->orderByDesc('loja_online_destaque')->orderByDesc('id')->limit(12)->get(), true
                )->values();
                $bloco['produtosSlide'] = [];
                foreach (array_values($bloco['lista'] ?? []) as $n => $item) {
                    $bloco['produtosSlide'][$n] = blank($item['imagem'] ?? '') && $comFoto->isNotEmpty() ? $comFoto[($proximoProduto++) % $comFoto->count()] : null;
                }
            } elseif (($bloco['tipo'] ?? '') === 'banner' && blank($bloco['imagem'] ?? '')) {
                // Banner sem imagem mostra produtos de verdade no lugar de enfeite
                $mosaico ??= $catalogo->paraVitrine(
                    $catalogo->vitrine()->whereNotNull('foto')->orderByDesc('loja_online_destaque')->orderByDesc('id')->limit(3)->get(), true
                );
                $bloco['mosaico'] = $mosaico;
            }
            $montados[] = $bloco;
        }

        return view('loja.home', $this->comum($catalogo) + [
            'blocos' => $montados,
            'totalProdutos' => $catalogo->totalNaLoja(),
            'titulo' => $loja->nome,
        ]);
    }

    public function produtos(Request $request)
    {
        $catalogo = new CatalogoLoja($this->ctx->loja);
        $filtros = [
            'q' => $request->query('q'),
            'categoria' => $request->query('categoria'),
            'ordem' => $request->query('ordem'),
            'promocao' => $request->boolean('promocao'),
            'preco_min' => $request->query('preco_min'),
            'preco_max' => $request->query('preco_max'),
            'destaque' => $request->boolean('destaque'),
        ];

        $categoriaAtual = null;
        if ($id = (int) $filtros['categoria']) {
            $categoriaAtual = Categoria::withoutGlobalScopes()->where('empresa_id', $this->ctx->loja->empresa_id)->whereKey($id)->first(['id', 'nome']);
        }

        $produtos = $catalogo->listar($filtros)->withPath($this->ctx->caminho('produtos'));
        if (filled($filtros['q']) && (int) $request->query('page', 1) === 1) {
            \App\Services\LojaOnline\MetricasLoja::buscou($this->ctx->loja, (string) $filtros['q'], $produtos->total() > 0);
        }

        return view('loja.produtos', $this->comum($catalogo) + [
            'produtos' => $produtos,
            'filtros' => $filtros,
            'categoriaAtual' => $categoriaAtual,
            'faixa' => $catalogo->faixaDePreco(),
            'titulo' => ($categoriaAtual->nome ?? (filled($filtros['q']) ? 'Busca: ' . $filtros['q'] : 'Produtos')) . ' — ' . $this->ctx->loja->nome,
        ]);
    }

    public function produto(string $produto)
    {
        $loja = $this->ctx->loja;
        $catalogo = new CatalogoLoja($loja);
        $model = $catalogo->produto((int) $produto);
        abort_unless($model, 404);

        $dados = $catalogo->paraVitrine(collect([$model]))->first();

        // Endereço canônico: id + nome do produto
        if ($produto !== substr($dados['caminho'], 2)) {
            return redirect($this->ctx->url($dados['caminho']), 301);
        }

        \App\Services\LojaOnline\MetricasLoja::produtoVisto($loja, (int) ($model->grade_id ?: $model->id));
        $alvoAvaliacao = (int) ($model->grade_id ?: $model->id);

        return view('loja.produto', $this->comum($catalogo) + [
            'grade' => $catalogo->grade($model),
            'avaliacoes' => $loja->opcao('avaliacoes') ? \Illuminate\Support\Facades\DB::table('loja_avaliacoes')->where('empresa_id', $loja->empresa_id)
                ->where('produto_id', $alvoAvaliacao)->where('status', 'aprovada')->orderByDesc('id')->limit(30)->get() : collect(),
            'p' => $dados,
            'model' => $model,
            'galeria' => $catalogo->galeria($model),
            'relacionados' => $catalogo->relacionados($model),
            'meios' => PagamentoLoja::meios($loja),
            'titulo' => $model->descricao . ' — ' . $loja->nome,
            'descricaoPagina' => Str::limit(trim((string) $model->descricao_detalhada), 155),
            'imagemPagina' => $dados['foto'],
        ]);
    }

    public function informacoes()
    {
        $catalogo = new CatalogoLoja($this->ctx->loja);

        return view('loja.informacoes', $this->comum($catalogo) + [
            'unidade' => $this->ctx->loja->unidade,
            'titulo' => 'Informações — ' . $this->ctx->loja->nome,
        ]);
    }

    /** Sugestões da busca do cabeçalho (JSON). */
    public function buscar(Request $request)
    {
        $catalogo = new CatalogoLoja($this->ctx->loja);

        return response()->json(['produtos' => $catalogo->sugestoes((string) $request->query('q'))->map(fn ($p) => [
            'nome' => $p['nome'], 'url' => $this->ctx->url($p['caminho']), 'foto' => $p['foto'], 'preco' => $p['preco'], 'normal' => $p['promocao'] ? $p['normal'] : null,
        ])->values()]);
    }

    /** Cartões dos produtos vistos recentemente (os ids vêm do navegador do visitante). */
    public function vistos(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))->map(fn ($i) => (int) $i)->filter()->unique()->take(8);
        $catalogo = new CatalogoLoja($this->ctx->loja);
        $produtos = $catalogo->paraVitrine($catalogo->base()->whereIn('id', $ids)->get())->sortBy(fn ($p) => $ids->search($p['id']))->values();

        return view('loja.partials.fileira', ['produtos' => $produtos]);
    }

    public function ofertas()
    {
        return redirect($this->ctx->url('produtos?promocao=1'));
    }

    /** Páginas de política: privacidade, trocas e devoluções, entrega, termos. */
    public function politica(string $tipo)
    {
        abort_unless(isset(\App\Services\LojaOnline\PoliticasLoja::TIPOS[$tipo]), 404);
        $catalogo = new CatalogoLoja($this->ctx->loja);

        return view('loja.politica', $this->comum($catalogo) + [
            'tipo' => $tipo,
            'tituloPolitica' => \App\Services\LojaOnline\PoliticasLoja::titulo($tipo),
            'texto' => \App\Services\LojaOnline\PoliticasLoja::texto($this->ctx->loja, $tipo),
            'titulo' => \App\Services\LojaOnline\PoliticasLoja::titulo($tipo) . ' — ' . $this->ctx->loja->nome,
        ]);
    }

    /**
     * Cadastro para receber novidades (popup ou bloco "Novidades"). Exige o aceite
     * marcado; guarda a data dele. Se o popup tem cupom, devolve o código.
     */
    public function contato(Request $request)
    {
        $loja = $this->ctx->loja;
        $dados = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'nome' => ['nullable', 'string', 'max:120'],
            'aceite' => ['accepted'],
            'origem' => ['nullable', 'in:popup,newsletter'],
        ], ['email.required' => 'Informe o seu e-mail.', 'email.email' => 'Confira o e-mail.', 'aceite.accepted' => 'Marque que você aceita receber as novidades.']);

        \App\Models\LojaContato::updateOrCreate(
            ['empresa_id' => $loja->empresa_id, 'email' => mb_strtolower($dados['email'])],
            ['nome' => $dados['nome'] ?? null, 'origem' => $dados['origem'] ?? 'newsletter', 'aceitou_em' => now()]
        );

        $cupom = ($dados['origem'] ?? '') === 'popup' ? \App\Services\LojaOnline\CupomLoja::normalizar($loja->opcao('popup_cupom')) : '';

        return response()->json(['ok' => true, 'mensagem' => 'Pronto! Você vai receber as novidades da loja.', 'cupom' => $cupom !== '' ? $cupom : null]);
    }

    /** Mapa do site para os buscadores (só loja no ar). */
    public function sitemap()
    {
        abort_unless($this->ctx->loja->noAr(), 404);
        $catalogo = new CatalogoLoja($this->ctx->loja);
        $urls = [$this->ctx->urlAbsoluta(), $this->ctx->urlAbsoluta('produtos')];
        foreach ($catalogo->base()->orderByDesc('id')->limit(2000)->get(['id', 'descricao']) as $p) {
            $urls[] = $this->ctx->urlAbsoluta('p/' . $p->id . '-' . (Str::slug($p->descricao) ?: 'produto'));
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>' . e($u) . '</loc></url>';
        }

        return response($xml . '</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
