<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusOrcamento;
use App\Enums\StatusPedido;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrcamentoController extends Controller
{
    public function index(Request $request)
    {
        $query = Orcamento::where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->with(['cliente:id,nome_razao_social,cpf_cnpj', 'vendedor:id,name', 'itens']);

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                  ->orWhereHas('cliente', fn ($c) => $c->where('nome_razao_social', 'like', "%{$busca}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orcamentos = $query->latest()->paginate(20)->withQueryString();

        return view('app.orcamentos.index', compact('orcamentos'));
    }

    public function create()
    {
        $vendedores = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();

        return view('app.orcamentos.create', compact('vendedores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'              => 'required|exists:clientes,id',
            'vendedor_id'             => 'nullable|exists:users,id',
            'validade_ate'            => 'required|date|after_or_equal:today',
            'desconto_percentual'     => 'nullable|numeric|min:0|max:100',
            'desconto_valor'          => 'nullable|numeric|min:0',
            'observacoes_internas'    => 'nullable|string|max:2000',
            'observacoes_externas'    => 'nullable|string|max:2000',
            'itens'                   => 'required|array|min:1',
            'itens.*.produto_id'      => 'required|exists:produtos,id',
            'itens.*.quantidade'      => 'required|numeric|min:0.001',
            'itens.*.preco_unitario'  => 'required|numeric|min:0',
            'itens.*.desconto_percentual' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request) {
            $empresaId  = session('empresa_id');
            $unidadeId  = session('unidade_id');

            // Auto-generate numero
            $ultimoNumero = Orcamento::where('empresa_id', $empresaId)->max('numero');
            $numero = $ultimoNumero ? $ultimoNumero + 1 : 1;

            $subtotal = 0;
            $itensData = [];

            foreach ($request->itens as $item) {
                $produto = Produto::find($item['produto_id']);
                $descontoPerc = $item['desconto_percentual'] ?? 0;
                $precoUnit = $item['preco_unitario'];
                $qtd = $item['quantidade'];
                $descontoValor = round($precoUnit * $qtd * ($descontoPerc / 100), 2);
                $totalItem = round(($precoUnit * $qtd) - $descontoValor, 2);

                $itensData[] = [
                    'produto_id'         => $item['produto_id'],
                    'descricao'          => $produto->descricao,
                    'quantidade'         => $qtd,
                    'preco_unitario'     => $precoUnit,
                    'desconto_percentual'=> $descontoPerc,
                    'desconto_valor'     => $descontoValor,
                    'total'              => $totalItem,
                ];

                $subtotal += $totalItem;
            }

            $descontoGeralPerc  = $request->desconto_percentual ?? 0;
            $descontoGeralValor = $request->desconto_valor ?? round($subtotal * ($descontoGeralPerc / 100), 2);
            $total = round($subtotal - $descontoGeralValor, 2);

            $orcamento = Orcamento::create([
                'empresa_id'          => $empresaId,
                'unidade_id'          => $unidadeId,
                'cliente_id'          => $request->cliente_id,
                'vendedor_id'         => $request->vendedor_id,
                'numero'              => $numero,
                'validade_ate'        => $request->validade_ate,
                'subtotal'            => $subtotal,
                'desconto_percentual' => $descontoGeralPerc,
                'desconto_valor'      => $descontoGeralValor,
                'total'               => $total,
                'status'              => StatusOrcamento::EmAberto,
                'observacoes_internas'=> $request->observacoes_internas,
                'observacoes_externas'=> $request->observacoes_externas,
            ]);

            foreach ($itensData as $itemData) {
                $orcamento->itens()->create($itemData);
            }
        });

        return redirect()->route('app.orcamentos.index')
            ->with('success', 'Orcamento criado com sucesso!');
    }

    public function show(Orcamento $orcamento)
    {
        $orcamento->load(['cliente', 'vendedor', 'itens.produto', 'pedido']);

        return view('app.orcamentos.show', compact('orcamento'));
    }

    public function edit(Orcamento $orcamento)
    {
        $orcamento->load(['cliente', 'itens.produto']);
        $vendedores = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();

        return view('app.orcamentos.edit', compact('orcamento', 'vendedores'));
    }

    public function update(Request $request, Orcamento $orcamento)
    {
        $request->validate([
            'cliente_id'              => 'required|exists:clientes,id',
            'vendedor_id'             => 'nullable|exists:users,id',
            'validade_ate'            => 'required|date',
            'desconto_percentual'     => 'nullable|numeric|min:0|max:100',
            'desconto_valor'          => 'nullable|numeric|min:0',
            'observacoes_internas'    => 'nullable|string|max:2000',
            'observacoes_externas'    => 'nullable|string|max:2000',
            'itens'                   => 'required|array|min:1',
            'itens.*.produto_id'      => 'required|exists:produtos,id',
            'itens.*.quantidade'      => 'required|numeric|min:0.001',
            'itens.*.preco_unitario'  => 'required|numeric|min:0',
            'itens.*.desconto_percentual' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request, $orcamento) {
            $subtotal = 0;
            $itensData = [];

            foreach ($request->itens as $item) {
                $produto = Produto::find($item['produto_id']);
                $descontoPerc = $item['desconto_percentual'] ?? 0;
                $precoUnit = $item['preco_unitario'];
                $qtd = $item['quantidade'];
                $descontoValor = round($precoUnit * $qtd * ($descontoPerc / 100), 2);
                $totalItem = round(($precoUnit * $qtd) - $descontoValor, 2);

                $itensData[] = [
                    'produto_id'         => $item['produto_id'],
                    'descricao'          => $produto->descricao,
                    'quantidade'         => $qtd,
                    'preco_unitario'     => $precoUnit,
                    'desconto_percentual'=> $descontoPerc,
                    'desconto_valor'     => $descontoValor,
                    'total'              => $totalItem,
                ];

                $subtotal += $totalItem;
            }

            $descontoGeralPerc  = $request->desconto_percentual ?? 0;
            $descontoGeralValor = $request->desconto_valor ?? round($subtotal * ($descontoGeralPerc / 100), 2);
            $total = round($subtotal - $descontoGeralValor, 2);

            $orcamento->update([
                'cliente_id'          => $request->cliente_id,
                'vendedor_id'         => $request->vendedor_id,
                'validade_ate'        => $request->validade_ate,
                'subtotal'            => $subtotal,
                'desconto_percentual' => $descontoGeralPerc,
                'desconto_valor'      => $descontoGeralValor,
                'total'               => $total,
                'observacoes_internas'=> $request->observacoes_internas,
                'observacoes_externas'=> $request->observacoes_externas,
            ]);

            // Remove old items and recreate
            $orcamento->itens()->delete();
            foreach ($itensData as $itemData) {
                $orcamento->itens()->create($itemData);
            }
        });

        return redirect()->route('app.orcamentos.show', $orcamento)
            ->with('success', 'Orcamento atualizado com sucesso!');
    }

    public function converter(Orcamento $orcamento)
    {
        if ($orcamento->status === StatusOrcamento::Convertido) {
            return back()->with('error', 'Este orcamento ja foi convertido em pedido.');
        }

        DB::transaction(function () use ($orcamento) {
            $ultimoNumero = Pedido::where('empresa_id', $orcamento->empresa_id)->max('numero');
            $numero = $ultimoNumero ? $ultimoNumero + 1 : 1;

            $pedido = Pedido::create([
                'empresa_id'          => $orcamento->empresa_id,
                'unidade_id'          => $orcamento->unidade_id,
                'cliente_id'          => $orcamento->cliente_id,
                'vendedor_id'         => $orcamento->vendedor_id,
                'orcamento_id'        => $orcamento->id,
                'numero'              => $numero,
                'subtotal'            => $orcamento->subtotal,
                'desconto_percentual' => $orcamento->desconto_percentual,
                'desconto_valor'      => $orcamento->desconto_valor,
                'total'               => $orcamento->total,
                'status'              => StatusPedido::Rascunho,
                'observacoes_internas'=> $orcamento->observacoes_internas,
                'observacoes_externas'=> $orcamento->observacoes_externas,
            ]);

            foreach ($orcamento->itens as $item) {
                PedidoItem::create([
                    'pedido_id'          => $pedido->id,
                    'produto_id'         => $item->produto_id,
                    'servico_id'         => $item->servico_id,
                    'descricao'          => $item->descricao,
                    'quantidade'         => $item->quantidade,
                    'preco_unitario'     => $item->preco_unitario,
                    'desconto_percentual'=> $item->desconto_percentual,
                    'desconto_valor'     => $item->desconto_valor,
                    'total'              => $item->total,
                ]);
            }

            $orcamento->update(['status' => StatusOrcamento::Convertido]);
        });

        return redirect()->route('app.pedidos.show', $orcamento->pedido)
            ->with('success', 'Orcamento convertido em pedido com sucesso!');
    }

    public function destroy(Orcamento $orcamento)
    {
        $orcamento->delete();

        return redirect()->route('app.orcamentos.index')
            ->with('success', 'Orcamento excluido com sucesso!');
    }
}
