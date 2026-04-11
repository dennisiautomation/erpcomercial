<?php

namespace App\Http\Controllers\App;

use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\EstoqueMovimentacao;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstoqueMovimentacaoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $query = EstoqueMovimentacao::with(['produto', 'user', 'unidade'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('produto_id')) {
            $query->where('produto_id', $request->produto_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        $movimentacoes = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $produtos = Produto::where('empresa_id', $empresaId)
            ->orderBy('descricao')
            ->get(['id', 'descricao']);

        // Summary cards
        $totalEntradas = EstoqueMovimentacao::where('empresa_id', $empresaId)
            ->whereIn('tipo', ['entrada', 'devolucao'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalSaidas = EstoqueMovimentacao::where('empresa_id', $empresaId)
            ->whereIn('tipo', ['saida', 'perda'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalAjustes = EstoqueMovimentacao::where('empresa_id', $empresaId)
            ->whereIn('tipo', ['ajuste', 'bonificacao'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalTransferencias = EstoqueMovimentacao::where('empresa_id', $empresaId)
            ->where('tipo', 'transferencia')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('app.estoque.movimentacoes.index', compact(
            'movimentacoes', 'produtos', 'totalEntradas', 'totalSaidas', 'totalAjustes', 'totalTransferencias'
        ));
    }

    public function create()
    {
        $produtos = Produto::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativo')
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'estoque_minimo']);

        return view('app.estoque.movimentacoes.create', compact('produtos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'produto_id'     => 'required|exists:produtos,id',
            'tipo'           => 'required|in:ajuste,perda,bonificacao,entrada',
            'quantidade'     => 'required|numeric|min:0.001',
            'custo_unitario' => 'nullable|numeric|min:0',
            'observacoes'    => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated) {
            $produto = Produto::lockForUpdate()->findOrFail($validated['produto_id']);

            // Calculate current stock from last movimentacao
            $ultimaMovimentacao = EstoqueMovimentacao::where('produto_id', $produto->id)
                ->where('empresa_id', auth()->user()->empresa_id)
                ->orderByDesc('id')
                ->first();

            $estoqueAnterior = $ultimaMovimentacao
                ? (float) $ultimaMovimentacao->quantidade_posterior
                : 0;

            $tipo = TipoMovimentacaoEstoque::from($validated['tipo']);
            $quantidade = (float) $validated['quantidade'];

            // Determine stock change based on type
            $delta = match ($tipo) {
                TipoMovimentacaoEstoque::Entrada, TipoMovimentacaoEstoque::Ajuste => $quantidade,
                TipoMovimentacaoEstoque::Perda, TipoMovimentacaoEstoque::Bonificacao => -$quantidade,
                default => $quantidade,
            };

            $estoquePosterior = $estoqueAnterior + $delta;

            EstoqueMovimentacao::create([
                'empresa_id'          => auth()->user()->empresa_id,
                'unidade_id'          => session('unidade_id'),
                'produto_id'          => $produto->id,
                'tipo'                => $validated['tipo'],
                'quantidade'          => $quantidade,
                'quantidade_anterior' => $estoqueAnterior,
                'quantidade_posterior' => $estoquePosterior,
                'custo_unitario'      => $validated['custo_unitario'] ?? 0,
                'user_id'             => auth()->id(),
                'observacoes'         => $validated['observacoes'] ?? null,
            ]);
        });

        return redirect()->route('app.movimentacoes.index')
            ->with('success', 'Movimentacao registrada com sucesso!');
    }

    public function show(EstoqueMovimentacao $movimentacao)
    {
        $movimentacao->load(['produto', 'user', 'unidade']);

        return view('app.estoque.movimentacoes.show', compact('movimentacao'));
    }
}
