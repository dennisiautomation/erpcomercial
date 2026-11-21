<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AjudaConversa;
use App\Models\AjudaMensagem;
use App\Services\Ajuda\AgenteAjuda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Botão "Ajuda" (todas as telas): conversa com o agente de ajuda.
 * Cada usuário só vê as PRÓPRIAS conversas (filtro por user_id em toda consulta).
 */
class AjudaController extends Controller
{
    public function __construct()
    {
        abort_unless(config('ajuda.ativo'), 404);
    }

    /** Conversa em andamento (a última, se mexida nas últimas 12 h). */
    public function atual(Request $request): JsonResponse
    {
        $conversa = AjudaConversa::query()
            ->where('user_id', $request->user()->id)
            ->where('updated_at', '>=', now()->subHours(12))
            ->latest('updated_at')->first();

        return response()->json($this->formatar($conversa));
    }

    public function nova(Request $request): JsonResponse
    {
        return response()->json($this->formatar(null));
    }

    public function mensagem(Request $request, AgenteAjuda $agente): JsonResponse
    {
        $dados = $request->validate([
            'texto' => ['required', 'string', 'max:2000'],
            'conversa_id' => ['nullable', 'integer'],
            'pagina' => ['nullable', 'string', 'max:300'],
        ]);
        $user = $request->user();

        $max = (int) config('ajuda.max_mensagens_hora_usuario', 60);
        $usadas = AjudaMensagem::query()->where('papel', 'user')->where('created_at', '>=', now()->subHour())
            ->whereIn('conversa_id', AjudaConversa::query()->select('id')->where('user_id', $user->id))->count();
        if ($usadas >= $max) {
            return response()->json(['erro' => 'Você mandou muitas perguntas na última hora. Espere alguns minutos e tente de novo.'], 429);
        }

        $conversa = null;
        if (! empty($dados['conversa_id'])) {
            $conversa = AjudaConversa::query()->where('user_id', $user->id)->find($dados['conversa_id']);
        }
        $conversa ??= AjudaConversa::create([
            'empresa_id' => $user->empresa_id,
            'unidade_id' => session('unidade_id'),
            'user_id' => $user->id,
        ]);

        $pagina = $this->paginaLegivel($dados['pagina'] ?? null);
        $r = $agente->responder($user, $conversa, $dados['texto'], $pagina);

        return response()->json([
            'conversa_id' => $conversa->id,
            'resposta' => $r['resposta'],
            'protocolo' => $r['protocolo'] ?? $conversa->fresh()->chamado_protocolo,
        ]);
    }

    public function avaliar(Request $request, int $conversa): JsonResponse
    {
        $request->validate(['util' => ['required', 'boolean']]);
        $c = AjudaConversa::query()->where('user_id', $request->user()->id)->findOrFail($conversa);
        $c->forceFill(['avaliacao' => $request->boolean('util') ? 1 : 0])->save();

        return response()->json(['ok' => true]);
    }

    private function formatar(?AjudaConversa $c): array
    {
        return [
            'conversa_id' => $c?->id,
            'protocolo' => $c?->chamado_protocolo,
            'mensagens' => $c ? $c->mensagens()->whereIn('papel', ['user', 'assistant'])
                ->whereNotNull('conteudo')->where('conteudo', '!=', '')
                ->whereNull('ferramentas')
                ->orderBy('id')->get(['papel', 'conteudo'])
                ->map(fn ($m) => ['papel' => $m->papel, 'texto' => $m->conteudo])->all() : [],
        ];
    }

    /** "/app/notas-fiscais/12" + título da aba → texto que a IA entende. */
    private function paginaLegivel(?string $p): ?string
    {
        if (! $p) {
            return null;
        }
        $p = strip_tags($p);

        return mb_substr(trim($p), 0, 250);
    }
}
