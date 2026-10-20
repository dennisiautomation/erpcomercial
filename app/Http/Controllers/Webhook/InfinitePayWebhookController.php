<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\EmpresaGateway;
use App\Models\PedidoLinkCartao;
use App\Services\Pagamento\CartaoPedidoService;
use App\Services\Pagamento\InfinitePayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook InfinitePay (25/09/2026) — pagamento aprovado no link do pedido.
 *
 * A InfinitePay não assina o aviso. Então:
 *  1. o caminho leva o webhook_token da empresa (sem ele, nada é consultado);
 *  2. o order_nsu tem de ser um link NOSSO dessa empresa;
 *  3. o pagamento é RECONFIRMADO em payment_check antes de mexer no pedido.
 *
 * Respostas (regra da InfinitePay: 400 = "tente de novo"):
 *  - 200 para o que não é nosso / já processado / não confirmado (não adianta repetir);
 *  - 400 só quando a consulta à InfinitePay falhou — ela reenvia depois.
 */
class InfinitePayWebhookController extends Controller
{
    public function handle(Request $request, string $token): JsonResponse
    {
        $orderNsu = (string) $request->input('order_nsu', '');
        if ($orderNsu === '') {
            return response()->json(['ignored' => 'sem_order_nsu']);
        }

        $link = PedidoLinkCartao::where('provedor', EmpresaGateway::PROVEDOR_INFINITEPAY)
            ->where('referencia', $orderNsu)
            ->first();
        if (! $link) {
            return response()->json(['ignored' => 'pedido_desconhecido']);
        }

        $gw = EmpresaGateway::where('empresa_id', $link->empresa_id)
            ->where('provedor', EmpresaGateway::PROVEDOR_INFINITEPAY)
            ->first();
        $esperado = (string) ($gw?->config['webhook_token'] ?? '');
        if ($esperado === '' || ! hash_equals($esperado, $token)) {
            Log::channel('integracao')->warning('InfinitePay webhook: token inválido', [
                'order_nsu' => $orderNsu, 'ip' => $request->ip(),
            ]);

            return response()->json(['ignored' => 'nao_autorizado']);
        }

        if ($link->pago()) {
            return response()->json(['ignored' => 'ja_processado']);
        }

        $extras = [
            'transaction_nsu' => $this->texto($request->input('transaction_nsu')),
            'slug' => $this->texto($request->input('invoice_slug')),
            'receipt_url' => $this->texto($request->input('receipt_url')),
        ];

        // Guarda o que veio (mesmo sem confirmar) — a checagem periódica usa
        // transaction_nsu/slug na próxima consulta.
        $link->forceFill(array_filter([
            'transaction_nsu' => $extras['transaction_nsu'],
            'slug' => $extras['slug'],
            'receipt_url' => $extras['receipt_url'],
        ]))->save();

        try {
            $consulta = (new InfinitePayService($gw))->consultarPagamento(
                $orderNsu,
                $extras['transaction_nsu'],
                $extras['slug'],
            );
        } catch (\Throwable $e) {
            Log::channel('integracao')->warning('InfinitePay webhook: payment_check falhou — pedindo reenvio', [
                'order_nsu' => $orderNsu, 'erro' => $e->getMessage(),
            ]);

            return response()->json(['erro' => 'consulta_indisponivel'], 400);
        }

        $confirmou = app(CartaoPedidoService::class)->confirmarInfinitePay($link, $consulta, $extras, 'webhook');

        if (! $confirmou && ! $consulta['paid']) {
            Log::channel('integracao')->notice('InfinitePay webhook: payment_check diz NÃO pago', [
                'order_nsu' => $orderNsu, 'resposta' => $consulta['raw'],
            ]);
        }

        return response()->json(['success' => true, 'confirmado' => $confirmou]);
    }

    private function texto(mixed $v): ?string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';

        return $s !== '' ? mb_substr($s, 0, 500) : null;
    }
}
