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
use App\Models\Servico;
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

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        $orcamentos = $query->latest()->paginate(20)->withQueryString();

        // Summary stats
        $empresaId = session('empresa_id');
        $unidadeId = session('unidade_id');
        $stats = [
            'total_em_aberto' => Orcamento::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)->where('status', StatusOrcamento::EmAberto)->sum('total'),
            'count_em_aberto' => Orcamento::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)->where('status', StatusOrcamento::EmAberto)->count(),
            'count_aprovados' => Orcamento::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)->where('status', StatusOrcamento::Aprovado)->count(),
            'count_convertidos' => Orcamento::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)->where('status', StatusOrcamento::Convertido)->count(),
        ];

        return view('app.orcamentos.index', compact('orcamentos', 'stats'));
    }

    public function create()
    {
        $vendedores = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();
        $produtos = Produto::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();
        $servicos = Servico::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();

        return view('app.orcamentos.create', compact('vendedores', 'produtos', 'servicos'));
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
            'itens.*.produto_id'      => 'nullable|exists:produtos,id',
            'itens.*.servico_id'      => 'nullable|exists:servicos,id',
            'itens.*.descricao'       => 'nullable|string|max:500',
            'itens.*.quantidade'      => 'required|numeric|min:0.001',
            'itens.*.preco_unitario'  => 'required|numeric|min:0',
            'itens.*.desconto_percentual' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request) {
            $empresaId  = session('empresa_id');
            $unidadeId  = session('unidade_id');

            $ultimoNumero = Orcamento::where('empresa_id', $empresaId)->max('numero');
            $numero = $ultimoNumero ? $ultimoNumero + 1 : 1;

            $subtotal = 0;
            $itensData = [];

            foreach ($request->itens as $item) {
                $descricao = $item['descricao'] ?? '';
                if (!empty($item['produto_id'])) {
                    $produto = Produto::find($item['produto_id']);
                    $descricao = $descricao ?: $produto->descricao;
                } elseif (!empty($item['servico_id'])) {
                    $servico = Servico::find($item['servico_id']);
                    $descricao = $descricao ?: $servico->descricao;
                }

                $descontoPerc = $item['desconto_percentual'] ?? 0;
                $precoUnit = $item['preco_unitario'];
                $qtd = $item['quantidade'];
                $descontoValor = round($precoUnit * $qtd * ($descontoPerc / 100), 2);
                $totalItem = round(($precoUnit * $qtd) - $descontoValor, 2);

                $itensData[] = [
                    'produto_id'         => $item['produto_id'] ?? null,
                    'servico_id'         => $item['servico_id'] ?? null,
                    'descricao'          => $descricao,
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
        $orcamento->load(['cliente', 'vendedor', 'itens.produto', 'itens.servico', 'pedido']);

        return view('app.orcamentos.show', compact('orcamento'));
    }

    public function edit(Orcamento $orcamento)
    {
        if ($orcamento->status === StatusOrcamento::Convertido) {
            return redirect()->route('app.orcamentos.show', $orcamento)
                ->with('error', 'Orcamento convertido nao pode ser editado.');
        }

        $orcamento->load(['cliente', 'itens.produto', 'itens.servico']);
        $vendedores = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();
        $produtos = Produto::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();
        $servicos = Servico::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();

        return view('app.orcamentos.edit', compact('orcamento', 'vendedores', 'produtos', 'servicos'));
    }

    public function update(Request $request, Orcamento $orcamento)
    {
        if ($orcamento->status === StatusOrcamento::Convertido) {
            return back()->with('error', 'Orcamento convertido nao pode ser editado.');
        }

        $request->validate([
            'cliente_id'              => 'required|exists:clientes,id',
            'vendedor_id'             => 'nullable|exists:users,id',
            'validade_ate'            => 'required|date',
            'desconto_percentual'     => 'nullable|numeric|min:0|max:100',
            'desconto_valor'          => 'nullable|numeric|min:0',
            'observacoes_internas'    => 'nullable|string|max:2000',
            'observacoes_externas'    => 'nullable|string|max:2000',
            'itens'                   => 'required|array|min:1',
            'itens.*.produto_id'      => 'nullable|exists:produtos,id',
            'itens.*.servico_id'      => 'nullable|exists:servicos,id',
            'itens.*.descricao'       => 'nullable|string|max:500',
            'itens.*.quantidade'      => 'required|numeric|min:0.001',
            'itens.*.preco_unitario'  => 'required|numeric|min:0',
            'itens.*.desconto_percentual' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request, $orcamento) {
            $subtotal = 0;
            $itensData = [];

            foreach ($request->itens as $item) {
                $descricao = $item['descricao'] ?? '';
                if (!empty($item['produto_id'])) {
                    $produto = Produto::find($item['produto_id']);
                    $descricao = $descricao ?: $produto->descricao;
                } elseif (!empty($item['servico_id'])) {
                    $servico = Servico::find($item['servico_id']);
                    $descricao = $descricao ?: $servico->descricao;
                }

                $descontoPerc = $item['desconto_percentual'] ?? 0;
                $precoUnit = $item['preco_unitario'];
                $qtd = $item['quantidade'];
                $descontoValor = round($precoUnit * $qtd * ($descontoPerc / 100), 2);
                $totalItem = round(($precoUnit * $qtd) - $descontoValor, 2);

                $itensData[] = [
                    'produto_id'         => $item['produto_id'] ?? null,
                    'servico_id'         => $item['servico_id'] ?? null,
                    'descricao'          => $descricao,
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

            $orcamento->itens()->delete();
            foreach ($itensData as $itemData) {
                $orcamento->itens()->create($itemData);
            }
        });

        return redirect()->route('app.orcamentos.show', $orcamento)
            ->with('success', 'Orcamento atualizado com sucesso!');
    }

    public function updateStatus(Request $request, Orcamento $orcamento)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $novoStatus = StatusOrcamento::from($request->status);
        $orcamento->update(['status' => $novoStatus]);

        return back()->with('success', "Status do orcamento atualizado para {$novoStatus->label()}!");
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

        return redirect()->route('app.pedidos.show', $orcamento->fresh()->pedido)
            ->with('success', 'Orcamento convertido em pedido com sucesso!');
    }

    public function destroy(Orcamento $orcamento)
    {
        if ($orcamento->status === StatusOrcamento::Convertido) {
            return back()->with('error', 'Orcamento convertido nao pode ser excluido.');
        }

        $orcamento->itens()->delete();
        $orcamento->delete();

        return redirect()->route('app.orcamentos.index')
            ->with('success', 'Orcamento excluido com sucesso!');
    }
}
