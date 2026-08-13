<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\PedidoCobranca;
use App\Services\Pix\PixPedidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook PIX Sicredi (payload BACEN: {"pix": [{"txid": ..., "endToEndId": ...}]}).
 *
 * A rota vive sob api/integracao/* (já isenta de CSRF — armadilha 46 evitada:
 * nada de mexer em bootstrap/). O Sicredi registra {url} e ENTREGA em {url}/pix.
 *
 * Segurança: o payload é tratado como DICA, nunca como verdade — para cada
 * txid conhecido consultamos a cobrança na API do Sicredi (mTLS) e só
 * confirmamos o pedido com a resposta autenticada. txid desconhecido é
 * ignorado (pode ser de outra integração da mesma chave).
 */
class SicrediPixWebhookController extends Controller
{
    public function handle(Request $request, PixPedidoService $pixPedidos): JsonResponse
    {
        $eventos = $request->input('pix', []);

        Log::channel('integracao')->info('Sicredi PIX: webhook recebido', [
            'qtd' => is_array($eventos) ? count($eventos) : 0,
            'ip' => $request->ip(),
        ]);

        if (! is_array($eventos)) {
            return response()->json(['ok' => true]);
        }

        foreach ($eventos as $evento) {
            $txid = is_array($evento) ? ($evento['txid'] ?? null) : null;

            if (! $txid) {
                continue;
            }

            $cobranca = PedidoCobranca::where('txid', $txid)->first();

            if (! $cobranca) {
                Log::channel('integracao')->notice('Sicredi PIX: webhook com txid desconhecido', ['txid' => $txid]);
                continue;
            }

            try {
                $pixPedidos->sincronizarCobranca($cobranca);
            } catch (\Throwable $e) {
                Log::channel('integracao')->error('Sicredi PIX: erro ao processar webhook', [
                    'txid' => $txid,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        // 200 sempre — o PSP não deve ficar reentregando por erro nosso
        return response()->json(['ok' => true]);
    }
}
