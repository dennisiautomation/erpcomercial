<?php

namespace App\Jobs;

use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFSesRecebidasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza NFS-es tomadas (onde a empresa é cliente do serviço).
 * Paralelo do SincronizarNFesRecebidasJob, mas para NFS-e.
 */
class SincronizarNFSesRecebidasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $empresaId,
        public int $unidadeId,
    ) {}

    public function handle(): void
    {
        $empresa = Empresa::withoutGlobalScopes()->find($this->empresaId);
        $unidade = Unidade::withoutGlobalScopes()->find($this->unidadeId);

        if (! $empresa || ! $unidade) {
            Log::warning('[NFSesRecebidasJob] empresa/unidade não encontrada', [
                'empresa_id' => $this->empresaId,
                'unidade_id' => $this->unidadeId,
            ]);
            return;
        }

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId)
            ->where('unidade_id', $this->unidadeId)
            ->first();

        if (! $config || ! $config->tokenFocusAmbienteAtual() || ! $config->emissao_fiscal_ativa) {
            Log::info('[NFSesRecebidasJob] unidade sem fiscal ativo, ignorada', [
                'unidade_id' => $this->unidadeId,
            ]);
            return;
        }

        if (! $config->emite_nfse) {
            // Unidade não emite NFS-e — não faz sentido puxar NFS-es tomadas
            return;
        }

        try {
            $service = new NFSesRecebidasService(FocusNFeClient::fromConfig($config));
            $novas = $service->sincronizar($empresa, $unidade);

            Log::info('[NFSesRecebidasJob] sync concluído', [
                'unidade_id' => $this->unidadeId,
                'novas' => $novas,
            ]);
        } catch (\Throwable $e) {
            Log::error('[NFSesRecebidasJob] falhou', [
                'unidade_id' => $this->unidadeId,
                'error' => $e->getMessage(),
            ]);
            if ($this->attempts() < $this->tries) {
                $this->release(600); // 10min
            }
        }
    }

    public function tags(): array
    {
        return ['fiscal', 'nfses-tomadas', 'sync', "empresa:{$this->empresaId}", "unidade:{$this->unidadeId}"];
    }
}
