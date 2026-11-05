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
            ->where('status', '!=', \App\Enums\StatusNotaFiscal::Rejeitada) // rejeitada não tem XML (canceladas sim)
            ->whereNotNull('chave_acesso')
            ->whereNotNull('xml_url')
            ->get();

        $ok = 0; $baixados = 0; $falha = 0; $rejeitadas = 0;

        foreach ($notas as $nota) {
            if ($nota->temXmlLocal()) {
                $ok++;
                continue;
            }

            if ($nota->salvarXmlLocal()) {
                $this->info("  ✓ nota {$nota->id} ({$nota->chave_acesso}) baixada");
                $baixados++;
            } elseif ($nota->foiRejeitadaNaFocus()) {
                // SEFAZ rejeitou depois da resposta "autorizado" — status já corrigido pela consulta
                $this->warn("  ! nota {$nota->id} foi rejeitada pela SEFAZ depois — ignorada");
                $rejeitadas++;
            } else {
                $this->warn("  ✗ nota {$nota->id} ({$nota->chave_acesso}) indisponível");
                $falha++;
            }
        }

        $this->info("Cópias locais: {$ok} já existiam · {$baixados} baixadas agora · {$falha} indisponíveis · {$rejeitadas} rejeitadas depois");

        return $falha > 0 ? self::FAILURE : self::SUCCESS;
    }
}
