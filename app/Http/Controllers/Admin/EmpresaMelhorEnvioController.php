<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaGateway;
use App\Services\Entrega\MelhorEnvioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Melhor Envio por EMPRESA (05/09/2026) — card da aba Integração:
 *   conectar   → manda o admin autorizar a conta da empresa no Melhor Envio
 *   store      → pacote padrão, serviços permitidos, seguro, ativo
 *   testar     → GET /me com o token da empresa
 *   desconectar→ apaga tokens (a conta continua existindo no Melhor Envio)
 * O retorno da autorização cai em Integracao\MelhorEnvioCallbackController.
 */
class EmpresaMelhorEnvioController extends Controller
{
    public function conectar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if (! MelhorEnvioService::appConfigurado()) {
            return redirect()->route('admin.integracoes.index')
                ->with('error', 'Antes de conectar uma empresa, cadastre o Client ID e o Secret do aplicativo IA365 no Melhor Envio.');
        }

        // Garante o registro do gateway (inativo até a autorização voltar).
        EmpresaGateway::firstOrCreate(
            ['empresa_id' => $empresa->id, 'provedor' => EmpresaGateway::PROVEDOR_MELHOR_ENVIO],
            ['base_url' => MelhorEnvioService::baseUrl()]
        );

        return redirect()->away(MelhorEnvioService::urlAutorizacao($empresa->id));
    }

    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'pacote_altura' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'pacote_largura' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'pacote_comprimento' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'pacote_peso' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'servicos' => ['nullable', 'string', 'max:100', 'regex:/^[0-9, ]*$/'],
            'seguro' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $gateway = EmpresaGateway::firstOrCreate(
            ['empresa_id' => $empresa->id, 'provedor' => EmpresaGateway::PROVEDOR_MELHOR_ENVIO],
            ['base_url' => MelhorEnvioService::baseUrl()]
        );

        $config = $gateway->config ?? [];
        foreach (['pacote_altura', 'pacote_largura', 'pacote_comprimento', 'pacote_peso'] as $campo) {
            if (filled($validated[$campo] ?? null)) {
                $config[$campo] = (float) $validated[$campo];
            }
        }
        $config['servicos'] = preg_replace('/\s+/', '', (string) ($validated['servicos'] ?? ''));
        $config['seguro'] = $request->boolean('seguro');
        $gateway->config = $config;
        $gateway->ativo = $request->boolean('ativo');
        $gateway->save();

        $aviso = ($gateway->ativo && blank($gateway->access_token))
            ? ' Atenção: a conta ainda não foi autorizada — clique em "Conectar Melhor Envio".'
            : '';

        return redirect()->route('admin.empresas.show', $empresa)
            ->with('success', 'Melhor Envio salvo.' . $aviso)
            ->withFragment('integracao');
    }

    public function testar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $gateway = EmpresaGateway::where('empresa_id', $empresa->id)
            ->where('provedor', EmpresaGateway::PROVEDOR_MELHOR_ENVIO)
            ->first();

        if (! $gateway || blank($gateway->access_token)) {
            return redirect()->route('admin.empresas.show', $empresa)
                ->with('error', 'Melhor Envio ainda não conectado nesta empresa.')
                ->withFragment('integracao');
        }

        try {
            $me = MelhorEnvioService::paraGateway($gateway)->testarConexao();
            $msg = 'Melhor Envio conectado: ' . ($me['nome'] ?: $me['email'])
                . ' — token válido até ' . optional($gateway->fresh()->token_expira_em)->format('d/m/Y') . '.';

            return redirect()->route('admin.empresas.show', $empresa)->with('success', $msg)->withFragment('integracao');
        } catch (\Throwable $e) {
            $gateway->update(['ultima_falha' => mb_substr($e->getMessage(), 0, 1000)]);

            return redirect()->route('admin.empresas.show', $empresa)
                ->with('error', 'Melhor Envio recusou: ' . $e->getMessage())
                ->withFragment('integracao');
        }
    }

    public function desconectar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        EmpresaGateway::where('empresa_id', $empresa->id)
            ->where('provedor', EmpresaGateway::PROVEDOR_MELHOR_ENVIO)
            ->update(['access_token' => null, 'refresh_token' => null, 'token_expira_em' => null, 'ativo' => false]);

        return redirect()->route('admin.empresas.show', $empresa)
            ->with('success', 'Melhor Envio desconectado desta empresa. Para voltar, clique em Conectar de novo.')
            ->withFragment('integracao');
    }
}
