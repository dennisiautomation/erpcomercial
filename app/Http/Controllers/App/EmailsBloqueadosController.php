<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Services\FocusNFe\EmailsBloqueadosService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailsBloqueadosController extends Controller
{
    public function index()
    {
        $config = $this->configAtiva();
        $emails = [];
        $erro = null;

        if ($config) {
            try {
                $service = new EmailsBloqueadosService(FocusNFeClient::fromConfig($config));
                $emails = $service->listar();
            } catch (\Throwable $e) {
                Log::error('Erro ao listar emails bloqueados', ['error' => $e->getMessage()]);
                $erro = $e->getMessage();
            }
        }

        return view('app.emails-bloqueados.index', [
            'emails' => $emails,
            'erro' => $erro,
            'fiscalAtivo' => (bool) $config,
        ]);
    }

    public function desbloquear(Request $request, string $email)
    {
        $config = $this->configAtiva();

        if (! $config) {
            return back()->with('error', 'Configuração fiscal não encontrada.');
        }

        try {
            $service = new EmailsBloqueadosService(FocusNFeClient::fromConfig($config));
            $service->desbloquear($email);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Email {$email} desbloqueado. A Focus voltará a tentar entregas para este endereço.");
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
