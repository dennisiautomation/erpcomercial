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
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\ConfiguracaoFiscal;
use App\Services\EstoqueMultiUnidadeService;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFCeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        // Vendedores selecionáveis no PDV: qualquer funcionário ativo da empresa
        // (não só perfis caixa/vendedor — dono, gerente, admin do cliente também vendem).
        // Exclui admin de plataforma (is_admin).
        $operadores = User::where('empresa_id', session('empresa_id'))
            ->where('status', 'ativo')
            ->where('is_admin', false)
            ->select('id', 'name', 'perfil')
            ->orderBy('name')
            ->get();

        return view('app.pdv.index', compact('caixa', 'unidade', 'configFiscal', 'operadores'));
    }

    public function verificarEstoque(Request $request, $produtoId, EstoqueMultiUnidadeService $estoqueSvc)
    {
        $produto = Produto::find($produtoId);
        $empresa = $request->user()->empresa;
        $unidadeAtual = (int) session('unidade_id');

        $estoque = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produtoId)
            ->where('unidade_id', $unidadeAtual)
            ->orderByDesc('id')
            ->first();

        $saldoAtual = $estoque ? (float) $estoque->quantidade_posterior : 0;

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

    public function buscarProduto(Request $request, $codigo)
    {
        $termo = trim($codigo);

        $produtos = Produto::where('empresa_id', session('empresa_id'))
            ->where('status', 'ativo')
            ->where(function ($q) use ($termo) {
                // Busca ampla: nome, código interno, SKU e código de barras
                $q->where('descricao', 'like', "%{$termo}%")
                  ->orWhere('descricao_detalhada', 'like', "%{$termo}%")
                  ->orWhere('codigo_interno', 'like', "%{$termo}%")
                  ->orWhere('sku', 'like', "%{$termo}%")
                  ->orWhere('codigo_barras', 'like', "%{$termo}%");
            })
            // Match exato (leitura de scanner / código digitado) vem primeiro,
            // garantindo auto-add quando o termo bate certinho num código.
            ->orderByRaw(
                'CASE WHEN codigo_barras = ? OR codigo_interno = ? OR sku = ? THEN 0 ELSE 1 END',
                [$termo, $termo, $termo]
            )
            ->orderBy('descricao')
            ->select('id', 'codigo_interno', 'codigo_barras', 'sku', 'descricao', 'preco_venda', 'unidade_medida')
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
            'itens.*.unidade_origem_id'=> ['nullable', 'integer', Rule::exists('unidades', 'id')->where('empresa_id', session('empresa_id'))],
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
                    ->lockForUpdate()
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

                // Pagamento precisa cobrir o total da venda. O PDV finaliza tudo como
                // pago à vista (ContaReceber 'paga' + valor cheio no caixa); aceitar um
                // valor menor gera furo de caixa silencioso (R$ esperado ≠ R$ recebido).
                if (round($totalPago, 2) < round($total, 2)) {
                    $falta = round($total - $totalPago, 2);
                    throw new \DomainException(
                        'Pagamento insuficiente: o total da venda é R$ ' . number_format($total, 2, ',', '.')
                        . ' e foi informado apenas R$ ' . number_format($totalPago, 2, ',', '.')
                        . ' (faltam R$ ' . number_format($falta, 2, ',', '.') . ').'
                    );
                }

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
                        // Venda local — baixa direto na unidade ativa
                        $estoqueAnterior = EstoqueMovimentacao::withoutGlobalScopes()
                            ->where('produto_id', $produtoId)
                            ->where('unidade_id', $unidadeId)
                            ->lockForUpdate()
                            ->latest()
                            ->value('quantidade_posterior') ?? 0;

                        EstoqueMovimentacao::create([
                            'empresa_id'           => $empresaId,
                            'unidade_id'           => $unidadeId,
                            'produto_id'           => $produtoId,
                            'tipo'                 => TipoMovimentacaoEstoque::Saida,
                            'quantidade'           => $qtd,
                            'quantidade_anterior'  => $estoqueAnterior,
                            'quantidade_posterior' => $estoqueAnterior - $qtd,
                            'custo_unitario'       => $itemData['preco_unitario'],
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

                // Comissão do vendedor — usa o percentual configurado no cadastro do
                // funcionário (User.comissao_percentual). Sem percentual definido, NÃO
                // gera comissão (nada de percentual fixo "mágico").
                $vendedorId = $request->vendedor_id ?? auth()->id();
                $vendedor = $vendedorId ? User::find($vendedorId) : null;
                $percentualComissao = (float) ($vendedor->comissao_percentual ?? 0);

                if ($vendedorId && $percentualComissao > 0) {
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

            if ($deveEmitirNfce) {
                try {
                    $client = FocusNFeClient::fromConfig($config);
                    $nfceService = new NFCeService($client);
                    $notaFiscal = $nfceService->emitir($venda, $config);
                } catch (\Throwable $e) {
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
                'tipo_cupom'  => $deveEmitirNfce ? 'fiscal' : 'nao_fiscal',
            ]);

        } catch (\DomainException $e) {
            // Erro de regra de negócio (ex.: pagamento insuficiente) — não é falha técnica.
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
}
