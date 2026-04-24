<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Services\FocusNFe\BackupXmlService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BackupsXmlController extends Controller
{
    public function index()
    {
        $config = $this->configAtiva();

        if (! $config) {
            return view('app.backups-xml.index', [
                'fiscalAtivo' => false,
                'meses' => [],
                'backups' => [],
            ]);
        }

        $service = new BackupXmlService(FocusNFeClient::fromConfig($config));
        $meses = $service->mesesDisponiveis();
        $backups = [];

        // Consulta o status de cada mês listado (com cache curto para não martelar a Focus)
        foreach ($meses as $mes) {
            try {
                $backups[$mes] = cache()->remember(
                    "focus.backup.{$config->empresa_id}.{$config->unidade_id}.{$mes}",
                    60,
                    fn () => $service->consultar($mes)
                );
            } catch (\Throwable $e) {
                $backups[$mes] = ['status' => 'indisponivel', 'url' => null, 'mes' => $mes];
            }
        }

        return view('app.backups-xml.index', [
            'fiscalAtivo' => true,
            'meses' => $meses,
            'backups' => $backups,
        ]);
    }

    public function gerar(Request $request)
    {
        $request->validate([
            'mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], [
            'mes.regex' => 'Mês inválido — use o formato YYYY-MM.',
        ]);

        $config = $this->configAtiva();

        if (! $config) {
            return back()->with('error', 'Configuração fiscal não encontrada ou inativa.');
        }

        try {
            $service = new BackupXmlService(FocusNFeClient::fromConfig($config));
            $result = $service->solicitar($request->input('mes'));
        } catch (\Throwable $e) {
            Log::error('Erro ao solicitar backup XML', [
                'mes' => $request->input('mes'),
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', $e->getMessage());
        }

        // Invalida cache para o próximo index mostrar o status novo
        cache()->forget("focus.backup.{$config->empresa_id}.{$config->unidade_id}.{$result['mes']}");

        $msg = $result['status'] === 'concluido' && $result['url']
            ? "Backup de {$result['mes']} já disponível para download."
            : "Backup de {$result['mes']} em processamento. Atualize a página em alguns minutos.";

        return back()->with('success', $msg);
    }

    private function configAtiva(): ?ConfiguracaoFiscal
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->first();

        if (! $config || ! $config->tokenFocusAmbienteAtual() || ! $config->emissao_fiscal_ativa) {
            return null;
        }

        return $config;
    }
}
