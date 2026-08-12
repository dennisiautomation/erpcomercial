<?php

namespace App\Console\Commands;

use App\Models\ConfiguracaoFiscal;
use App\Models\Unidade;
use App\Services\FocusNFe\BackupXmlService;
use App\Support\Cnpj;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Monta o pacote mensal de XMLs fiscais por CNPJ, a partir das cópias locais
 * que o ERP já guarda por nota (BaixarXmlNotaJob / fiscal:baixar-xmls-notas).
 *
 * Não fala mais com a Focus: o endpoint /v2/backups dela não existe (404 em
 * todas as 7 noites de 05→12/08/2026). Os XMLs transmitidos já estão aqui —
 * o pacote auditável é só juntá-los.
 *
 * Roda diariamente no agendador. Sem --mes, remonta o mês corrente E o
 * anterior (pega nota atrasada/cancelamento que entrou depois da virada).
 *
 * Retenção: 5 anos (exigência fiscal). Arquivos em
 *   storage/app/private/fiscal/backups/{cnpj}/{YYYY-MM}.zip
 */
class BackupXmlsFiscaisCommand extends Command
{
    protected $signature = 'fiscal:backup-xmls
                            {--mes= : Mês YYYY-MM (default: mês corrente + anterior)}
                            {--unidade= : ID da unidade específica}';

    protected $description = 'Monta os pacotes mensais de XMLs fiscais (por CNPJ) a partir das cópias locais';

    public function handle(): int
    {
        $meses = $this->option('mes')
            ? [$this->option('mes')]
            : [now()->format('Y-m'), now()->subMonthNoOverflow()->format('Y-m')];

        $unidadeId = $this->option('unidade');

        $configs = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('emissao_fiscal_ativa', true)
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->get();

        // 1 pacote por (empresa, CNPJ) — lojas do mesmo CNPJ compartilham o zip
        // (armadilha 35: certificado, numeração e notas são do CNPJ).
        $alvos = [];
        foreach ($configs as $config) {
            $unidade = Unidade::withoutGlobalScopes()->with('empresa')->find($config->unidade_id);
            if (! $unidade) {
                continue;
            }

            $cnpj = Cnpj::limpar((string) ($unidade->cnpj ?: $unidade->empresa?->cnpj));
            if (! $cnpj) {
                continue;
            }

            $alvos[$unidade->empresa_id . ':' . $cnpj] = [
                'empresa_id' => $unidade->empresa_id,
                'cnpj' => $cnpj,
                'nome' => $unidade->empresa?->razao_social ?: $unidade->nome,
            ];
        }

        $svc = new BackupXmlService();
        $this->info('Pacotes: ' . count($alvos) . ' CNPJ(s) × ' . count($meses) . ' mês(es)');
        $gerados = 0;
        $vazios = 0;
        $falhas = 0;

        foreach ($meses as $mes) {
            foreach ($alvos as $alvo) {
                try {
                    $r = $svc->gerar($alvo['empresa_id'], $alvo['cnpj'], $mes);

                    if ($r['status'] === 'concluido') {
                        $aviso = $r['sem_xml'] ? ' (⚠ ' . count($r['sem_xml']) . ' sem XML)' : '';
                        $this->info("  ✓ {$alvo['nome']} ({$alvo['cnpj']}) {$mes} → {$r['arquivos']} XML(s){$aviso}");
                        $gerados++;
                    } else {
                        $this->line("  · {$alvo['nome']} ({$alvo['cnpj']}) {$mes} → sem notas");
                        $vazios++;
                    }
                } catch (\Throwable $e) {
                    $this->error("  ✗ {$alvo['nome']} {$mes}: " . $e->getMessage());
                    Log::error('[BackupFiscal] falha', [
                        'empresa_id' => $alvo['empresa_id'],
                        'cnpj' => $alvo['cnpj'],
                        'mes' => $mes,
                        'erro' => $e->getMessage(),
                    ]);
                    $falhas++;
                }
            }
        }

        $this->newLine();
        $this->info("✓ {$gerados} pacotes · · {$vazios} sem notas · ✗ {$falhas} falhas");

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
