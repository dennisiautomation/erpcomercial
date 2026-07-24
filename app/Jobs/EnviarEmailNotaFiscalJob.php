<?php

namespace App\Jobs;

use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envia XML + DANFE por e-mail (via Focus /email) após a autorização da NF-e.
 * Disparado automaticamente para vendas geradas por faturamento de pedido.
 */
class EnviarEmailNotaFiscalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $notaFiscalId,
        public string $email,
    ) {}

    public function handle(): void
    {
        $nota = NotaFiscal::withoutGlobalScopes()->find($this->notaFiscalId);

        if (! $nota
            || $nota->tipo?->value !== 'nfe'
            || $nota->status?->value !== 'autorizada') {
            return;
        }

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $nota->empresa_id)
            ->where('unidade_id', $nota->unidade_id)
            ->first();

        if (! $config) {
            return;
        }

        try {
            $client = FocusNFeClient::fromConfig($config);
            (new NFeService($client))->reenviarEmail($nota, [$this->email]);

            Log::info('[NotaFiscal] XML+DANFE enviados por e-mail ao cliente.', [
                'nota_id' => $nota->id,
                'email'   => $this->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('[NotaFiscal] Falha ao enviar e-mail automático da NF-e.', [
                'nota_id' => $nota->id,
                'email'   => $this->email,
                'error'   => $e->getMessage(),
            ]);

            throw $e; // deixa a fila re-tentar
        }
    }
}
