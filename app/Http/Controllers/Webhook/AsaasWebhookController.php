<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\DespacharEntregaUberJob;
use App\Models\EmpresaGateway;
use App\Models\Pedido;
use App\Enums\StatusPedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Asaas (Fase 2, 13/08/2026) — pagamento no cartão confirma o pedido
 * do agente (rascunho → confirmado), igual ao PIX Sicredi. SEMPRE responde
 * 200: 4xx faz o Asaas re-enfileirar e acabar DESATIVANDO o webhook (modo de
 * falha documentado no §240 do app.ia365 — não repetir).
 *
 * Autentica pelo header `asaas-access-token` contra o webhook_token do
 * gateway da empresa dona do pedido (externalReference "pedido:{id}").
 */
class AsaasWebhookController extends Controller
{
    private const EVENTOS_PAGO = ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'];

    public function handle(Request $request): JsonResponse
    {
        $evento = (string) $request->input('event', '');
        $ref = (string) $request->input('payment.externalReference', '');

        if (! in_array($evento, self::EVENTOS_PAGO, true) || ! str_starts_with($ref, 'pedido:')) {
            return response()->json(['ignored' => true]);
        }

        $pedidoId = (int) substr($ref, strlen('pedido:'));
        $pedido = Pedido::withoutGlobalScopes()->with('cliente')->find($pedidoId);
        if (! $pedido) {
            return response()->json(['ignored' => 'pedido_desconhecido']);
        }

        $gw = EmpresaGateway::ativoPara((int) $pedido->empresa_id, EmpresaGateway::PROVEDOR_ASAAS);
        $tokenEsperado = (string) ($gw?->config['webhook_token'] ?? '');
        if ($tokenEsperado === '' || ! hash_equals($tokenEsperado, (string) $request->header('asaas-access-token'))) {
            Log::channel('integracao')->warning('Asaas webhook: token inválido', ['pedido_id' => $pedidoId]);

            return response()->json(['ignored' => 'nao_autorizado']);
        }

        $paymentId = (string) $request->input('payment.id', '');
        $valor = (float) $request->input('payment.value', 0);

        $nota = sprintf(
            "\nCARTÃO PAGO via Asaas em %s — R$ %s (payment %s).",
            now()->format('d/m/Y H:i'),
            number_format($valor, 2, ',', '.'),
            $paymentId
        );

        if ($pedido->status === StatusPedido::Rascunho) {
            $pedido->status = StatusPedido::Confirmado;
        }
        if ($paymentId !== '' && ! str_contains((string) $pedido->observacoes_internas, $paymentId)) {
            $pedido->observacoes_internas = trim(($pedido->observacoes_internas ?? '') . $nota);
        }
        $pedido->save();

        DespacharEntregaUberJob::dispatch($pedido->id, (int) $pedido->empresa_id);

        Log::channel('integracao')->info('Asaas: pedido confirmado por pagamento no cartão', [
            'pedido_id' => $pedido->id,
            'payment_id' => $paymentId,
        ]);

        return response()->json(['success' => true]);
    }
}
