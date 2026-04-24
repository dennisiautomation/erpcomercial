<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Models\ConfiguracaoFiscal;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFSeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Emite NFS-e de forma assíncrona e encadeia o polling.
 *
 * Serve para casos em que a emissão entra numa fila de trabalho
 * (emissão em lote, retentativa de erro transitório). Preserva
 * multi-tenant: o job carrega empresa_id/unidade_id no próprio
 * payload de dados e usa o token da config correspondente.
 */
class EmitirNFSeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $empresaId,
        public int $unidadeId,
        public array $dadosServico,
        public ?int $clienteId = null,
    ) {}

    public function handle(): void
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId)
            ->where('unidade_id', $this->unidadeId)
            ->first();

        if (! $config || ! $config->emissao_fiscal_ativa || ! $config->emite_nfse) {
            Log::info('[Fiscal] Emissão NFS-e desativada, job ignorado.', [
                'empresa_id' => $this->empresaId,
                'unidade_id' => $this->unidadeId,
            ]);
            return;
        }

        $cliente = $this->clienteId
            ? Cliente::withoutGlobalScopes()->find($this->clienteId)
            : null;

        try {
            $client = FocusNFeClient::fromConfig($config);
            $service = new NFSeService($client);
            $nota = $service->emitir($this->dadosServico, $config, $cliente);

            Log::info('[Fiscal] NFS-e enviada para processamento via job.', [
                'nota_id' => $nota->id,
                'status'  => $nota->status->value,
            ]);

            // Se ainda pendente, encadeia polling — mesma estratégia da NF-e.
            if ($nota->status->value === 'pendente') {
                ConsultarNotaFiscalJob::dispatch($nota)->delay(now()->addSeconds(10));
            }
        } catch (\Throwable $e) {
            Log::error('[Fiscal] Erro ao emitir NFS-e via job.', [
                'empresa_id' => $this->empresaId,
                'error'      => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(30);
            }
        }
    }

    public function tags(): array
    {
        return ['fiscal', 'nfse', "empresa:{$this->empresaId}", "unidade:{$this->unidadeId}"];
    }
}
