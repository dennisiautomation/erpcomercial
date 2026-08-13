<?php

namespace App\Console\Commands;

use App\Models\PedidoCobranca;
use App\Services\Pix\PixPedidoService;
use Illuminate\Console\Command;

/**
 * Rede de segurança do webhook: consulta as cobranças ATIVAS na API do
 * Sicredi e confirma pagamentos que o webhook tenha perdido. Agendada a
 * cada 15 min em routes/console.php. Também expira cobranças vencidas.
 */
class SincronizarCobrancasPix extends Command
{
    protected $signature = 'agente:pix-sincronizar {--empresa= : Limita a uma empresa}';

    protected $description = 'Sincroniza cobranças PIX ativas do Agente IA com o Sicredi';

    public function handle(PixPedidoService $pixPedidos): int
    {
        $cobrancas = PedidoCobranca::where('status', 'ATIVA')
            ->when($this->option('empresa'), fn ($q, $e) => $q->where('empresa_id', $e))
            ->orderBy('id')
            ->limit(200)
            ->get();

        $pagas = 0;

        foreach ($cobrancas as $cobranca) {
            // Vencida há mais de 1 dia sem pagamento: marca local, sem API
            if ($cobranca->expira_em?->lt(now()->subDay())) {
                $cobranca->update(['status' => 'EXPIRADA']);
                continue;
            }

            $pixPedidos->sincronizarCobranca($cobranca->refresh());

            if ($cobranca->refresh()->paga()) {
                $pagas++;
            }
        }

        $this->info("{$cobrancas->count()} cobrança(s) verificada(s), {$pagas} paga(s).");

        return self::SUCCESS;
    }
}
