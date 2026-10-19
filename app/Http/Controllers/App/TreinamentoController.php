<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Admin\TreinamentoController as AdminTreinamentoController;
use App\Http\Controllers\Controller;
use App\Models\Treinamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Aba Treinamento da empresa (19/10/2026) — o único lugar onde os vídeos
 * aparecem. Cada usuário vê os vídeos do seu perfil (dono e gerente veem
 * todos) e o próprio selo de assistido/pendente; quem acompanha a equipe é
 * o admin IA365, na aba Treinamento da empresa.
 *
 * O vendedor em modo "só PDV" também entra aqui, pelo link no topo do PDV.
 * O filtro por perfil está em `Treinamento::visiveisPara()`, usado também
 * em `video` e `concluir`.
 */
class TreinamentoController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $treinamentos = Treinamento::visiveisPara($user)->get();
        $concluidos = $this->concluidos($user);

        return view('app.treinamento.index', compact('treinamentos', 'concluidos'));
    }

    public function video(Request $request, Treinamento $treinamento): BinaryFileResponse
    {
        $this->garantirVisivel($request, $treinamento);

        return AdminTreinamentoController::responderVideo($treinamento);
    }

    /**
     * Marca como assistido — chamado quando o vídeo chega ao fim ou quando o
     * usuário clica no botão. Vale a primeira data: assistir de novo não muda nada.
     */
    public function concluir(Request $request, Treinamento $treinamento): JsonResponse
    {
        $this->garantirVisivel($request, $treinamento);
        $user = $request->user();

        if (! $user->treinamentosConcluidos()->whereKey($treinamento->id)->exists()) {
            $user->treinamentosConcluidos()->attach($treinamento->id, ['concluido_em' => now()]);
        }

        return response()->json(['concluido_em' => $this->concluidos($user)[$treinamento->id]]);
    }

    private function garantirVisivel(Request $request, Treinamento $treinamento): void
    {
        abort_unless(
            Treinamento::visiveisPara($request->user())->whereKey($treinamento->id)->exists(),
            404
        );
    }

    /** [treinamento_id => 'd/m/Y H:i'] do que este usuário já assistiu. */
    private function concluidos($user): array
    {
        return $user->treinamentosConcluidos()->get()
            ->mapWithKeys(fn (Treinamento $t) => [
                $t->id => Carbon::parse($t->pivot->concluido_em)->format('d/m/Y H:i'),
            ])->all();
    }
}
