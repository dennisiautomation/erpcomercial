<?php

namespace App\Console\Commands;

use App\Models\NotaFiscal;
use Illuminate\Console\Command;

/**
 * Varre as notas com chave de acesso e garante a cópia local do XML.
 * Backfill inicial + rede de segurança diária (o hook do model cobre o
 * fluxo normal; isto pega o que escapou — worker parado, Focus fora do ar).
 */
class BaixarXmlsNotasCommand extends Command
{
    protected $signature = 'fiscal:baixar-xmls-notas';

    protected $description = 'Garante cópia local do XML de todas as notas com chave de acesso';

    public function handle(): int
    {
        $notas = NotaFiscal::withoutGlobalScopes()
            ->whereNotNull('chave_acesso')
            ->whereNotNull('xml_url')
            ->get();

        $ok = 0; $baixados = 0; $falha = 0;

        foreach ($notas as $nota) {
            if ($nota->temXmlLocal()) {
                $ok++;
                continue;
            }

            if ($nota->salvarXmlLocal()) {
                $this->info("  ✓ nota {$nota->id} ({$nota->chave_acesso}) baixada");
                $baixados++;
            } else {
                $this->warn("  ✗ nota {$nota->id} ({$nota->chave_acesso}) indisponível");
                $falha++;
            }
        }

        $this->info("Cópias locais: {$ok} já existiam · {$baixados} baixadas agora · {$falha} indisponíveis");

        return $falha > 0 ? self::FAILURE : self::SUCCESS;
    }
}
