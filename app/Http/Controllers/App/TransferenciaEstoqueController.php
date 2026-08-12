<?php

namespace App\Http\Controllers\App;

use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Services\SaldoEstoque;
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
        $empresaId = auth()->user()->empresa_id;

        $query = TransferenciaEstoque::with(['unidadeOrigem', 'unidadeDestino', 'solicitante'])
            ->withCount('itens')
            ->where('empresa_id', $empresaId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $transferencias = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Summary counts
        $totalSolicitadas = TransferenciaEstoque::where('empresa_id', $empresaId)
            ->where('status', 'solicitada')->count();
        $totalAprovadas = TransferenciaEstoque::where('empresa_id', $empresaId)
            ->where('status', 'aprovada')->count();
        $totalConcluidas = TransferenciaEstoque::where('empresa_id', $empresaId)
            ->where('status', 'concluida')->count();
        $totalCanceladas = TransferenciaEstoque::where('empresa_id', $empresaId)
            ->where('status', 'cancelada')->count();

        return view('app.estoque.transferencias.index', compact(
            'transferencias', 'totalSolicitadas', 'totalAprovadas', 'totalConcluidas', 'totalCanceladas'
        ));
    }

    public function create()
    {
        $empresaId = auth()->user()->empresa_id;

        // A loja atual ENTRA na lista: com vários estoques, transferir dentro
        // da mesma loja (salão → depósito) passou a ser um caso legítimo.
        $unidades = Unidade::where('empresa_id', $empresaId)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $estoques = Estoque::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->orderBy('unidade_id')
            ->orderByDesc('is_padrao')
            ->orderBy('nome')
            ->get(['id', 'unidade_id', 'nome', 'is_padrao']);

        $produtos = Produto::where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->orderBy('descricao')
            ->get(['id', 'descricao']);

        return view('app.estoque.transferencias.create', compact('unidades', 'estoques', 'produtos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unidade_destino_id' => 'required|exists:unidades,id',
            'estoque_origem_id'  => 'nullable|exists:estoques,id',
            'estoque_destino_id' => 'nullable|exists:estoques,id|different:estoque_origem_id',
            'observacoes'        => 'nullable|string|max:500',
            'itens'              => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|min:0.001',
        ], [
            'estoque_destino_id.different' => 'Origem e destino não podem ser o mesmo estoque.',
        ]);

        $unidadeOrigemId = (int) session('unidade_id');

        $estoqueOrigemId = $this->estoqueValido($validated['estoque_origem_id'] ?? null, $unidadeOrigemId)
            ?? SaldoEstoque::estoqueDeVendaId($unidadeOrigemId);
        $estoqueDestinoId = $this->estoqueValido($validated['estoque_destino_id'] ?? null, (int) $validated['unidade_destino_id'])
            ?? SaldoEstoque::estoqueDeVendaId((int) $validated['unidade_destino_id']);

        if ($estoqueOrigemId === $estoqueDestinoId) {
            return back()->withInput()
                ->with('error', 'Origem e destino são o mesmo estoque — não há o que transferir.');
        }

        DB::transaction(function () use ($validated, $unidadeOrigemId, $estoqueOrigemId, $estoqueDestinoId) {
            $transferencia = TransferenciaEstoque::create([
                'empresa_id'          => auth()->user()->empresa_id,
                'unidade_origem_id'   => $unidadeOrigemId,
                'estoque_origem_id'   => $estoqueOrigemId,
                'unidade_destino_id'  => $validated['unidade_destino_id'],
                'estoque_destino_id'  => $estoqueDestinoId,
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

    /** Só aceita estoque que realmente pertence à loja indicada. */
    private function estoqueValido(?int $estoqueId, int $unidadeId): ?int
    {
        if (! $estoqueId) {
            return null;
        }

        return Estoque::withoutGlobalScopes()
            ->where('id', $estoqueId)
            ->where('unidade_id', $unidadeId)
            ->where('status', 'ativo')
            ->value('id');
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

        return view('app.estoque.transferencias.show', compact('transferencia'));
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

                // Estoque a estoque: se a transferência não veio com estoque
                // escolhido (histórico ou tela antiga), cai no de venda da loja.
                $estoqueOrigemId = $transferencia->estoque_origem_id
                    ?? SaldoEstoque::estoqueDeVendaId($transferencia->unidade_origem_id);
                $estoqueDestinoId = $transferencia->estoque_destino_id
                    ?? SaldoEstoque::estoqueDeVendaId($transferencia->unidade_destino_id);

                // Saida na origem
                SaldoEstoque::registrar(
                    $transferencia->empresa_id,
                    $transferencia->unidade_origem_id,
                    $estoqueOrigemId,
                    $produto->id,
                    TipoMovimentacaoEstoque::Transferencia->value,
                    -(float) $item->quantidade,
                    [
                        'custo_unitario' => $produto->preco_custo ?? 0,
                        'origem_tipo'    => TransferenciaEstoque::class,
                        'origem_id'      => $transferencia->id,
                        'observacoes'    => 'Saida por transferencia #' . $transferencia->id,
                    ]
                );

                // Entrada no destino
                SaldoEstoque::registrar(
                    $transferencia->empresa_id,
                    $transferencia->unidade_destino_id,
                    $estoqueDestinoId,
                    $produto->id,
                    TipoMovimentacaoEstoque::Transferencia->value,
                    (float) $item->quantidade,
                    [
                        'custo_unitario' => $produto->preco_custo ?? 0,
                        'origem_tipo'    => TransferenciaEstoque::class,
                        'origem_id'      => $transferencia->id,
                        'observacoes'    => 'Entrada por transferencia #' . $transferencia->id,
                    ]
                );
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
