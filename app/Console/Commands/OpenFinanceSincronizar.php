<?php

namespace App\Console\Commands;

use App\Models\ContaBancaria;
use App\Services\OpenFinance\SincronizadorExtrato;
use App\Services\OpenFinance\TecnospeedClient;
use Illuminate\Console\Command;

/**
 * Open Finance (17/11/2026): passa por todas as contas bancárias ligadas, confere se o dono
 * já autorizou no banco, busca o extrato e deixa as sugestões de conciliação prontas.
 */
class OpenFinanceSincronizar extends Command
{
    protected $signature = 'openfinance:sincronizar {--conta= : id de uma conta só} {--forcar : pede extrato mesmo fora da hora}';

    protected $description = 'Traz o extrato dos bancos conectados e sugere a conciliação';

    public function handle(TecnospeedClient $api, SincronizadorExtrato $sincronizador): int
    {
        if (! $api->configurado()) {
            $this->line('Tecnospeed sem chave configurada — nada a fazer.');

            return self::SUCCESS;
        }

        $contas = ContaBancaria::withoutGlobalScopes()
            ->whereNotNull('account_hash')
            ->where('situacao', '!=', 'desconectada')
            ->when($this->option('conta'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        foreach ($contas as $conta) {
            // Empresa que perdeu a liberação não gasta consulta; a conta fica como está.
            $empresa = $conta->empresa()->withoutGlobalScopes()->first();
            if (! $empresa || ! $empresa->openFinanceLiberado()) {
                $this->line("conta {$conta->id}: empresa sem liberação");

                continue;
            }

            $this->line("conta {$conta->id}: " . $sincronizador->sincronizar($conta, (bool) $this->option('forcar')));
        }

        return self::SUCCESS;
    }
}
