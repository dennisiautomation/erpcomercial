<?php

namespace App\Services\Ajuda;

use App\Http\Middleware\CheckPermission;
use App\Models\Caixa;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Services\EstoqueMultiUnidadeService;
use Illuminate\Support\Str;

/**
 * As ferramentas que a IA do botão "Ajuda" pode chamar.
 *
 * REGRA DE OURO: só LEITURA e só da empresa de quem está logado. Toda consulta
 * filtra `empresa_id = $user->empresa_id` na mão (além dos escopos globais) e
 * respeita a permissão do perfil — vendedor não lê nota fiscal se o perfil dele
 * não lê na tela. Usuário sem empresa (admin da IA365 fora de "acessar como")
 * não consulta dado nenhum: só a base de conhecimento.
 *
 * O que vai para a IA é o mínimo para diagnosticar: nada de token, CSC, senha,
 * CPF/CNPJ completo de cliente, telefone ou e-mail de cliente.
 */
class FerramentasAjuda
{
    public function __construct(
        private User $user,
        private BaseConhecimento $base,
    ) {}

    /** Definições no formato "tools" da API OpenAI/DeepSeek. */
    public function definicoes(): array
    {
        $f = [];
        $f[] = $this->def('buscar_ajuda', 'Busca no manual do ERP (como fazer, onde fica, o que significa um erro ou rejeição). Use SEMPRE antes de explicar um procedimento ou um erro. Pode chamar mais de uma vez com palavras diferentes.', [
            'pergunta' => ['type' => 'string', 'description' => 'o assunto em poucas palavras, ex.: "cadastrar promoção", "rejeição 778 NCM"'],
        ], ['pergunta']);

        if ($this->user->empresa_id) {
            if ($this->pode('notas_fiscais')) {
                $f[] = $this->def('consultar_notas', 'Lista notas fiscais da empresa do usuário (as mais recentes primeiro). Use para achar a nota de que a pessoa fala.', [
                    'numero' => ['type' => 'integer', 'description' => 'número da nota, se a pessoa disse'],
                    'tipo' => ['type' => 'string', 'enum' => ['nfe', 'nfce', 'nfse'], 'description' => 'nfe = nota grande/DANFE, nfce = cupom do PDV, nfse = serviço'],
                    'situacao' => ['type' => 'string', 'enum' => ['rejeitada', 'pendente', 'autorizada', 'cancelada', 'inutilizada', 'contingencia']],
                    'dias' => ['type' => 'integer', 'description' => 'quantos dias para trás (padrão 30, máximo 365)'],
                ]);
                $f[] = $this->def('detalhar_nota', 'Abre uma nota: situação, mensagem da SEFAZ/Focus, itens com os dados fiscais do produto e o que falta no cadastro do cliente. Use para explicar o erro de uma nota.', [
                    'nota_id' => ['type' => 'integer', 'description' => 'id interno que veio de consultar_notas'],
                ], ['nota_id']);
            }
            if ($this->pode('configuracoes_fiscais') || $this->pode('notas_fiscais')) {
                $f[] = $this->def('situacao_fiscal', 'Mostra a configuração fiscal de cada loja da empresa: emissão ligada, quais notas emite, séries, ambiente, validade do certificado digital, CSC preenchido ou não.', []);
            }
            if ($this->pode('produtos')) {
                $f[] = $this->def('consultar_produto', 'Procura produtos da empresa por nome, código interno ou código de barras: preço, situação, dados fiscais e saldo por loja.', [
                    'busca' => ['type' => 'string'],
                ], ['busca']);
            }
            if ($this->pode('vendas')) {
                $f[] = $this->def('consultar_venda', 'Abre uma venda da empresa pelo número (ou id): situação, total, pagamento, itens, nota ligada.', [
                    'numero' => ['type' => 'string', 'description' => 'número da venda como aparece na tela'],
                ], ['numero']);
            }
            $f[] = $this->def('caixas_abertos', 'Mostra os caixas abertos agora nas lojas que o usuário enxerga e quem abriu. Útil para "PDV diz caixa fechado", "não consigo faturar".', []);
        }

        $f[] = $this->def('abrir_chamado', 'Abre um chamado para a equipe de suporte da IA365 (app.ia365). Use SÓ quando for defeito do sistema, algo que o manual não resolve, ou quando a pessoa pedir. Antes, confirme com a pessoa e junte o que você já descobriu.', [
            'assunto' => ['type' => 'string', 'description' => 'resumo em até 10 palavras'],
            'descricao' => ['type' => 'string', 'description' => 'relato completo: tela, o que a pessoa fez, o que apareceu (mensagem exata), o que você já conferiu (ex.: nota nº, produto, mensagem da SEFAZ) e o que ela esperava'],
            'prioridade' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'description' => 'urgent = loja parada sem vender/emitir; high = atrapalha muito; medium = padrão'],
        ], ['assunto', 'descricao']);

        return $f;
    }

    /** Executa uma ferramenta (menos abrir_chamado, que o AgenteAjuda trata). Devolve array serializável. */
    public function executar(string $nome, array $args): array
    {
        try {
            return match ($nome) {
                'buscar_ajuda' => $this->buscarAjuda((string) ($args['pergunta'] ?? '')),
                'consultar_notas' => $this->exige('notas_fiscais', fn () => $this->consultarNotas($args)),
                'detalhar_nota' => $this->exige('notas_fiscais', fn () => $this->detalharNota((int) ($args['nota_id'] ?? 0))),
                'situacao_fiscal' => ($this->pode('configuracoes_fiscais') || $this->pode('notas_fiscais')) ? $this->situacaoFiscal() : $this->negado(),
                'consultar_produto' => $this->exige('produtos', fn () => $this->consultarProduto((string) ($args['busca'] ?? ''))),
                'consultar_venda' => $this->exige('vendas', fn () => $this->consultarVenda((string) ($args['numero'] ?? ''))),
                'caixas_abertos' => $this->caixasAbertos(),
                default => ['erro' => 'Ferramenta desconhecida.'],
            };
        } catch (\Throwable $e) {
            report($e);

            return ['erro' => 'Não consegui consultar agora (erro interno). Siga sem essa informação ou ofereça abrir chamado.'];
        }
    }

    // ------------------------------------------------------------------

    private function buscarAjuda(string $pergunta): array
    {
        $achados = $this->base->buscar($pergunta, 5);
        if (! $achados) {
            return ['resultado' => 'Nada no manual sobre isso. Não invente: diga que não encontrou e ofereça abrir chamado.'];
        }

        return ['trechos' => array_map(fn ($a) => ['titulo' => $a['titulo'], 'texto' => Str::limit($a['texto'], 2200)], $achados)];
    }

    private function consultarNotas(array $a): array
    {
        $dias = min(365, max(1, (int) ($a['dias'] ?? 30)));
        $q = NotaFiscal::query()
            ->where('notas_fiscais.empresa_id', $this->user->empresa_id)
            ->with('unidade:id,nome')
            ->orderByDesc('id');
        if (! empty($a['numero'])) {
            $q->where('numero', (int) $a['numero']);
        } else {
            $q->where('created_at', '>=', now()->subDays($dias));
        }
        if (! empty($a['tipo'])) {
            $q->where('tipo', $a['tipo']);
        }
        if (! empty($a['situacao'])) {
            $q->where('status', $a['situacao']);
        }
        $notas = $q->limit(10)->get();
        if ($notas->isEmpty()) {
            return ['resultado' => 'Nenhuma nota encontrada com esse filtro.'];
        }

        return ['notas' => $notas->map(fn (NotaFiscal $n) => [
            'nota_id' => $n->id,
            'tipo' => $n->tipo?->value,
            'numero' => $n->numero,
            'serie' => $n->serie,
            'situacao' => $n->status?->value,
            'loja' => $n->unidade?->nome,
            'valor' => (float) $n->valor_total,
            'criada_em' => $n->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'mensagem' => Str::limit((string) $n->focus_mensagem, 300),
            'venda_id' => $n->venda_id,
        ])->all()];
    }

    private function detalharNota(int $id): array
    {
        $n = NotaFiscal::query()->where('empresa_id', $this->user->empresa_id)->find($id);
        if (! $n) {
            return ['erro' => 'Nota não encontrada nesta empresa.'];
        }

        $itens = [];
        $venda = $n->venda_id ? Venda::query()->where('empresa_id', $this->user->empresa_id)->with('itens.produto')->find($n->venda_id) : null;
        foreach (($venda?->itens ?? collect())->take(30) as $it) {
            $p = $it->produto;
            $itens[] = [
                'produto_id' => $it->produto_id,
                'descricao' => $p?->descricao ?? $it->descricao ?? null,
                'quantidade' => (float) $it->quantidade,
                'total' => (float) $it->total,
                'ncm' => $it->snapshot_ncm ?? $p?->ncm,
                'cest' => $it->snapshot_cest ?? $p?->cest,
                'cfop' => $it->snapshot_cfop ?? $p?->cfop,
                'cst_csosn' => $it->snapshot_cst_csosn ?? $p?->cst_csosn,
                'origem' => $it->snapshot_origem ?? $p?->origem,
                'codigo_barras' => $p?->codigo_barras,
            ];
        }

        $cliente = null;
        if ($n->cliente_id) {
            $c = $n->cliente()->withoutGlobalScopes()->where('empresa_id', $this->user->empresa_id)->first();
            if ($c) {
                $doc = preg_replace('/\D/', '', (string) $c->cpf_cnpj);
                $cliente = [
                    'nome' => $c->nome_razao_social,
                    'tipo' => $c->tipo_pessoa,
                    'documento' => $doc ? (strlen($doc) === 14 ? 'CNPJ' : 'CPF') . ' terminado em ' . substr($doc, -4) . ' (' . strlen($doc) . ' dígitos)' : 'sem CPF/CNPJ',
                    'ie' => $c->ie ?: 'vazia',
                    'endereco_completo' => (bool) ($c->cep && $c->logradouro && $c->cidade && $c->uf),
                    'faltando' => array_values(array_filter([
                        $c->cep ? null : 'CEP', $c->logradouro ? null : 'rua', filled($c->getAttribute('numero')) ? null : 'número',
                        filled($c->getAttribute('bairro')) ? null : 'bairro', $c->cidade ? null : 'cidade', $c->uf ? null : 'UF',
                    ])),
                    'uf' => $c->uf,
                ];
            }
        }

        $eventos = method_exists($n, 'eventos')
            ? $n->eventos()->latest('id')->limit(5)->get()->map(fn ($e) => Str::limit(json_encode(collect($e->getAttributes())->only(['tipo', 'status', 'mensagem', 'created_at'])->all(), JSON_UNESCAPED_UNICODE), 300))->all()
            : [];

        return [
            'nota_id' => $n->id,
            'tipo' => $n->tipo?->value,
            'numero' => $n->numero,
            'serie' => $n->serie,
            'situacao' => $n->status?->value,
            'situacao_focus' => $n->focus_status,
            'mensagem_sefaz_focus' => Str::limit((string) $n->focus_mensagem, 1500),
            'ambiente' => $n->ambiente,
            'valor' => (float) $n->valor_total,
            'natureza_operacao' => $n->natureza_operacao,
            'loja' => $n->unidade()->value('nome'),
            'criada_em' => $n->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'venda' => $venda ? ['venda_id' => $venda->id, 'numero' => $venda->numero, 'tipo' => $venda->tipo ?? null] : null,
            'cliente' => $cliente,
            'itens' => $itens,
            'eventos' => $eventos,
            'link_tela' => url('/app/notas-fiscais/' . $n->id),
        ];
    }

    private function situacaoFiscal(): array
    {
        $configs = ConfiguracaoFiscal::query()->where('empresa_id', $this->user->empresa_id)->get();
        $unidades = Unidade::query()->where('empresa_id', $this->user->empresa_id)->pluck('nome', 'id');

        return ['lojas' => $configs->map(fn ($c) => [
            'loja' => $unidades[$c->unidade_id] ?? ('loja ' . $c->unidade_id),
            'emissao_ligada' => (bool) $c->emissao_fiscal_ativa,
            'emite_nfe' => (bool) $c->emite_nfe,
            'emite_nfce' => (bool) $c->emite_nfce,
            'emite_nfse' => (bool) $c->emite_nfse,
            'ambiente' => $c->ambiente,
            'serie_nfe' => $c->serie_nfe,
            'serie_nfce' => $c->serie_nfce,
            'serie_nfse' => $c->serie_nfse,
            'csc_nfce_preenchido' => filled($c->csc_nfce) && filled($c->csc_id_nfce),
            'certificado_valido_ate' => $c->certificado_validade ? \Carbon\Carbon::parse($c->certificado_validade)->format('d/m/Y') : 'não enviado',
            'certificado_vencido' => $c->certificado_validade ? \Carbon\Carbon::parse($c->certificado_validade)->isPast() : null,
        ])->values()->all()];
    }

    private function consultarProduto(string $busca): array
    {
        $busca = trim($busca);
        if (mb_strlen($busca) < 2) {
            return ['erro' => 'Informe ao menos 2 letras.'];
        }
        $produtos = Produto::query()
            ->where('empresa_id', $this->user->empresa_id)
            ->where(fn ($q) => $q->where('descricao', 'like', '%' . $busca . '%')
                ->orWhere('codigo_interno', $busca)
                ->orWhere('codigo_barras', $busca))
            ->orderByRaw('descricao = ? desc', [$busca])
            ->limit(5)
            ->get();
        if ($produtos->isEmpty()) {
            return ['resultado' => 'Nenhum produto encontrado.'];
        }
        $estoque = app(EstoqueMultiUnidadeService::class);
        $verCusto = $this->pode('produtos', 'editar');

        return ['produtos' => $produtos->map(fn (Produto $p) => array_filter([
            'produto_id' => $p->id,
            'descricao' => $p->descricao,
            'codigo_interno' => $p->codigo_interno,
            'codigo_barras' => $p->codigo_barras,
            'situacao' => is_object($p->status) ? $p->status->value : $p->status,
            'preco_venda' => (float) $p->preco_venda,
            'preco_custo' => $verCusto ? (float) $p->preco_custo : null,
            'ncm' => $p->ncm, 'cest' => $p->cest, 'cfop' => $p->cfop, 'cst_csosn' => $p->cst_csosn, 'origem' => $p->origem,
            'saldo_por_loja' => array_map(fn ($s) => ['loja' => $s['nome'], 'saldo' => $s['saldo']],
                $estoque->saldoPorUnidade((int) $this->user->empresa_id, (int) $p->id, (int) session('unidade_id'))),
        ], fn ($v) => $v !== null))->all()];
    }

    private function consultarVenda(string $numero): array
    {
        $numero = trim(ltrim($numero, '#'));
        $v = Venda::query()->where('empresa_id', $this->user->empresa_id)
            ->where(fn ($q) => $q->where('numero', $numero)->orWhere('id', ctype_digit($numero) ? (int) $numero : 0))
            ->orderByRaw('numero = ? desc', [$numero])
            ->with(['itens.produto:id,descricao', 'unidade:id,nome', 'vendedor:id,name'])
            ->first();
        if (! $v) {
            return ['erro' => 'Venda não encontrada nesta empresa.'];
        }
        $nota = NotaFiscal::query()->where('empresa_id', $this->user->empresa_id)->where('venda_id', $v->id)->latest('id')->first();

        return [
            'venda_id' => $v->id,
            'numero' => $v->numero,
            'situacao' => is_object($v->status) ? $v->status->value : $v->status,
            'loja' => $v->unidade?->nome,
            'vendedor' => $v->vendedor?->name,
            'total' => (float) $v->total,
            'forma_pagamento' => $v->forma_pagamento,
            'data' => $v->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'itens' => $v->itens->take(30)->map(fn ($i) => ['produto' => $i->produto?->descricao, 'quantidade' => (float) $i->quantidade, 'total' => (float) $i->total])->all(),
            'nota' => $nota ? ['nota_id' => $nota->id, 'tipo' => $nota->tipo?->value, 'numero' => $nota->numero, 'situacao' => $nota->status?->value] : 'sem nota fiscal',
        ];
    }

    private function caixasAbertos(): array
    {
        $caixas = Caixa::query()->where('empresa_id', $this->user->empresa_id)
            ->where('status', 'aberto')
            ->with(['unidade:id,nome'])
            ->limit(20)->get();
        $nomes = User::withoutGlobalScopes()->whereIn('id', $caixas->pluck('user_id'))->pluck('name', 'id');

        return [
            'regra' => 'O caixa é de quem abriu e da loja: cada operador abre o seu; o dono não vê no PDV o caixa aberto pelo vendedor.',
            'usuario_atual_tem_caixa_aberto_nesta_loja' => $caixas->contains(fn ($c) => $c->user_id === $this->user->id && (int) $c->unidade_id === (int) session('unidade_id')),
            'caixas' => $caixas->map(fn ($c) => [
                'loja' => $c->unidade?->nome,
                'numero_caixa' => $c->numero_caixa,
                'aberto_por' => $nomes[$c->user_id] ?? '?',
                'aberto_em' => $c->aberto_em ? \Carbon\Carbon::parse($c->aberto_em)->timezone('America/Sao_Paulo')->format('d/m/Y H:i') : null,
            ])->all(),
        ];
    }

    // ------------------------------------------------------------------

    private function pode(string $modulo, string $acao = 'ver'): bool
    {
        return CheckPermission::canUser($this->user, $modulo, $acao);
    }

    private function exige(string $modulo, \Closure $fn): array
    {
        return $this->pode($modulo) ? $fn() : $this->negado();
    }

    private function negado(): array
    {
        return ['erro' => 'O perfil deste usuário não tem acesso a esta informação. Diga que quem pode ver é o dono ou o gerente.'];
    }

    private function def(string $nome, string $desc, array $props, array $obrig = []): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $nome,
            'description' => $desc,
            'parameters' => ['type' => 'object', 'properties' => (object) $props, 'required' => $obrig],
        ]];
    }
}
