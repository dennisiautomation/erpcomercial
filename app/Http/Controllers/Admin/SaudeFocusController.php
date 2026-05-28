<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionarEmpresaFocusJob;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Services\FocusNFe\FocusEmpresaService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard de saúde da integração Focus NFe para uma empresa.
 *
 * Mostra (por unidade):
 *  - Empresa criada na Focus? (focus_empresa_id presente)
 *  - Tokens homologação/produção emitidos?
 *  - Certificado A1 enviado e validade
 *  - Webhooks ativos (lista de hook_id por evento)
 *  - Última sincronização
 *  - Status SEFAZ da UF da unidade
 *
 * Botão "Resincronizar" dispara ProvisionarEmpresaFocusJob.
 */
class SaudeFocusController extends Controller
{
    public function show(Request $request, Empresa $empresa): View
    {
        abort_unless($request->user()->is_admin, 403);

        $empresa->load('unidades');

        $masterOk = FocusNFeClient::masterDisponivel();

        $configs = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->get()
            ->keyBy('unidade_id');

        $unidades = $empresa->unidades->map(function ($unidade) use ($configs, $masterOk) {
            $config = $configs->get($unidade->id);
            $temFocus = $config && $config->focus_empresa_id;
            $temTokens = $config && ($config->focus_token_producao || $config->focus_token_homologacao);
            $temWebhooks = $config && is_array($config->focus_webhook_ids) && count($config->focus_webhook_ids) > 0;
            $diasCert = $config?->diasParaVencerCertificado();

            return [
                'unidade' => $unidade,
                'config' => $config,
                'tem_focus' => $temFocus,
                'tem_tokens' => $temTokens,
                'tem_certificado' => $config?->temCertificadoValido() ?? false,
                'dias_cert' => $diasCert,
                'tem_webhooks' => $temWebhooks,
                'webhooks_count' => is_array($config?->focus_webhook_ids ?? null) ? count($config->focus_webhook_ids) : 0,
                'eventos_ativos' => is_array($config?->focus_webhook_ids ?? null) ? array_keys($config->focus_webhook_ids) : [],
                'pode_provisionar' => $masterOk,
                'ultimo_sync' => $config?->webhooks_sincronizados_em,
            ];
        });

        return view('admin.empresas.saude-focus', [
            'empresa' => $empresa,
            'unidades' => $unidades,
            'master_disponivel' => $masterOk,
        ]);
    }

    public function resincronizar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if (! FocusNFeClient::masterDisponivel()) {
            return back()->with('error', 'Token master Focus NFe não configurado. Defina FOCUS_MASTER_TOKEN no .env.');
        }

        $unidadeId = $request->input('unidade_id');
        $unidades = $unidadeId
            ? $empresa->unidades()->whereKey($unidadeId)->get()
            : $empresa->unidades;

        foreach ($unidades as $unidade) {
            $config = ConfiguracaoFiscal::withoutGlobalScopes()
                ->where('empresa_id', $empresa->id)
                ->where('unidade_id', $unidade->id)
                ->first();

            $flags = [
                'habilita_nfe' => $config?->emite_nfe ?? true,
                'habilita_nfce' => $config?->emite_nfce ?? true,
                'habilita_nfse' => $config?->emite_nfse ?? false,
                'habilita_manifestacao' => true,
            ];

            ProvisionarEmpresaFocusJob::dispatch(
                empresaId: $empresa->id,
                unidadeId: $unidade->id,
                flags: $flags,
                solicitadoPor: $request->user()->id,
            );
        }

        return back()->with('success', 'Resincronização disparada — acompanhe pelo sino de notificações.');
    }
}
