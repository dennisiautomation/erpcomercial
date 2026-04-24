<?php

namespace App\Jobs;

use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\ManifestacaoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza as NFes destinadas de UMA unidade.
 *
 * No scheduler, iteramos as unidades fiscalmente ativas e despachamos
 * um job por unidade. Cada job mexe só no escopo daquele tenant.
 */
class SincronizarNFesRecebidasJob implements ShouldQueue
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
            Log::warning('[ManifestacaoJob] empresa/unidade não encontrada, ignorado', [
                'empresa_id' => $this->empresaId,
                'unidade_id' => $this->unidadeId,
            ]);
            return;
        }

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId)
            ->where('unidade_id', $this->unidadeId)
            ->first();

        if (! $config || ! $config->focus_token || ! $config->emissao_fiscal_ativa) {
            Log::info('[ManifestacaoJob] unidade sem fiscal ativo, ignorada', [
                'unidade_id' => $this->unidadeId,
            ]);
            return;
        }

        try {
            $service = new ManifestacaoService(FocusNFeClient::fromConfig($config));
            $novas = $service->sincronizar($empresa, $unidade);

            Log::info('[ManifestacaoJob] sync concluído', [
                'unidade_id' => $this->unidadeId,
                'novas'      => $novas,
            ]);
        } catch (\Throwable $e) {
            Log::error('[ManifestacaoJob] falhou', [
                'unidade_id' => $this->unidadeId,
                'error'      => $e->getMessage(),
            ]);
            if ($this->attempts() < $this->tries) {
                $this->release(300); // 5min
            }
        }
    }

    public function tags(): array
    {
        return ['fiscal', 'manifestacao', 'sync', "empresa:{$this->empresaId}", "unidade:{$this->unidadeId}"];
    }
}
