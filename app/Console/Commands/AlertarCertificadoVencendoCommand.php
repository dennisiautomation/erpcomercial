<?php

namespace App\Console\Commands;

use App\Models\ConfiguracaoFiscal;
use App\Models\Notificacao;
use Illuminate\Console\Command;

/**
 * Notifica donos de empresa quando o certificado A1 está prestes a vencer.
 * Disparo em janelas: 30, 15, 7 e 1 dia(s) antes do vencimento, sem repetição.
 * Cria notificação no sino — o módulo de email pode opcionalmente disparar
 * a partir da mesma fila se MAIL_MAILER != log.
 */
class AlertarCertificadoVencendoCommand extends Command
{
    protected $signature = 'fiscal:alertar-certificado';
    protected $description = 'Avisa donos sobre certificado A1 prestes a vencer (30/15/7/1 dias)';

    private const JANELAS = [30, 15, 7, 1];

    public function handle(): int
    {
        $configs = ConfiguracaoFiscal::withoutGlobalScopes()
            ->whereNotNull('certificado_validade')
            ->get();

        $criadas = 0;
        $hoje = now()->startOfDay();

        foreach ($configs as $config) {
            $dias = $config->diasParaVencerCertificado();
            if ($dias === null) continue;
            if ($dias < 0 || $dias > 30) continue; // já venceu ou ainda longe

            if (! in_array($dias, self::JANELAS, true)) continue;

            $empresa = $config->empresa()->withoutGlobalScopes()->first();
            $unidade = $config->unidade()->withoutGlobalScopes()->first();
            if (! $empresa || ! $unidade) continue;

            $dono = $empresa->users()->where('perfil', 'dono')->first();
            if (! $dono) continue;

            // Evitar duplicação no mesmo dia
            $jaTemHoje = Notificacao::where('user_id', $dono->id)
                ->where('tipo', 'fiscal')
                ->where('titulo', 'like', '%Certificado digital%')
                ->where('created_at', '>=', $hoje)
                ->exists();
            if ($jaTemHoje) continue;

            $urgencia = $dias <= 7 ? 'URGENTE — ' : '';
            Notificacao::create([
                'user_id' => $dono->id,
                'empresa_id' => $empresa->id,
                'tipo' => 'fiscal',
                'titulo' => "{$urgencia}Certificado digital vence em {$dias} dia(s)",
                'mensagem' => "{$unidade->nome}: certificado A1 ({$config->certificado_nome}) vence em {$config->certificado_validade->format('d/m/Y')}. Sem renovação, NF-e/NFC-e param.",
                'url' => route('app.configuracao-fiscal.edit', [], false),
            ]);
            $criadas++;
        }

        $this->info("{$criadas} alerta(s) criado(s).");
        return self::SUCCESS;
    }
}
