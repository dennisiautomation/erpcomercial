<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Models\PosEvento;
use App\Services\Pos\PosCupomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Log de atividade do app (contrato Stone: 180 dias) e comprovante de caixa. */
class PosEventoController extends Controller
{
    /** POST eventos {eventos:[{tipo, mensagem, dados, ocorrido_em}]} — lote enviado pelo app. */
    public function registrar(Request $request): JsonResponse
    {
        $request->validate([
            'eventos' => ['required', 'array', 'min:1', 'max:200'],
            'eventos.*.tipo' => ['required', 'string', 'max:60'],
            'eventos.*.mensagem' => ['nullable', 'string', 'max:500'],
            'eventos.*.dados' => ['nullable', 'array'],
            'eventos.*.ocorrido_em' => ['nullable', 'date'],
        ]);
        $token = $request->attributes->get('pos_token');

        foreach ($request->eventos as $e) {
            PosEvento::create([
                'empresa_id' => (int) session('empresa_id'),
                'user_id' => $request->user()->id,
                'unidade_id' => session('unidade_id'),
                'dispositivo_serial' => $token?->dispositivo_serial,
                'tipo' => 'app_' . preg_replace('/[^a-z0-9_]/', '', strtolower($e['tipo'])),
                'mensagem' => $e['mensagem'] ?? null,
                'dados' => $e['dados'] ?? null,
                'ocorrido_em' => isset($e['ocorrido_em']) ? \Carbon\Carbon::parse($e['ocorrido_em']) : now(),
            ]);
        }

        return response()->json(['ok' => true, 'gravados' => count($request->eventos)]);
    }

    /** GET caixa/{id}/comprovante — linhas do comprovante de fechamento (2ª via). */
    public function comprovanteCaixa(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $caixa = Caixa::withoutGlobalScopes()
            ->where('empresa_id', (int) session('empresa_id'))
            ->where('unidade_id', (int) session('unidade_id'))
            ->with('movimentacoes')
            ->findOrFail($id);

        $gestor = $user->is_admin || in_array($user->perfil->value, ['admin', 'dono', 'gerente'], true);
        if (! $gestor && (int) $caixa->user_id !== (int) $user->id) {
            return response()->json(['erro' => 'Você só vê o próprio caixa.'], 403);
        }

        $resumo = app(\App\Http\Controllers\App\CaixaController::class)->resumoCaixa($caixa);
        $operador = \App\Models\User::withoutGlobalScopes()->find($caixa->user_id);

        return response()->json([
            'cupom' => (new PosCupomService((int) $request->input('colunas', 32)))->fechamentoCaixa($caixa, $resumo, $operador),
        ]);
    }
}
