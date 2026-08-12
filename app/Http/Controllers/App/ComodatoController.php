<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusComodato;
use App\Http\Controllers\Controller;
use App\Models\EstoqueComodato;
use App\Models\EstoqueMovimentacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Peças em poder de terceiros — o que saiu em bonificação e deve voltar.
 *
 * A saída já baixou o estoque (movimentação de bonificação). Aqui só se
 * acompanha o retorno; devolver gera a movimentação de entrada de volta.
 */
class ComodatoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $query = EstoqueComodato::with(['produto', 'unidade', 'user'])
            ->where('empresa_id', $empresaId);

        // Padrão da tela: o que interessa é o que ainda está fora
        $situacao = $request->get('situacao', 'em_aberto');

        match ($situacao) {
            'atrasado' => $query->atrasado(),
            'devolvido' => $query->where('status', StatusComodato::Devolvido),
            'perdido' => $query->where('status', StatusComodato::Perdido),
            'todos' => null,
            default => $query->emAberto(),
        };

        if ($request->filled('responsavel')) {
            $query->where('responsavel', 'like', '%' . $request->responsavel . '%');
        }

        if ($request->filled('produto_id')) {
            $query->where('produto_id', $request->produto_id);
        }

        $comodatos = $query->orderBy('data_prevista_retorno')
            ->paginate(20)
            ->withQueryString();

        // Cards do topo — sempre da empresa toda, independem do filtro
        $base = fn () => EstoqueComodato::where('empresa_id', $empresaId);

        $totalEmAberto = (clone $base())->emAberto()->count();
        $totalAtrasado = (clone $base())->atrasado()->count();
        $pecasFora = (float) (clone $base())->emAberto()
            ->selectRaw('COALESCE(SUM(quantidade - quantidade_devolvida), 0) as total')
            ->value('total');
        $totalDevolvido = (clone $base())->where('status', StatusComodato::Devolvido)->count();

        return view('app.estoque.comodatos.index', compact(
            'comodatos', 'situacao', 'totalEmAberto', 'totalAtrasado', 'pecasFora', 'totalDevolvido'
        ));
    }

    /**
     * Registra o retorno (total ou parcial) e devolve a quantidade ao estoque.
     */
    public function devolver(Request $request, EstoqueComodato $comodato)
    {
        abort_unless($comodato->empresa_id === auth()->user()->empresa_id, 403);

        if (! $comodato->status->emAberto()) {
            return back()->with('error', 'Este comodato já foi encerrado.');
        }

        $validated = $request->validate([
            'quantidade' => [
                'required', 'numeric', 'min:0.001',
                'max:' . $comodato->quantidade_pendente,
            ],
            'observacoes' => 'nullable|string|max:500',
        ], [
            'quantidade.max' => 'Faltam apenas ' . rtrim(rtrim(number_format($comodato->quantidade_pendente, 3, ',', '.'), '0'), ',') . ' para voltar.',
        ]);

        DB::transaction(function () use ($validated, $comodato) {
            $quantidade = (float) $validated['quantidade'];

            // Volta para a MESMA unidade de onde saiu — não a da sessão, senão
            // a peça reaparece na loja errada.
            $ultima = EstoqueMovimentacao::withoutGlobalScopes()
                ->where('empresa_id', $comodato->empresa_id)
                ->where('unidade_id', $comodato->unidade_id)
                ->where('produto_id', $comodato->produto_id)
                ->orderByDesc('id')
                ->first();

            $estoqueAnterior = $ultima ? (float) $ultima->quantidade_posterior : 0;

            EstoqueMovimentacao::create([
                'empresa_id'           => $comodato->empresa_id,
                'unidade_id'           => $comodato->unidade_id,
                'produto_id'           => $comodato->produto_id,
                'tipo'                 => 'devolucao',
                'quantidade'           => $quantidade,
                'quantidade_anterior'  => $estoqueAnterior,
                'quantidade_posterior' => $estoqueAnterior + $quantidade,
                'origem_tipo'          => 'comodato',
                'origem_id'            => $comodato->id,
                'user_id'              => auth()->id(),
                'observacoes'          => trim('Retorno de ' . $comodato->responsavel . '. ' . ($validated['observacoes'] ?? '')),
            ]);

            $comodato->quantidade_devolvida = (float) $comodato->quantidade_devolvida + $quantidade;
            $comodato->status = $comodato->recalcularStatus();

            if ($comodato->status === StatusComodato::Devolvido) {
                $comodato->data_retorno = now()->toDateString();
            }

            $comodato->save();
        });

        return redirect()->route('app.comodatos.index')
            ->with('success', 'Retorno registrado — a peça voltou para o estoque.');
    }

    /**
     * Marca como não devolvida. O estoque NÃO volta: a peça foi embora de vez,
     * e a baixa da bonificação já refletiu isso.
     */
    public function baixarPerda(Request $request, EstoqueComodato $comodato)
    {
        abort_unless($comodato->empresa_id === auth()->user()->empresa_id, 403);

        if (! $comodato->status->emAberto()) {
            return back()->with('error', 'Este comodato já foi encerrado.');
        }

        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:500',
        ]);

        $comodato->status = StatusComodato::Perdido;
        $comodato->data_retorno = now()->toDateString();
        $comodato->observacoes = trim(($comodato->observacoes ?? '') . ' | Baixado como não devolvido: ' . ($validated['observacoes'] ?? 'sem justificativa'));
        $comodato->save();

        return redirect()->route('app.comodatos.index')
            ->with('success', 'Comodato encerrado como não devolvido.');
    }
}
