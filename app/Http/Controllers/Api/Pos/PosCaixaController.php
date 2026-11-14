<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\App\CaixaController;
use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Services\Pos\PosPermissoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caixa do operador no POS. Abrir/fechar/sangria/suprimento passam pelo
 * CaixaController do ERP (mesma regra, mesmo número de caixa, mesma conferência);
 * aqui só se escolhe a resposta JSON.
 */
class PosCaixaController extends Controller
{
    public function atual(Request $request): JsonResponse
    {
        $caixa = $this->caixa();
        if (! $caixa) {
            return response()->json(['caixa' => null]);
        }

        return response()->json(['caixa' => $this->caixaJson($caixa)]);
    }

    public function abrir(Request $request): Response
    {
        if ($erro = $this->exigePermissao($request)) {
            return $erro;
        }
        $resposta = app(CaixaController::class)->abrir($request);
        if ($resposta->getStatusCode() !== 200) {
            return $resposta;
        }

        return response()->json(['caixa' => $this->caixaJson($this->caixa())]);
    }

    /** Fecha o caixa e devolve o resumo para o comprovante impresso no POS. */
    public function fechar(Request $request): Response
    {
        if ($erro = $this->exigePermissao($request)) {
            return $erro;
        }
        $caixa = $this->caixa();
        if (! $caixa) {
            return response()->json(['erro' => 'Nenhum caixa aberto.'], 422);
        }

        $resposta = app(CaixaController::class)->fechar($request);
        if ($resposta->getStatusCode() !== 200) {
            return $resposta;
        }

        return response()->json(['caixa' => $this->caixaJson($caixa->fresh())]);
    }

    public function sangria(Request $request): Response
    {
        return $this->exigePermissao($request) ?? app(CaixaController::class)->sangria($request);
    }

    public function suprimento(Request $request): Response
    {
        return $this->exigePermissao($request) ?? app(CaixaController::class)->suprimento($request);
    }

    private function caixa(): ?Caixa
    {
        $id = session('caixa_id');

        return $id ? Caixa::with('movimentacoes')->find($id) : null;
    }

    private function caixaJson(Caixa $caixa): array
    {
        $caixa->loadMissing('movimentacoes');
        $resumo = app(CaixaController::class)->resumoCaixa($caixa);
        $resumo['vendas_por_forma'] = collect($resumo['vendas_por_forma'])->map(fn ($v) => (float) $v);

        return [
            'id' => $caixa->id,
            'numero' => $caixa->numero_caixa,
            'status' => $caixa->status->value,
            'aberto_em' => $caixa->aberto_em?->toIso8601String(),
            'fechado_em' => $caixa->fechado_em?->toIso8601String(),
            'valor_abertura' => (float) $caixa->valor_abertura,
            'valor_fechamento' => $caixa->valor_fechamento !== null ? (float) $caixa->valor_fechamento : null,
            'conferencia' => $caixa->conferencia,
            'resumo' => $resumo,
        ];
    }

    private function exigePermissao(Request $request): ?JsonResponse
    {
        return PosPermissoes::pode($request->user(), 'vendas', 'criar')
            ? null
            : response()->json(['erro' => 'Seu perfil não opera caixa.'], 403);
    }
}
