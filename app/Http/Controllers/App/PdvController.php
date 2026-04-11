<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoCaixa;
use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\MovimentacaoCaixa;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\ConfiguracaoFiscal;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFCeService;
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

        return view('app.pdv.index', compact('caixa', 'unidade', 'configFiscal'));
    }

    public function buscarProduto(Request $request, $codigo)
    {
        $produtos = Produto::where('empresa_id', session('empresa_id'))
            ->where('status', 'ativo')
            ->where(function ($q) use ($codigo) {
                $q->where('codigo_barras', $codigo)
                  ->orWhere('codigo_interno', $codigo)
                  ->orWhere('descricao', 'like', "%{$codigo}%");
            })
            ->select('id', 'codigo_interno', 'codigo_barras', 'descricao', 'preco_venda', 'unidade_medida')
            ->limit(20)
            ->get();

        return response()->json($produtos);
    }

    public function buscarCliente(Request $request, $termo)
    {
        $clientes = Cliente::where('empresa_id', session('empresa_id'))
            ->where(function ($q) use ($termo) {
                $q->where('nome_razao_social', 'like', "%{$termo}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$termo}%");
            })
            ->select('id', 'nome_razao_social', 'cpf_cnpj')
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
            'pagamentos'               => 'required|array|min:1',
            'pagamentos.*.forma'       => 'required|string',
            'pagamentos.*.valor'       => 'required|numeric|min:0.01',
            'cliente_id'               => 'nullable|exists:clientes,id',
            'desconto_valor'           => 'nullable|numeric|min:0',
            'desconto_percentual'      => 'nullable|numeric|min:0|max:100',
            'vendedor_id'              => 'nullable|exists:users,id',
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

                $subtotal = 0;
                $itensData = [];

                foreach ($request->itens as $item) {
                    $produto = Produto::find($item['produto_id']);
                    if (!$produto) continue;

                    $precoUnit = $item['preco_unitario'];
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

                // Create VendaItens
                foreach ($itensData as $itemData) {
                    $venda->itens()->create($itemData);
                }

                // Deduct estoque
                foreach ($request->itens as $item) {
                    if (!empty($item['produto_id'])) {
                        $produto = Produto::find($item['produto_id']);
                        if (!$produto) continue;

                        $estoqueAnterior = EstoqueMovimentacao::withoutGlobalScopes()
                            ->where('produto_id', $item['produto_id'])
                            ->where('unidade_id', $unidadeId)
                            ->latest()
                            ->value('quantidade_posterior') ?? 0;

                        EstoqueMovimentacao::create([
                            'empresa_id'           => $empresaId,
                            'unidade_id'           => $unidadeId,
                            'produto_id'           => $item['produto_id'],
                            'tipo'                 => TipoMovimentacaoEstoque::Saida,
                            'quantidade'           => $item['quantidade'],
                            'quantidade_anterior'  => $estoqueAnterior,
                            'quantidade_posterior'  => $estoqueAnterior - $item['quantidade'],
                            'custo_unitario'       => $item['preco_unitario'],
                            'origem_tipo'          => Venda::class,
                            'origem_id'            => $venda->id,
                            'user_id'              => auth()->id(),
                            'observacoes'          => "Venda PDV #{$venda->numero}",
                        ]);
                    }
                }

                // Create MovimentacaoCaixa (venda)
                MovimentacaoCaixa::create([
                    'empresa_id'  => $empresaId,
                    'unidade_id'  => $unidadeId,
                    'caixa_id'    => $caixa->id,
                    'tipo'        => TipoMovimentacaoCaixa::Venda,
                    'valor'       => $total,
                    'descricao'   => "Venda #{$venda->numero}",
                    'user_id'     => auth()->id(),
                ]);

                // Create ContaReceber entries
                foreach ($pagamentos as $pgto) {
                    $valorPgto = min($pgto['valor'], $total);
                    ContaReceber::create([
                        'empresa_id'      => $empresaId,
                        'unidade_id'      => $unidadeId,
                        'cliente_id'      => $request->cliente_id,
                        'venda_id'        => $venda->id,
                        'descricao'       => "Venda PDV #{$venda->numero} - " . ucfirst($pgto['forma']),
                        'valor'           => $valorPgto,
                        'valor_pago'      => $valorPgto,
                        'vencimento'      => now(),
                        'pago_em'         => now(),
                        'forma_pagamento' => $pgto['forma'],
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

            if ($config && $config->emissao_fiscal_ativa && $config->tipo_cupom_pdv === 'fiscal') {
                try {
                    $client = FocusNFeClient::fromConfig($config);
                    $nfceService = new NFCeService($client);
                    $notaFiscal = $nfceService->emitir($venda, $config);
                    $cupomHtml = view('app.pdv.cupom-nao-fiscal', compact('venda', 'notaFiscal'))->render();
                } catch (\Throwable $e) {
                    Log::error('[PDV] Erro ao emitir NFC-e, gerando cupom nao fiscal.', [
                        'venda_id' => $venda->id,
                        'error'    => $e->getMessage(),
                    ]);
                    $cupomHtml = view('app.pdv.cupom-nao-fiscal', compact('venda'))->render();
                }
            } else {
                $cupomHtml = view('app.pdv.cupom-nao-fiscal', compact('venda'))->render();
            }

            return response()->json([
                'success'     => true,
                'venda'       => $venda,
                'cupom'       => $cupomHtml,
                'nota_fiscal' => $notaFiscal,
                'tipo_cupom'  => $config?->tipo_cupom_pdv ?? 'nao_fiscal',
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
