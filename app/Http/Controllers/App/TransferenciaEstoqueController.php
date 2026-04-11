<?php

namespace App\Http\Controllers\App;

use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\EstoqueMovimentacao;
use App\Models\Produto;
use App\Models\TransferenciaEstoque;
use App\Models\TransferenciaEstoqueItem;
use App\Models\Unidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferenciaEstoqueController extends Controller
{
    public function index(Request $request)
    {
        $query = TransferenciaEstoque::with(['unidadeOrigem', 'unidadeDestino', 'solicitante'])
            ->withCount('itens')
            ->where('empresa_id', auth()->user()->empresa_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $transferencias = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('app.transferencias.index', compact('transferencias'));
    }

    public function create()
    {
        $unidades = Unidade::where('empresa_id', auth()->user()->empresa_id)
            ->where('id', '!=', session('unidade_id'))
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $produtos = Produto::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativo')
            ->orderBy('descricao')
            ->get(['id', 'descricao']);

        return view('app.transferencias.create', compact('unidades', 'produtos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unidade_destino_id' => 'required|exists:unidades,id',
            'observacoes'        => 'nullable|string|max:500',
            'itens'              => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($validated) {
            $transferencia = TransferenciaEstoque::create([
                'empresa_id'          => auth()->user()->empresa_id,
                'unidade_origem_id'   => session('unidade_id'),
                'unidade_destino_id'  => $validated['unidade_destino_id'],
                'user_solicitante_id' => auth()->id(),
                'status'              => 'solicitada',
                'observacoes'         => $validated['observacoes'] ?? null,
            ]);

            foreach ($validated['itens'] as $item) {
                TransferenciaEstoqueItem::create([
                    'transferencia_estoque_id' => $transferencia->id,
                    'produto_id'               => $item['produto_id'],
                    'quantidade'               => $item['quantidade'],
                ]);
            }
        });

        return redirect()->route('app.transferencias.index')
            ->with('success', 'Transferencia solicitada com sucesso!');
    }

    public function show(TransferenciaEstoque $transferencia)
    {
        $transferencia->load([
            'unidadeOrigem',
            'unidadeDestino',
            'solicitante',
            'aprovador',
            'itens.produto',
        ]);

        return view('app.transferencias.show', compact('transferencia'));
    }

    public function aprovar(TransferenciaEstoque $transferencia)
    {
        if ($transferencia->status !== 'solicitada') {
            return back()->with('error', 'Esta transferencia nao pode ser aprovada.');
        }

        DB::transaction(function () use ($transferencia) {
            $transferencia->load('itens.produto');

            foreach ($transferencia->itens as $item) {
                $produto = Produto::lockForUpdate()->findOrFail($item->produto_id);

                // Saida na origem
                $ultimaOrigem = EstoqueMovimentacao::where('produto_id', $produto->id)
                    ->where('unidade_id', $transferencia->unidade_origem_id)
                    ->orderByDesc('id')
                    ->first();

                $estoqueAnteriorOrigem = $ultimaOrigem ? (float) $ultimaOrigem->quantidade_posterior : 0;
                $estoquePosteriorOrigem = $estoqueAnteriorOrigem - (float) $item->quantidade;

                EstoqueMovimentacao::create([
                    'empresa_id'          => $transferencia->empresa_id,
                    'unidade_id'          => $transferencia->unidade_origem_id,
                    'produto_id'          => $produto->id,
                    'tipo'                => TipoMovimentacaoEstoque::Transferencia->value,
                    'quantidade'          => $item->quantidade,
                    'quantidade_anterior' => $estoqueAnteriorOrigem,
                    'quantidade_posterior' => $estoquePosteriorOrigem,
                    'custo_unitario'      => $produto->preco_custo ?? 0,
                    'origem_tipo'         => TransferenciaEstoque::class,
                    'origem_id'           => $transferencia->id,
                    'user_id'             => auth()->id(),
                    'observacoes'         => 'Saida por transferencia #' . $transferencia->id,
                ]);

                // Entrada no destino
                $ultimaDestino = EstoqueMovimentacao::where('produto_id', $produto->id)
                    ->where('unidade_id', $transferencia->unidade_destino_id)
                    ->orderByDesc('id')
                    ->first();

                $estoqueAnteriorDestino = $ultimaDestino ? (float) $ultimaDestino->quantidade_posterior : 0;
                $estoquePosteriorDestino = $estoqueAnteriorDestino + (float) $item->quantidade;

                EstoqueMovimentacao::create([
                    'empresa_id'          => $transferencia->empresa_id,
                    'unidade_id'          => $transferencia->unidade_destino_id,
                    'produto_id'          => $produto->id,
                    'tipo'                => TipoMovimentacaoEstoque::Transferencia->value,
                    'quantidade'          => $item->quantidade,
                    'quantidade_anterior' => $estoqueAnteriorDestino,
                    'quantidade_posterior' => $estoquePosteriorDestino,
                    'custo_unitario'      => $produto->preco_custo ?? 0,
                    'origem_tipo'         => TransferenciaEstoque::class,
                    'origem_id'           => $transferencia->id,
                    'user_id'             => auth()->id(),
                    'observacoes'         => 'Entrada por transferencia #' . $transferencia->id,
                ]);
            }

            $transferencia->update([
                'status'           => 'aprovada',
                'user_aprovador_id' => auth()->id(),
            ]);
        });

        return redirect()->route('app.transferencias.show', $transferencia)
            ->with('success', 'Transferencia aprovada com sucesso!');
    }

    public function cancelar(TransferenciaEstoque $transferencia)
    {
        if ($transferencia->status !== 'solicitada') {
            return back()->with('error', 'Esta transferencia nao pode ser cancelada.');
        }

        $transferencia->update([
            'status'           => 'cancelada',
            'user_aprovador_id' => auth()->id(),
        ]);

        return redirect()->route('app.transferencias.show', $transferencia)
            ->with('success', 'Transferencia cancelada.');
    }

    public function destroy(TransferenciaEstoque $transferencia)
    {
        if ($transferencia->status !== 'solicitada') {
            return back()->with('error', 'Somente transferencias solicitadas podem ser excluidas.');
        }

        $transferencia->delete();

        return redirect()->route('app.transferencias.index')
            ->with('success', 'Transferencia excluida com sucesso!');
    }
}
