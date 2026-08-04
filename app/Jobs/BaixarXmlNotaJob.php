<?php

namespace App\Jobs;

use App\Models\NotaFiscal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Guarda cópia local do XML da nota (storage/app/private/fiscal/xmls/).
 * Disparado pelo hook saved do model quando a nota ganha chave/XML/status.
 * Retenta: a Focus pode demorar alguns segundos para publicar o arquivo.
 */
class BaixarXmlNotaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var array<int> backoff crescente entre tentativas (s) */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $notaId) {}

    public function handle(): void
    {
        $nota = NotaFiscal::withoutGlobalScopes()->find($this->notaId);

        if (! $nota || ! $nota->chave_acesso || ! $nota->xml_url) {
            return; // nada a fazer
        }

        if (! $nota->salvarXmlLocal()) {
            // lança para cair no retry com backoff
            throw new \RuntimeException("XML da nota {$this->notaId} ainda indisponível na Focus");
        }
    }
}
