<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoCaixa;
use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\AdquirenteTaxa;
use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\MovimentacaoCaixa;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vale;
use App\Models\ValeUso;
use App\Models\Devolucao;
use App\Scopes\UnidadeScope;
use App\Services\TrocaService;
use App\Models\ConfiguracaoFiscal;
use App\Models\ConfiguracaoLoja;
use App\Services\EstoqueMultiUnidadeService;
use App\Services\JurosParcelamentoService;
use App\Services\SaldoEstoque;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFCeService;
use App\Services\TabelaPrecoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PdvController extends Controller
{
    public function index()
    {
        $caixaId = session('caixa_id');
        $caixa = null;

        if ($caixaId) {
            $caixa = Caixa::with('operador')->find($caixaId);
            if ($caixa && $caixa->status->value !== 'aberto') {
                session()->forget('caixa_id');
                $caixa = null;
            }
        }

        $unidade = Unidade::find(session('unidade_id'));

        // Fiscal config for the indicator
        $configFiscal = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->first();

        // Quem pode ser o vendedor da venda: todos os perfis que vendem.
        // O operador logado fica de fora da lista — ele já é o padrão do select.
        $operadores = User::where('empresa_id', session('empresa_id'))
            ->where('status', 'ativo')
            ->whereIn('perfil', ['caixa', 'vendedor', 'gerente', 'dono'])
            ->where('id', '!=', auth()->id())
            ->select('id', 'name', 'perfil')
            ->orderBy('name')
            ->get();

        $operadores = static::filtrarOperadoresPelaLoja($operadores, $unidade?->empresa);

        $configLoja = ConfiguracaoLoja::daUnidade();

        // Estoques ativos da loja — o modal de troca (F6) só mostra o seletor
        // quando há mais de um (salão/depósito/avaria)
        $estoquesLoja = \App\Models\Estoque::withoutGlobalScopes()
            ->where('unidade_id', session('unidade_id'))
            ->where('status', 'ativo')
            ->orderByDesc('permite_venda')
            ->orderBy('nome')
            ->get(['id', 'nome', 'permite_venda']);

        return view('app.pdv.index', compact('caixa', 'unidade', 'configFiscal', 'operadores', 'configLoja', 'estoquesLoja'));
    }

    /**
     * Select de vendedor (F3) só com quem está vinculado à loja da sessão, quando
     * a empresa liga `pdv_vendedores_da_loja` (04/09/2026).
     *
     * Motivo: MISS MERLINDA tem 6 lojas e 4 vendedores, cada um vinculado a UMA
     * loja — o caixa de qualquer loja via os 4 no mesmo select.
     *
     * Três exceções, todas ditadas pelo dado real de produção:
     *  - **dono e admin aparecem sempre**: o dono da MISS MERLINDA está vinculado
     *    a 1 loja de 6 e sumiria das outras 5; no resto do sistema ele também não
     *    é preso pelo `UnidadeScope`;
     *  - **sem vínculo nenhum aparece em todas**: não dá para dizer onde a pessoa
     *    está, e esconder esvaziaria o select (mesmo fallback do
     *    `MultilojaController::unidadesVisiveis`);
     *  - o operador logado já saiu da lista antes daqui e continua sendo o padrão,
     *    então o select nunca fica sem opção.
     *
     * O filtro é feito em PHP sobre uma consulta só ao pivô: a lista tem no máximo
     * algumas dezenas de nomes, e `whereHas('unidades')` traria os global scopes de
     * `Unidade` para dentro da subconsulta sem necessidade.
     */
    private static function filtrarOperadoresPelaLoja($operadores, ?\App\Models\Empresa $empresa)
    {
        if (! $empresa?->pdvVendedoresDaLoja() || $operadores->isEmpty()) {
            return $operadores;
        }

        $unidadeId = (int) session('unidade_id');

        $vinculos = \Illuminate\Support\Facades\DB::table('unidade_user')
            ->whereIn('user_id', $operadores->pluck('id'))
            ->get(['user_id', 'unidade_id'])
            ->groupBy('user_id');

        return $operadores->filter(function ($operador) use ($unidadeId, $vinculos) {
            $perfil = $operador->perfil instanceof \App\Enums\Perfil
                ? $operador->perfil->value
                : $operador->perfil;

            if (in_array($perfil, ['dono', 'admin'], true)) {
                return true;
            }

            $lojas = $vinculos->get($operador->id);

            return $lojas === null || $lojas->pluck('unidade_id')->contains($unidadeId);
        })->values();
    }

    public function verificarEstoque(Request $request, $produtoId, EstoqueMultiUnidadeService $estoqueSvc)
    {
        $produto = Produto::find($produtoId);
        $empresa = $request->user()->empresa;
        $unidadeAtual = (int) session('unidade_id');

        // Saldo da LOJA (soma dos estoques dela) — o vendedor pensa em loja,
        // não em depósito. A baixa é que sai do estoque de venda.
        $saldoAtual = SaldoEstoque::naUnidade($unidadeAtual, (int) $produtoId);

        $response = [
            'produto_id' => $produtoId,
            'estoque_atual' => $saldoAtual,
            'estoque_minimo' => $produto->estoque_minimo ?? 0,
            'pode_vender_remoto' => false,
            'outras_unidades' => [],
        ];

        if ($empresa?->permiteVerEstoqueOutrasUnidades()) {
            $response['outras_unidades'] = $estoqueSvc->outrasUnidadesComEstoque(
                $empresa->id, (int) $produtoId, $unidadeAtual
            );
            $response['pode_vender_remoto'] = $empresa->permiteVenderEstoqueRemoto()
                && count($response['outras_unidades']) > 0;
        }

        return response()->json($response);
    }

    public function buscarProduto(Request $request, $codigo, TabelaPrecoService $tabelaPrecos)
    {
        $produtos = Produto::where('empresa_id', session('empresa_id'))
            ->where('status', 'ativo')
            ->where(function ($q) use ($codigo) {
                $q->where('codigo_barras', $codigo)
                  ->orWhere('codigo_interno', $codigo)
                  ->orWhere('descricao', 'like', "%{$codigo}%");
            })
            ->select('id', 'codigo_interno', 'codigo_barras', 'descricao', 'preco_venda', 'unidade_medida')
            ->with('precos:id,produto_id,modalidade,valor')
            ->limit(20)
            ->get();

        // Tabelas de preço por forma de pagamento (dinheiro_pix/debito/credito)
        $configLoja = ConfiguracaoLoja::daUnidade();
        $produtos = $produtos->map(function ($p) use ($tabelaPrecos, $configLoja) {
            $dados = $p->only(['id', 'codigo_interno', 'codigo_barras', 'descricao', 'preco_venda', 'unidade_medida']);
            $dados['precos'] = $tabelaPrecos->precosDoProduto($p, $configLoja);
            return $dados;
        });

        return response()->json($produtos);
    }

    public function buscarCliente(Request $request, $termo)
    {
        $clientes = Cliente::where('empresa_id', session('empresa_id'))
            ->where(function ($q) use ($termo) {
                $q->where('nome_razao_social', 'like', "%{$termo}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$termo}%");
            })
            ->select('id', 'nome_razao_social', 'cpf_cnpj', 'tipo_preco')
            ->limit(10)
            ->get();

        return response()->json($clientes);
    }

    public function registrarVenda(Request $request)
    {
        $request->validate([
            'itens'                    => 'required|array|min:1',
            'itens.*.produto_id'       => 'required|exists:produtos,id',
            'itens.*.quantidade'       => 'required|numeric|min:0.001',
            'itens.*.preco_unitario'   => 'required|numeric|min:0',
            'itens.*.desconto_valor'   => 'nullable|numeric|min:0',
            'itens.*.unidade_origem_id'=> 'nullable|integer|exists:unidades,id',
            'pagamentos'               => 'required|array|min:1',
            'pagamentos.*.forma'       => 'required|string',
            'pagamentos.*.valor'       => 'required|numeric|min:0.01',
            'pagamentos.*.parcelas'    => 'nullable|integer|min:1|max:24',
            'juros_parcelamento'       => 'nullable|boolean',
            'cliente_id'               => 'nullable|exists:clientes,id',
            'cpf_cnpj_nota'            => 'nullable|string|max:18',
            'desconto_valor'           => 'nullable|numeric|min:0',
            'desconto_percentual'      => 'nullable|numeric|min:0|max:100',
            'vendedor_id'              => 'nullable|exists:users,id',
            'tabela_precos'            => 'nullable|boolean',
            'documento'                => 'nullable|in:recibo,cupom_fiscal',
            // Vale de troca (03/09/2026)
            'pagamentos.*.vale_codigo' => 'nullable|string|max:20',
            'vale_sobra_dinheiro'      => 'nullable|boolean',
            'troca_devolucao_id'       => 'nullable|integer',
        ]);

        $caixaId = session('caixa_id');
        if (!$caixaId) {
            return response()->json(['error' => 'Nenhum caixa aberto. Abra o caixa antes de registrar vendas.'], 422);
        }

        $caixa = Caixa::find($caixaId);
        if (!$caixa || $caixa->status->value !== 'aberto') {
            session()->forget('caixa_id');
            return response()->json(['error' => 'Caixa nao esta aberto.'], 422);
        }

        try {
            $venda = DB::transaction(function () use ($request, $caixa) {
                $empresaId = session('empresa_id');
                $unidadeId = session('unidade_id');

                $ultimoNumero = Venda::withoutGlobalScopes()
                    ->where('empresa_id', $empresaId)
                    ->max('numero');
                $numero = $ultimoNumero ? $ultimoNumero + 1 : 1;

                // Tabela de preço por forma de pagamento: o servidor é a autoridade.
                // Gated pelo flag tabela_precos (front novo) para não repricear payloads
                // de abas do PDV abertas antes do deploy.
                $tabelaPrecos = null;
                $modalidade = null;
                $configLoja = null;
                if ($request->boolean('tabela_precos')) {
                    $tabelaPrecos = app(TabelaPrecoService::class);
                    $configLoja = ConfiguracaoLoja::daUnidade($empresaId, $unidadeId);
                    $formas = array_column($request->pagamentos, 'forma');
                    // Cliente de atacado leva o preço de atacado em qualquer forma
                    $clienteVenda = $request->cliente_id
                        ? Cliente::find($request->cliente_id)
                        : null;
                    $modalidade = $tabelaPrecos->modalidadeDaVenda($formas, $configLoja, $clienteVenda);
                }

                $subtotal = 0;
                $itensData = [];

                foreach ($request->itens as $item) {
                    $produto = Produto::find($item['produto_id']);
                    if (!$produto) continue;

                    $precoUnit = $item['preco_unitario'];
                    if ($modalidade !== null) {
                        $precoUnit = $tabelaPrecos->precosDoProduto($produto, $configLoja)[$modalidade];
                    }
                    $qtd = $item['quantidade'];
                    $descontoValor = $item['desconto_valor'] ?? 0;
                    $totalItem = round(($precoUnit * $qtd) - $descontoValor, 2);

                    $itensData[] = [
                        'produto_id'         => $item['produto_id'],
                        'descricao'          => $produto->descricao,
                        'quantidade'         => $qtd,
                        'preco_unitario'     => $precoUnit,
                        'desconto_valor'     => $descontoValor,
                        'desconto_percentual'=> 0,
                        'total'              => $totalItem,
                        // Venda remota: estoque vem de outra unidade da mesma empresa
                        'unidade_origem_id'  => $item['unidade_origem_id'] ?? null,
                    ];

                    $subtotal += $totalItem;
                }

                if (empty($itensData)) {
                    throw new \Exception('Nenhum item valido na venda.');
                }

                $descontoPercentual = $request->desconto_percentual ?? 0;
                $descontoGeral = $request->desconto_valor ?? 0;

                // If discount is percentage-based, calculate the value
                if ($descontoPercentual > 0 && $descontoGeral == 0) {
                    $descontoGeral = round($subtotal * ($descontoPercentual / 100), 2);
                }

                $total = round($subtotal - $descontoGeral, 2);
                if ($total < 0) $total = 0;

                // Determine forma_pagamento and troco
                $pagamentos = $request->pagamentos;

                // Juros de parcelamento (Tabela Price) — o servidor é a autoridade:
                // o front manda o valor SEM juros e o nº de parcelas, aqui o
                // acréscimo é recalculado. Só incide sobre a parte paga em crédito
                // parcelado; num split, dinheiro/PIX/débito não pegam nada.
                // Gated pelo flag juros_parcelamento (front novo) para não onerar
                // payloads de abas do PDV abertas antes do deploy.
                $configLojaJuros = $configLoja ?? ConfiguracaoLoja::daUnidade($empresaId, $unidadeId);
                $jurosService = app(JurosParcelamentoService::class);
                $acrescimoJuros = 0;

                // Acréscimo do cartão POR PARTE (04/09/2026), quando a loja usa a
                // regra `por_parte`: o item fica no preço à vista e cada forma paga
                // o acréscimo dela sobre o que foi pago nela. Numa venda de R$ 300
                // com R$ 100 no PIX e R$ 200 no crédito a 10%, só os R$ 200 sobem.
                //
                // Roda ANTES do juros de propósito: na maquininha o parcelamento
                // incide sobre o valor que passou nela, já acrescido.
                //
                // O servidor é a autoridade (mesma regra do juros): o front manda o
                // valor à vista e a conta é refeita aqui.
                $tabelaPreco = app(TabelaPrecoService::class);
                $acrescimoFormas = 0;

                // ⚠️ Só entra quando o SERVIDOR reprecificou os itens ($modalidade
                // definido, isto é, o front mandou `tabela_precos`). Um PDV antigo
                // demais para esse flag manda os itens já na tabela do cartão — somar
                // o acréscimo por cima cobraria o cliente duas vezes. Sem o flag, a
                // venda segue o comportamento antigo e ninguém é surpreendido.
                if ($modalidade !== null && $tabelaPreco->cobraPorParte($configLojaJuros)) {
                    foreach ($pagamentos as $idx => $pgto) {
                        $calc = $tabelaPreco->acrescimoSobre(
                            (float) $pgto['valor'],
                            (string) ($pgto['forma'] ?? ''),
                            $configLojaJuros
                        );

                        if (! $calc['tem_acrescimo'] || $calc['acrescimo'] <= 0) {
                            continue;
                        }

                        // O `valor` fica COM acréscimo: é o que passa na maquininha,
                        // o que entra no caixa e o que a nota soma nas formas de
                        // pagamento. O valor à vista fica guardado ao lado.
                        $pagamentos[$idx]['valor_sem_acrescimo']    = $calc['total'] - $calc['acrescimo'];
                        $pagamentos[$idx]['valor']                  = $calc['total'];
                        $pagamentos[$idx]['acrescimo_forma_valor']  = $calc['acrescimo'];
                        $pagamentos[$idx]['acrescimo_forma_percentual'] = $calc['percentual'];

                        $acrescimoFormas = round($acrescimoFormas + $calc['acrescimo'], 2);
                    }

                    $total = round($total + $acrescimoFormas, 2);
                }

                if ($request->boolean('juros_parcelamento')) {
                    foreach ($pagamentos as $idx => $pgto) {
                        $parcelasPgto = max(1, (int) ($pgto['parcelas'] ?? 1));

                        if (! $jurosService->aplicavel($pgto['forma'], $parcelasPgto)) {
                            continue;
                        }

                        $simulacao = $jurosService->simular((float) $pgto['valor'], $parcelasPgto, $configLojaJuros);

                        if (! $simulacao['tem_juros']) {
                            continue;
                        }

                        // pagamento_detalhes.valor fica COM juros: é o que a
                        // maquininha passa, o que entra no caixa e o que o
                        // fiscal soma nas formas de pagamento da nota.
                        $pagamentos[$idx]['valor_sem_juros']      = round((float) $pgto['valor'], 2);
                        $pagamentos[$idx]['valor']                = $simulacao['total'];
                        $pagamentos[$idx]['valor_parcela']        = $simulacao['valor_parcela'];
                        $pagamentos[$idx]['juros_valor']          = $simulacao['juros_valor'];
                        $pagamentos[$idx]['juros_percentual']     = $simulacao['percentual'];

                        $acrescimoJuros = round($acrescimoJuros + $simulacao['juros_valor'], 2);
                    }

                    $total = round($total + $acrescimoJuros, 2);
                }

                // Vale de troca como forma de pagamento (03/09/2026): o código
                // precisa existir NESTA empresa, estar ativo, dentro da validade e
                // com saldo. Lock de linha: dois caixas com o mesmo vale não gastam
                // o mesmo crédito duas vezes.
                $valesAplicar = [];
                foreach ($pagamentos as $idx => $pgto) {
                    if (($pgto['forma'] ?? '') !== 'vale') {
                        continue;
                    }
                    $codigoVale = Vale::normalizarCodigo((string) ($pgto['vale_codigo'] ?? ''));
                    $vale = $codigoVale !== ''
                        ? Vale::withoutGlobalScopes()->where('empresa_id', $empresaId)->where('codigo', $codigoVale)->lockForUpdate()->first()
                        : null;
                    if (! $vale) {
                        throw new \DomainException('Vale não encontrado. Informe o código impresso no comprovante da troca.');
                    }
                    if ($motivoVale = $vale->motivoIndisponivel()) {
                        throw new \DomainException($motivoVale);
                    }
                    $valorVale = round((float) $pgto['valor'], 2);
                    if ($valorVale > (float) $vale->saldo + 0.001) {
                        throw new \DomainException('O vale ' . $vale->codigo . ' tem saldo de R$ ' . number_format((float) $vale->saldo, 2, ',', '.') . '.');
                    }
                    if ($valorVale > $total + 0.001) {
                        throw new \DomainException('O valor do vale não pode passar do total da venda.');
                    }
                    $pagamentos[$idx]['vale_codigo'] = $vale->codigo;
                    $pagamentos[$idx]['vale_saldo_restante'] = round((float) $vale->saldo - $valorVale, 2);
                    $valesAplicar[] = [$vale, $valorVale];
                }

                $formaPrincipal = $pagamentos[0]['forma'];
                $totalPago = collect($pagamentos)->sum('valor');
                $troco = max(0, round($totalPago - $total, 2));

                $venda = Venda::create([
                    'empresa_id'          => $empresaId,
                    'unidade_id'          => $unidadeId,
                    'cliente_id'          => $request->cliente_id,
                    'cpf_cnpj_nota'       => $request->cpf_cnpj_nota ? \App\Support\Cnpj::limparCpfCnpj($request->cpf_cnpj_nota) : null,
                    'vendedor_id'         => $request->vendedor_id ?? auth()->id(),
                    'caixa_id'            => $caixa->id,
                    'numero'              => $numero,
                    'subtotal'            => $subtotal,
                    'desconto_percentual' => $descontoPercentual,
                    'desconto_valor'      => $descontoGeral,
                    'total'               => $total,
                    'forma_pagamento'     => count($pagamentos) > 1 ? 'misto' : $formaPrincipal,
                    'pagamento_detalhes'  => $pagamentos,
                    'troco'               => $troco,
                    'status'              => StatusVenda::Concluida,
                    'tipo'                => 'pdv',
                    'canal'               => \App\Enums\CanalVenda::Presencial->value,
                ]);

                // Create VendaItens + descarga de estoque (local OU remoto)
                $estoqueRemoto = app(EstoqueMultiUnidadeService::class);
                foreach ($itensData as $i => $itemData) {
                    $vendaItem = $venda->itens()->create($itemData);
                    $produtoId = $itemData['produto_id'];
                    $qtd = $itemData['quantidade'];
                    $origemRemota = $itemData['unidade_origem_id'] ?? null;

                    if ($origemRemota && $origemRemota !== $unidadeId) {
                        // Venda remota: baixa estoque da outra unidade + cria transferência
                        $empresa = Venda::find($venda->id)->empresa;
                        if (! $empresa?->permiteVenderEstoqueRemoto()) {
                            throw new \Exception('Política da empresa não permite venda remota.');
                        }
                        $estoqueRemoto->registrarVendaRemota($venda, $vendaItem, $origemRemota, (int) auth()->id());
                    } else {
                        // Venda local — baixa no estoque de venda da loja ativa
                        SaldoEstoque::registrar(
                            $empresaId,
                            $unidadeId,
                            SaldoEstoque::estoqueDeVendaId($unidadeId),
                            $produtoId,
                            TipoMovimentacaoEstoque::Saida->value,
                            -$qtd,
                            [
                                'custo_unitario' => $itemData['preco_unitario'],
                                'origem_tipo'    => Venda::class,
                                'origem_id'      => $venda->id,
                                'observacoes'    => "Venda PDV #{$venda->numero}",
                            ]
                        );
                    }
                }

                // Abate o(s) vale(s) usado(s) e, se o caixa pediu e a loja permite,
                // devolve a sobra em dinheiro pela gaveta (troca "com troco").
                foreach ($valesAplicar as [$vale, $valorVale]) {
                    $vale->abater($valorVale, 'venda', $venda->id);

                    if ($request->boolean('vale_sobra_dinheiro')
                        && (float) $vale->saldo > 0
                        && $configLojaJuros->troca_sobra === 'dinheiro') {
                        $sobraVale = (float) $vale->saldo;
                        MovimentacaoCaixa::create([
                            'empresa_id'      => $empresaId,
                            'unidade_id'      => $unidadeId,
                            'caixa_id'        => $caixa->id,
                            'tipo'            => TipoMovimentacaoCaixa::Devolucao,
                            'valor'           => $sobraVale,
                            'forma_pagamento' => 'dinheiro',
                            'descricao'       => "Sobra do vale {$vale->codigo} — venda #{$venda->numero}",
                            'user_id'         => auth()->id(),
                        ]);
                        $vale->abater($sobraVale, 'dinheiro', $venda->id);
                    }
                }

                // Troca feita no PDV (F6): liga a devolução à venda nova
                if ($request->filled('troca_devolucao_id')) {
                    Devolucao::withoutGlobalScopes()
                        ->where('empresa_id', $empresaId)
                        ->where('id', (int) $request->troca_devolucao_id)
                        ->update(['venda_nova_id' => $venda->id]);
                }

                // Vendedor responsável pela entrada no caixa (Configurações da Loja)
                $configLojaOp = $configLojaJuros;
                $responsavelCaixa = ($configLojaOp->vendedor_responsavel_caixa && $request->vendedor_id)
                    ? (int) $request->vendedor_id
                    : auth()->id();

                // Create MovimentacaoCaixa (venda) — uma por forma de pagamento,
                // para a conferência do fechamento bater só o que fica na gaveta.
                // Troco sai do dinheiro: desconta do valor em espécie recebido.
                $trocoRestante = $troco;
                foreach ($pagamentos as $pgto) {
                    $valorMov = (float) $pgto['valor'];
                    if ($pgto['forma'] === 'dinheiro' && $trocoRestante > 0) {
                        $abate = min($valorMov, $trocoRestante);
                        $valorMov -= $abate;
                        $trocoRestante -= $abate;
                    }
                    if ($valorMov <= 0) {
                        continue;
                    }
                    MovimentacaoCaixa::create([
                        'empresa_id'      => $empresaId,
                        'unidade_id'      => $unidadeId,
                        'caixa_id'        => $caixa->id,
                        'tipo'            => TipoMovimentacaoCaixa::Venda,
                        'valor'           => $valorMov,
                        'forma_pagamento' => $pgto['forma'],
                        'descricao'       => "Venda #{$venda->numero}",
                        'user_id'         => $responsavelCaixa,
                    ]);
                }

                // Create ContaReceber entries. Cartão com regra de adquirente
                // cadastrada vira recebível pendente com data prevista (D+prazo,
                // parcelas seguintes a cada 30d) e valor líquido descontada a taxa.
                foreach ($pagamentos as $pgto) {
                    $valorPgto = min($pgto['valor'], $total);
                    $forma = $pgto['forma'];
                    $parcelas = max(1, (int) ($pgto['parcelas'] ?? 1));

                    $regra = in_array($forma, ['cartao_credito', 'cartao_debito'], true)
                        ? AdquirenteTaxa::paraPagamento((int) $empresaId, $forma, $parcelas)
                        : null;

                    if ($regra) {
                        $valorParcela = round($valorPgto / $parcelas, 2);
                        $acumulado = 0;
                        for ($i = 1; $i <= $parcelas; $i++) {
                            $valorEsta = $i === $parcelas
                                ? round($valorPgto - $acumulado, 2)
                                : $valorParcela;
                            $acumulado = round($acumulado + $valorEsta, 2);

                            ContaReceber::create([
                                'empresa_id'         => $empresaId,
                                'unidade_id'         => $unidadeId,
                                'cliente_id'         => $request->cliente_id,
                                'venda_id'           => $venda->id,
                                'descricao'          => "Venda PDV #{$venda->numero} - " . ucfirst(str_replace('_', ' ', $forma)) . " {$i}/{$parcelas} ({$regra->nome})",
                                'valor'              => $valorEsta,
                                'valor_pago'         => 0,
                                'vencimento'         => now()->addDays($regra->prazo_dias + 30 * ($i - 1)),
                                'pago_em'            => null,
                                'forma_pagamento'    => $forma,
                                'adquirente_taxa_id' => $regra->id,
                                'taxa_percentual'    => $regra->taxa_percentual,
                                'valor_liquido'      => round($valorEsta * (1 - ((float) $regra->taxa_percentual) / 100), 2),
                                'parcela'            => $i,
                                'total_parcelas'     => $parcelas,
                                'status'             => 'pendente',
                            ]);
                        }
                        continue;
                    }

                    ContaReceber::create([
                        'empresa_id'      => $empresaId,
                        'unidade_id'      => $unidadeId,
                        'cliente_id'      => $request->cliente_id,
                        'venda_id'        => $venda->id,
                        'descricao'       => "Venda PDV #{$venda->numero} - " . ucfirst($forma),
                        'valor'           => $valorPgto,
                        'valor_pago'      => $valorPgto,
                        'vencimento'      => now(),
                        'pago_em'         => now(),
                        'forma_pagamento' => $forma,
                        'parcela'         => 1,
                        'total_parcelas'  => 1,
                        'status'          => 'paga',
                    ]);
                }

                // Calculate and create Comissao for vendedor
                $vendedorId = $request->vendedor_id ?? auth()->id();
                if ($vendedorId) {
                    $percentualComissao = 5;
                    $valorComissao = round($total * ($percentualComissao / 100), 2);

                    Comissao::create([
                        'empresa_id'     => $empresaId,
                        'unidade_id'     => $unidadeId,
                        'user_id'        => $vendedorId,
                        'venda_id'       => $venda->id,
                        'valor_venda'    => $total,
                        'percentual'     => $percentualComissao,
                        'valor_comissao' => $valorComissao,
                        'status'         => 'pendente',
                    ]);
                }

                return $venda;
            });

            $venda->load(['itens.produto', 'cliente', 'vendedor', 'empresa']);

            // Vale usado nesta venda (para o modal e o cupom)
            $valeUsado = collect($venda->pagamento_detalhes ?? [])->firstWhere('forma', 'vale');
            $valeSobraDevolvida = (float) ValeUso::where('venda_id', $venda->id)->where('tipo', 'dinheiro')->sum('valor');

            // Verificar configuracao fiscal da unidade
            $empresaId = session('empresa_id');
            $unidadeId = session('unidade_id');
            $config = ConfiguracaoFiscal::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->where('unidade_id', $unidadeId)
                ->first();

            $notaFiscal = null;
            $cupomHtml = '';

            // Decide se emite NFC-e: precisa estar fiscal ativo + flag emite_nfce
            // (fallback compatível: antes era apenas tipo_cupom_pdv=fiscal)
            $deveEmitirNfce = $config
                && $config->emissao_fiscal_ativa
                && ($config->emite_nfce ?? $config->tipo_cupom_pdv === 'fiscal');

            // Escolha manual do operador prevalece; sem escolha, vale a
            // parametrização das Configurações da Loja (cartão automático,
            // CPF→fiscal, padrão de impressão). Loja sem registro de config
            // mantém o comportamento antigo: fiscal ativo = sempre NFC-e.
            $escolhaOperador = $request->input('documento');
            $configLojaEmissao = ConfiguracaoLoja::daUnidade($empresaId, $unidadeId);

            if ($escolhaOperador === 'recibo') {
                $deveEmitirNfce = false;
            } elseif ($escolhaOperador !== 'cupom_fiscal' && $deveEmitirNfce && $configLojaEmissao->exists) {
                $formas = array_column($request->pagamentos, 'forma');
                $temCartao = (bool) array_intersect($formas, ['cartao_credito', 'cartao_debito']);

                $deveEmitirNfce =
                    ($configLojaEmissao->cupom_automatico_cartao && $temCartao)
                    || ($configLojaEmissao->cpf_emite_fiscal && ($request->cliente_id || $request->cpf_cnpj_nota))
                    || $configLojaEmissao->padrao_impressao === 'cupom_fiscal';
            }

            $nfceErro = null;
            if ($deveEmitirNfce) {
                try {
                    $client = FocusNFeClient::fromConfig($config);
                    $nfceService = new NFCeService($client);
                    $notaFiscal = $nfceService->emitir($venda, $config);
                } catch (\Throwable $e) {
                    // A venda nunca trava por falha fiscal — mas o operador
                    // precisa SABER que saiu recibo e o porquê (era silencioso).
                    $nfceErro = $e->getMessage();
                    Log::error('[PDV] Erro ao emitir NFC-e, gerando apenas recibo.', [
                        'venda_id' => $venda->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            // Cupom/recibo sempre impresso. Se houver NFC-e, vai com dados fiscais.
            $cupomHtml = view('app.pdv.cupom-nao-fiscal', compact('venda', 'notaFiscal'))->render();

            return response()->json([
                'success'     => true,
                'venda'       => $venda,
                'cupom'       => $cupomHtml,
                'nota_fiscal' => $notaFiscal,
                'tipo_cupom'  => ($deveEmitirNfce && $notaFiscal) ? 'fiscal' : 'nao_fiscal',
                'nfce_erro'   => $nfceErro,
                'vale'        => $valeUsado ? [
                    'codigo'          => $valeUsado['vale_codigo'] ?? null,
                    'valor_usado'     => (float) ($valeUsado['valor'] ?? 0),
                    'saldo_restante'  => $valeSobraDevolvida > 0 ? 0.0 : (float) ($valeUsado['vale_saldo_restante'] ?? 0),
                    'sobra_devolvida' => $valeSobraDevolvida,
                ] : null,
            ]);

        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[PDV] Erro ao registrar venda.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erro ao registrar venda: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Trocas (F6) e vales — 03/09/2026                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Vendas candidatas à troca: por número, por id bipado do cupom ("V158")
     * ou por nome do cliente. Qualquer loja da EMPRESA — o cliente pode ter
     * comprado em outra unidade — por isso o UnidadeScope sai daqui.
     */
    public function trocaBuscarVendas(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $empresaId = (int) session('empresa_id');
        $unidadeSessao = (int) session('unidade_id');

        $query = Venda::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $empresaId)
            ->where('status', StatusVenda::Concluida)
            ->with(['cliente:id,nome_razao_social', 'unidade:id,nome'])
            ->withCount('itens');

        if ($q !== '') {
            if (preg_match('/^V(\d+)$/i', $q, $m)) {
                $query->where('id', (int) $m[1]);
            } elseif (ctype_digit($q)) {
                $query->where(fn ($w) => $w->where('numero', (int) $q)->orWhere('id', (int) $q));
            } else {
                $query->whereHas('cliente', fn ($c) => $c->where('nome_razao_social', 'like', "%{$q}%"));
            }
        }

        $vendas = $query->orderByDesc('created_at')->limit(15)->get();

        return response()->json($vendas->map(fn ($v) => [
            'id'         => $v->id,
            'numero'     => $v->numero,
            'data'       => $v->created_at->format('d/m/Y H:i'),
            'total'      => (float) $v->total,
            'cliente'    => $v->cliente->nome_razao_social ?? null,
            'loja'       => $v->unidade->nome ?? null,
            'mesma_loja' => (int) $v->unidade_id === $unidadeSessao,
            'itens'      => (int) $v->itens_count,
        ]));
    }

    public function trocaVenda(int $venda, TrocaService $trocas)
    {
        $v = Venda::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', session('empresa_id'))
            ->findOrFail($venda);

        return response()->json($trocas->situacao($v, ConfiguracaoLoja::daUnidade(), auth()->user()));
    }

    public function trocaRegistrar(Request $request, TrocaService $trocas)
    {
        $request->validate([
            'venda_id'                 => 'required|integer',
            'tipo'                     => 'required|in:troca,devolucao',
            'itens'                    => 'required|array|min:1',
            'itens.*.venda_item_id'    => 'required|integer',
            'itens.*.quantidade'       => 'required|numeric|min:0.001',
            'itens.*.retorna_estoque'  => 'nullable|boolean',
            'itens.*.estoque_id'       => 'nullable|integer',
            'motivo'                   => 'required|string|max:40',
            'motivo_texto'             => 'nullable|string|max:500',
            'sobra_destino'            => 'nullable|in:vale,dinheiro',
            'gerente_email'            => 'nullable|string|max:255',
            'gerente_senha'            => 'nullable|string|max:255',
            'observacoes'              => 'nullable|string|max:1000',
        ]);

        $venda = Venda::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', session('empresa_id'))
            ->findOrFail((int) $request->venda_id);

        try {
            $devolucao = $trocas->registrar(
                $venda,
                $request->all(),
                auth()->user(),
                ConfiguracaoLoja::daUnidade(),
                (int) session('unidade_id'),
                session('caixa_id') ? (int) session('caixa_id') : null
            );
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[PDV] Erro ao registrar troca.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Erro ao registrar a troca: ' . $e->getMessage()], 500);
        }

        $comprovante = view('app.trocas.comprovante', ['devolucao' => $devolucao, 'autoPrint' => false])->render();

        return response()->json([
            'success'   => true,
            'devolucao' => [
                'id'                     => $devolucao->id,
                'tipo'                   => $devolucao->tipo,
                'venda_numero'           => $venda->numero,
                'valor_estornado'        => (float) $devolucao->valor_estornado,
                'valor_abatido_parcelas' => (float) $devolucao->valor_abatido_parcelas,
                'forma_sobra'            => $devolucao->forma_sobra,
                'valor_sobra'            => (float) $devolucao->valor_sobra,
            ],
            'vale' => $devolucao->vale ? [
                'id'       => $devolucao->vale->id,
                'codigo'   => $devolucao->vale->codigo,
                'saldo'    => (float) $devolucao->vale->saldo,
                'validade' => $devolucao->vale->validade?->format('d/m/Y'),
            ] : null,
            'comprovante' => $comprovante,
        ]);
    }

    /** Consulta de vale pelo código digitado/bipado no botão Vale do PDV. */
    public function valeConsultar(string $codigo)
    {
        $vale = Vale::withoutGlobalScopes()
            ->where('empresa_id', session('empresa_id'))
            ->where('codigo', Vale::normalizarCodigo($codigo))
            ->with('cliente:id,nome_razao_social')
            ->first();

        if (! $vale) {
            return response()->json(['error' => 'Vale não encontrado nesta empresa. Confira o código impresso no comprovante.'], 404);
        }

        if ($motivo = $vale->motivoIndisponivel()) {
            return response()->json(['error' => $motivo, 'codigo' => $vale->codigo], 422);
        }

        return response()->json([
            'codigo'   => $vale->codigo,
            'valor'    => (float) $vale->valor,
            'saldo'    => (float) $vale->saldo,
            'validade' => $vale->validade?->format('d/m/Y'),
            'cliente'  => $vale->cliente->nome_razao_social ?? null,
        ]);
    }
}
