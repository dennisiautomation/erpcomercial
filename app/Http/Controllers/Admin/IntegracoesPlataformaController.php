<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpresaGateway;
use App\Models\PlataformaConfiguracao;
use App\Services\Entrega\MelhorEnvioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Integrações da PLATAFORMA (05/09/2026) — credenciais que são da IA365 e
 * valem para todas as empresas-clientes. Hoje: o aplicativo "IA365" no
 * Melhor Envio (client_id/secret, e-mail do User-Agent, ambiente, escopos).
 * A conexão de cada empresa fica no card da aba Integração da empresa.
 */
class IntegracoesPlataformaController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $app = MelhorEnvioService::app();
        $conectadas = EmpresaGateway::with('empresa:id,nome_fantasia')
            ->where('provedor', EmpresaGateway::PROVEDOR_MELHOR_ENVIO)
            ->get();

        return view('admin.integracoes.index', [
            'melhorEnvio' => $app,
            'callbackUrl' => MelhorEnvioService::callbackUrl(),
            'scopesPadrao' => MelhorEnvioService::SCOPES_PADRAO,
            'conectadas' => $conectadas,
        ]);
    }

    public function storeMelhorEnvio(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'client_id' => ['nullable', 'string', 'max:100'],
            'client_secret' => ['nullable', 'string', 'max:200'],
            'email_suporte' => ['nullable', 'email', 'max:150'],
            'ambiente' => ['required', 'in:producao,sandbox'],
            'scopes' => ['nullable', 'string', 'max:500'],
        ]);

        // Campo vazio = mantém o que está salvo (mesma regra dos cards de gateway).
        if (filled($validated['client_id'] ?? null)) {
            PlataformaConfiguracao::set('melhor_envio_client_id', trim($validated['client_id']));
        }
        if (filled($validated['client_secret'] ?? null)) {
            PlataformaConfiguracao::set('melhor_envio_client_secret', trim($validated['client_secret']));
        }
        PlataformaConfiguracao::set('melhor_envio_email_suporte', trim((string) ($validated['email_suporte'] ?? '')) ?: null);
        PlataformaConfiguracao::set('melhor_envio_ambiente', $validated['ambiente']);
        PlataformaConfiguracao::set('melhor_envio_scopes', trim((string) ($validated['scopes'] ?? '')) ?: null);

        $app = MelhorEnvioService::app();

        return redirect()
            ->route('admin.integracoes.index')
            ->with('success', 'Aplicativo Melhor Envio salvo.' . ($app['configurado']
                ? ' Agora cada empresa pode clicar em "Conectar Melhor Envio" na aba Integração.'
                : ' Atenção: faltam Client ID e/ou Secret — o botão Conectar das empresas fica travado até completar.'));
    }
}
