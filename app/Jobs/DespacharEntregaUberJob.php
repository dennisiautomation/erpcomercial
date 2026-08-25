<?php

namespace App\Jobs;

use App\Models\Pedido;
use App\Models\PedidoEntrega;
use App\Services\Entrega\UberDirectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Despacho automático Uber Direct quando o pagamento do pedido confirma
 * (decisão do Dennis 13/08: automático, igual China Mix). REGRA DE OURO
 * (portada do despachoService do China Mix): falha no Uber NUNCA desfaz a
 * confirmação do pedido — registra o erro em pedido_entregas e o humano
 * despacha manualmente.
 *
 * Pula sem erro quando: gateway Uber inativo, cliente sem endereço/CEP,
 * CEP fora das faixas atendidas, fora da janela de horário, ou pedido já
 * tem entrega criada (idempotência).
 */
class DespacharEntregaUberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // sem retry automático: o humano decide (evita §267.3)

    public function __construct(public readonly int $pedidoId, public readonly int $empresaId)
    {
    }

    public function handle(): void
    {
        $uber = UberDirectService::ativoPara($this->empresaId);
        if (! $uber) {
            return;
        }

        $pedido = Pedido::withoutGlobalScopes()
            ->with(['cliente', 'unidade'])
            ->where('empresa_id', $this->empresaId)
            ->whereKey($this->pedidoId)
            ->first();

        if (! $pedido || ! $pedido->cliente || ! $pedido->unidade) {
            return;
        }

        // Cliente escolheu RETIRADA na conversa (25/08): nunca despachar,
        // mesmo com endereço cadastrado. NULL (pedido antigo/canal que não
        // pergunta) mantém o comportamento de sempre.
        if ($pedido->metodo_entrega === 'retirada') {
            return;
        }

        // Idempotência: já existe entrega criada (ou em criação) p/ o pedido
        if (PedidoEntrega::where('pedido_id', $pedido->id)->whereNotNull('delivery_id')->exists()) {
            return;
        }

        $dropoff = UberDirectService::enderecoCliente($pedido->cliente);
        if (! $dropoff || ! $uber->cepAtendido($pedido->cliente->cep)) {
            Log::channel('integracao')->info('Uber Direct: pedido pago fora do alcance da entrega automática', [
                'pedido_id' => $pedido->id,
                'motivo' => $dropoff ? 'cep_fora_das_faixas' : 'cliente_sem_endereco',
            ]);

            return;
        }

        if (! $uber->dentroJanela()) {
            PedidoEntrega::create([
                'pedido_id' => $pedido->id,
                'erro' => 'Pagamento fora da janela de operação do Uber — despachar manualmente.',
            ]);

            return;
        }

        try {
            $quote = $uber->cotar($pedido->unidade, $dropoff);
            $entrega = $uber->criarEntrega($pedido, $quote['id'], $dropoff);

            PedidoEntrega::updateOrCreate(
                ['pedido_id' => $pedido->id, 'provedor' => 'uber_direct'],
                [
                    'quote_id' => $quote['id'],
                    'delivery_id' => $entrega['delivery_id'],
                    'status' => $entrega['status'],
                    'tracking_url' => $entrega['tracking_url'],
                    'valor' => $entrega['fee'] / 100,
                    'courier' => $entrega['courier'],
                    'erro' => null,
                ]
            );

            Log::channel('integracao')->info('Uber Direct: entrega criada automaticamente', [
                'pedido_id' => $pedido->id,
                'delivery_id' => $entrega['delivery_id'],
            ]);
        } catch (\Throwable $e) {
            PedidoEntrega::updateOrCreate(
                ['pedido_id' => $pedido->id, 'provedor' => 'uber_direct'],
                ['erro' => mb_substr($e->getMessage(), 0, 1000)]
            );
            Log::channel('integracao')->error('Uber Direct: falha no despacho automático (pedido segue confirmado)', [
                'pedido_id' => $pedido->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
