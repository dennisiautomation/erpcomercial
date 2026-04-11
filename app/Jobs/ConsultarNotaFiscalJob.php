<?php

namespace App\Jobs;

use App\Models\NotaFiscal;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFeService;
use App\Services\FocusNFe\NFCeService;
use App\Services\FocusNFe\NFSeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConsultarNotaFiscalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;
    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        public NotaFiscal $notaFiscal,
    ) {}

    public function handle(): void
    {
        $nota = $this->notaFiscal->fresh();

        // Se já autorizada ou em estado final, não consultar mais
        if (in_array($nota->status->value, ['autorizada', 'cancelada', 'rejeitada', 'inutilizada'])) {
            Log::info('[Fiscal] Nota já em estado final, job ignorado.', [
                'nota_id' => $nota->id,
                'status' => $nota->status->value,
            ]);
            return;
        }

        $unidade = $nota->unidade;

        try {
            $client = FocusNFeClient::forUnidade($unidade);

            $service = match ($nota->tipo->value) {
                'nfe' => new NFeService($client),
                'nfce' => new NFCeService($client),
                'nfse' => new NFSeService($client),
            };

            $service->consultar($nota);

            Log::info('[Fiscal] Consulta realizada.', [
                'nota_id' => $nota->id,
                'tipo' => $nota->tipo->value,
                'status' => $nota->fresh()->status->value,
            ]);

            // Se ainda processando, re-agendar
            if ($nota->fresh()->status->value === 'pendente') {
                self::dispatch($nota)->delay(now()->addSeconds(30));
            }
        } catch (\Throwable $e) {
            Log::error('[Fiscal] Erro ao consultar nota.', [
                'nota_id' => $nota->id,
                'error' => $e->getMessage(),
            ]);

            // Re-tentar se não esgotou as tentativas
            if ($this->attempts() < $this->tries) {
                $this->release(60);
            }
        }
    }

    public function tags(): array
    {
        return [
            'fiscal',
            'consulta',
            "nota:{$this->notaFiscal->id}",
            "tipo:{$this->notaFiscal->tipo->value}",
        ];
    }
}
