<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaGateway;
use App\Services\Pagamento\AsaasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Asaas por empresa (Fase 2, 13/08/2026) — cartão via link no pedido do
 * agente. api_key CIFRADA em client_secret; webhook_token gerado aqui e
 * exibido p/ colar no painel do Asaas junto com a URL do webhook.
 */
class EmpresaAsaasController extends Controller
{
    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
            'sandbox' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $gateway = EmpresaGateway::firstOrCreate(
            ['empresa_id' => $empresa->id, 'provedor' => EmpresaGateway::PROVEDOR_ASAAS],
            ['base_url' => 'https://api.asaas.com/v3']
        );

        if (filled($validated['api_key'] ?? null)) {
            $gateway->client_secret = trim($validated['api_key']);
        }

        $config = $gateway->config ?? [];
        $config['sandbox'] = $request->boolean('sandbox');
        if (blank($config['webhook_token'] ?? null)) {
            $config['webhook_token'] = Str::random(40);
        }
        $gateway->config = $config;
        $gateway->ativo = $request->boolean('ativo');
        $gateway->save();

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', 'Gateway Asaas salvo.' . ($gateway->ativo && blank($gateway->client_secret) ? ' Atenção: falta a api_key.' : ''))
            ->withFragment('integracao');
    }

    public function testar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $gateway = EmpresaGateway::where('empresa_id', $empresa->id)
            ->where('provedor', EmpresaGateway::PROVEDOR_ASAAS)
            ->first();

        if (! $gateway || blank($gateway->client_secret)) {
            return redirect()->route('admin.empresas.show', $empresa)
                ->with('error', 'Cole a api_key do Asaas antes de testar.')
                ->withFragment('integracao');
        }

        try {
            (new AsaasService($gateway))->testarConexao();
            $gateway->update(['ultima_falha' => null]);

            return redirect()->route('admin.empresas.show', $empresa)
                ->with('success', 'Conexão com o Asaas OK.')->withFragment('integracao');
        } catch (\Throwable $e) {
            $gateway->update(['ultima_falha' => mb_substr($e->getMessage(), 0, 500)]);

            return redirect()->route('admin.empresas.show', $empresa)
                ->with('error', 'Falha na conexão com o Asaas: ' . $e->getMessage())
                ->withFragment('integracao');
        }
    }
}
