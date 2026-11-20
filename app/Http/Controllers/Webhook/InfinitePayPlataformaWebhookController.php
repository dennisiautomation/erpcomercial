<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\PlataformaFatura;
use App\Services\Plataforma\AssinaturaSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook InfinitePay da PLATAFORMA (20/11/2026) — fatura do sistema paga no cartão.
 *
 * A InfinitePay não assina o aviso. Então: o caminho leva o token da plataforma, o
 * order_nsu tem de ser de um link NOSSO e o pagamento é reconfirmado em payment_check.
 * 200 para o que não é nosso ou já foi tratado; 400 só quando a consulta falhou (ela reenvia).
 */
class InfinitePayPlataformaWebhookController extends Controller
{
    public function handle(Request $request, string $token): JsonResponse
    {
        if (! hash_equals(AssinaturaSite::webhookToken(), $token)) {
            Log::warning('[AssinaturaSite] webhook InfinitePay: token inválido', ['ip' => $request->ip()]);

            return response()->json(['ignored' => 'nao_autorizado']);
        }

        $nsu = (string) $request->input('order_nsu', '');
        $fatura = $nsu !== '' ? PlataformaFatura::where('cartao_nsu', $nsu)->first() : null;
        if (! $fatura) {
            return response()->json(['ignored' => 'fatura_desconhecida']);
        }
        if ($fatura->cartao_pago_em) {
            return response()->json(['ignored' => 'ja_processado']);
        }

        $texto = fn ($v) => is_scalar($v) && (string) $v !== '' ? (string) $v : null;
        $r = AssinaturaSite::conferirCartao($fatura, $texto($request->input('transaction_nsu')), $texto($request->input('invoice_slug')));

        if (! $r['consultou'] && ! $r['pago']) {
            return response()->json(['erro' => 'consulta_indisponivel'], 400);
        }

        return response()->json(['ok' => true, 'pago' => $r['pago']]);
    }
}
