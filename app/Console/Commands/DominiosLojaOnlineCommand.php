<?php

namespace App\Console\Commands;

use App\Models\LojaOnline;
use App\Services\LojaOnline\DominioLoja;
use Illuminate\Console\Command;

/**
 * Domínios próprios das lojas online (16/11/2026) — a ponte com o servidor.
 *
 *   php artisan loja:dominios                 lista todos, com a situação
 *   php artisan loja:dominios --pendentes     só os nomes prontos para o certificado (um por linha)
 *   php artisan loja:dominios --conferir      confere de novo o apontamento dos que não estão no ar
 *   php artisan loja:dominios --ativar=x.com  marca "no ar" (o certificado foi emitido)
 *
 * Quem chama `--pendentes` e `--ativar` é scripts/loja-dominios-host.sh, no
 * servidor: o app não mexe em nginx nem emite certificado.
 */
class DominiosLojaOnlineCommand extends Command
{
    protected $signature = 'loja:dominios {--pendentes : só os domínios apontados que esperam o certificado}
        {--conferir : confere de novo os que não estão no ar} {--ativar= : marca este domínio como no ar}';

    protected $description = 'Loja online: situação dos domínios próprios e ponte com a rotina de certificado do servidor';

    public function handle(): int
    {
        if ($dominio = DominioLoja::normalizar($this->option('ativar'))) {
            $loja = LojaOnline::where('dominio', $dominio)->first();
            if (! $loja) {
                $this->error("Nenhuma loja usa o domínio {$dominio}.");

                return self::FAILURE;
            }
            $loja->update(['dominio_status' => 'ativo', 'dominio_obs' => 'Tudo certo: a loja já abre por este domínio, com cadeado.', 'dominio_conferido_em' => now()]);
            $this->info("{$dominio}: no ar.");

            return self::SUCCESS;
        }

        $lojas = LojaOnline::where('ativo', true)->whereNotNull('dominio')->where('dominio', '!=', '')->orderBy('dominio')->get();

        if ($this->option('conferir') || $this->option('pendentes')) {
            foreach ($lojas->where('dominio_status', '!=', 'ativo') as $loja) {
                DominioLoja::conferir($loja);
            }
        }

        if ($this->option('pendentes')) {
            foreach ($lojas->where('dominio_status', 'dns_ok') as $loja) {
                $this->line($loja->dominio);
            }

            return self::SUCCESS;
        }

        $this->table(['Domínio', 'Loja', 'Situação', 'Conferido em'], $lojas->map(fn ($l) => [
            $l->dominio, $l->slug, DominioLoja::SITUACOES[$l->dominio_status][0] ?? 'Não conferido',
            $l->dominio_conferido_em?->format('d/m/Y H:i') ?? '—',
        ])->all());

        return self::SUCCESS;
    }
}
