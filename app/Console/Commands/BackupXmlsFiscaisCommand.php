<?php

namespace App\Console\Commands;

use App\Models\ConfiguracaoFiscal;
use App\Models\Unidade;
use App\Services\FocusNFe\BackupXmlService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Solicita à Focus NFe o backup mensal de XMLs (NF-e, NFC-e, CT-e, MDF-e)
 * para cada unidade com emissão fiscal ativa.
 *
 * Roda diariamente no agendador. No dia 5 de cada mês, solicita o backup
 * do mês anterior. Nos demais dias, apenas verifica e baixa backups
 * já gerados pela Focus para o mês corrente e anteriores.
 *
 * Retenção: 5 anos (exigência fiscal). Arquivos em
 *   storage/app/fiscal/backups/{cnpj}/{YYYY-MM}.zip
 */
class BackupXmlsFiscaisCommand extends Command
{
    protected $signature = 'fiscal:backup-xmls
                            {--mes= : Mês YYYY-MM (default: mês anterior se hoje >=5, senão atual)}
                            {--unidade= : ID da unidade específica}
                            {--apenas-download : pular solicitação, só baixar prontos}';

    protected $description = 'Solicita e baixa backups mensais de XMLs fiscais da Focus NFe';

    public function handle(BackupXmlService $service): int
    {
        $mes = $this->option('mes') ?: $this->mesPadrao();
        $unidadeId = $this->option('unidade');

        $configs = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('emissao_fiscal_ativa', true)
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->get();

        $this->info("Processando {$configs->count()} unidade(s) — mês {$mes}");
        $sucesso = 0; $falha = 0; $aguardando = 0;

        foreach ($configs as $config) {
            $unidade = Unidade::withoutGlobalScopes()->with('empresa')->find($config->unidade_id);
            if (! $unidade) continue;

            $cnpj = preg_replace('/\D/', '', $unidade->cnpj ?: $unidade->empresa->cnpj);
            if (! $cnpj) continue;

            try {
                $client = FocusNFeClient::fromConfig($config);
                $svc = new BackupXmlService($client);

                // Solicitar se ainda não foi pedido este mês
                if (! $this->option('apenas-download')) {
                    $svc->solicitar($mes);
                }

                $status = $svc->consultar($mes);

                if (! empty($status['url']) && in_array($status['status'] ?? '', ['pronto', 'concluido', 'concluído'])) {
                    $this->baixarParaStorage($cnpj, $mes, $status['url']);
                    $this->info("  ✓ {$unidade->nome} ({$cnpj}) → backup salvo");
                    $sucesso++;
                } else {
                    $this->line("  ⏳ {$unidade->nome} ({$cnpj}) → status: " . ($status['status'] ?? 'pendente'));
                    $aguardando++;
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ {$unidade->nome}: " . $e->getMessage());
                Log::error('[BackupFiscal] falha', [
                    'unidade_id' => $unidade->id,
                    'mes' => $mes,
                    'erro' => $e->getMessage(),
                ]);
                $falha++;
            }
        }

        $this->newLine();
        $this->info("✓ {$sucesso} salvos · ⏳ {$aguardando} aguardando · ✗ {$falha} falhas");

        return self::SUCCESS;
    }

    private function mesPadrao(): string
    {
        // Dia 5+: backup do mês anterior. Antes do dia 5: mês corrente
        // (a Focus normalmente leva 2-3 dias para fechar)
        return now()->day >= 5
            ? now()->subMonth()->format('Y-m')
            : now()->format('Y-m');
    }

    private function baixarParaStorage(string $cnpj, string $mes, string $url): void
    {
        $path = "fiscal/backups/{$cnpj}/{$mes}.zip";
        if (Storage::disk('local')->exists($path)) {
            return; // já temos
        }
        $bytes = file_get_contents($url);
        if ($bytes === false) {
            throw new \RuntimeException("Download falhou: {$url}");
        }
        Storage::disk('local')->put($path, $bytes);
    }
}
