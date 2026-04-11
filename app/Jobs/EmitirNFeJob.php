<?php

namespace App\Jobs;

use App\Models\ConfiguracaoFiscal;
use App\Models\Venda;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EmitirNFeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Venda $venda,
        public array $dadosAdicionais = [],
    ) {}

    public function handle(): void
    {
        $venda = $this->venda->load(['itens.produto', 'cliente', 'unidade.empresa']);
        $unidade = $venda->unidade;

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $unidade->empresa_id)
            ->where('unidade_id', $unidade->id)
            ->first();

        if (! $config || ! $config->emissao_fiscal_ativa) {
            Log::info('[Fiscal] Emissão fiscal desativada, NF-e não emitida.', [
                'venda_id' => $venda->id,
            ]);
            return;
        }

        try {
            $client = FocusNFeClient::fromConfig($config);
            $service = new NFeService($client);
            $nota = $service->emitir($venda, $config, $this->dadosAdicionais);

            Log::info('[Fiscal] NF-e enviada para processamento.', [
                'venda_id' => $venda->id,
                'nota_id' => $nota->id,
                'status' => $nota->status->value,
            ]);

            // Agendar consulta para verificar se foi autorizada
            ConsultarNotaFiscalJob::dispatch($nota)->delay(now()->addSeconds(10));
        } catch (\Throwable $e) {
            Log::error('[Fiscal] Erro ao emitir NF-e via job.', [
                'venda_id' => $venda->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(30);
            }
        }
    }

    public function tags(): array
    {
        return ['fiscal', 'nfe', "venda:{$this->venda->id}"];
    }
}
