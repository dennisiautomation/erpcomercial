<?php

namespace App\Jobs;

use App\Models\AgenteIaConfig;
use App\Services\AgenteIa\IndexadorProdutos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Indexação inicial/completa dos produtos de UMA empresa no banco vetorial.
 *
 * Disparado ao ativar o Agente IA no /admin (aba Integração da empresa) e
 * pelo comando agente:reindex. Idempotente: upsert + poda dos removidos.
 */
class IndexarEmpresaAgenteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 2;

    public function __construct(public readonly int $empresaId)
    {
    }

    public function handle(IndexadorProdutos $indexador): void
    {
        $config = AgenteIaConfig::where('empresa_id', $this->empresaId)->first();

        if (! $config || ! $config->ativo) {
            Log::channel('integracao')->info('Agente IA inativo — indexação ignorada', [
                'empresa_id' => $this->empresaId,
            ]);

            return;
        }

        $total = $indexador->indexarEmpresa($this->empresaId);

        Log::channel('integracao')->info('Agente IA: empresa indexada', [
            'empresa_id' => $this->empresaId,
            'produtos' => $total,
        ]);
    }

    public function failed(Throwable $e): void
    {
        AgenteIaConfig::where('empresa_id', $this->empresaId)->update([
            'ultima_falha' => mb_substr($e->getMessage(), 0, 500),
        ]);

        Log::channel('integracao')->error('Agente IA: indexação da empresa falhou', [
            'empresa_id' => $this->empresaId,
            'erro' => $e->getMessage(),
        ]);
    }
}
