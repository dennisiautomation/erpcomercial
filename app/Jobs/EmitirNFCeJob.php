<?php

namespace App\Jobs;

use App\Models\ConfiguracaoFiscal;
use App\Models\Venda;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFCeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EmitirNFCeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Venda $venda,
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
            Log::info('[Fiscal] Emissão fiscal desativada, NFC-e não emitida.', [
                'venda_id' => $venda->id,
            ]);
            return;
        }

        try {
            $client = FocusNFeClient::fromConfig($config);
            $service = new NFCeService($client);
            $nota = $service->emitir($venda, $config);

            Log::info('[Fiscal] NFC-e emitida via job.', [
                'venda_id' => $venda->id,
                'nota_id' => $nota->id,
                'status' => $nota->status->value,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Fiscal] Erro ao emitir NFC-e via job.', [
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
        return ['fiscal', 'nfce', "venda:{$this->venda->id}"];
    }
}
