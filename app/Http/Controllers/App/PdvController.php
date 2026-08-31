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
use App\Models\ConfiguracaoFiscal;
use App\Models\ConfiguracaoLoja;
use App\Services\EstoqueMultiUnidadeService;
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

        $configLoja = ConfiguracaoLoja::daUnidade();

        return view('app.pdv.index', compact('caixa', 'unidade', 'configFiscal', 'operadores', 'configLoja'));
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
            'cliente_id'               => 'nullable|exists:clientes,id',
            'cpf_cnpj_nota'            => 'nullable|string|max:18',
            'desconto_valor'           => 'nullable|numeric|min:0',
            'desconto_percentual'      => 'nullable|numeric|min:0|max:100',
            'vendedor_id'              => 'nullable|exists:users,id',
            'tabela_precos'            => 'nullable|boolean',
            'documento'                => 'nullable|in:recibo,cupom_fiscal',
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

                // Vendedor responsável pela entrada no caixa (Configurações da Loja)
                $configLojaOp = $configLoja ?? ConfiguracaoLoja::daUnidade($empresaId, $unidadeId);
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
            ]);

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
}
