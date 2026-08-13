<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaGateway;
use App\Services\Entrega\UberDirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Entrega Uber Direct por empresa (Fase 3, 13/08/2026) — aba Integração de
 * /admin/empresas/{id}. Cada empresa usa as PRÓPRIAS credenciais Uber
 * (client_id/client_secret cifrados; customer_id/faixas de CEP/janelas no
 * config JSON). Porte da integração validada no China Mix.
 */
class EmpresaEntregaController extends Controller
{
    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'string', 'max:255'],
            'ceps' => ['nullable', 'string', 'max:500'],
            'hora_inicio' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'hora_fim' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'hora_inicio_sab' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'hora_fim_sab' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $gateway = EmpresaGateway::firstOrCreate(
            ['empresa_id' => $empresa->id, 'provedor' => EmpresaGateway::PROVEDOR_UBER_DIRECT],
            ['base_url' => 'https://api.uber.com/v1']
        );

        foreach (['client_id', 'client_secret'] as $campo) {
            if (filled($validated[$campo] ?? null)) {
                $gateway->{$campo} = trim($validated[$campo]);
            }
        }

        $config = $gateway->config ?? [];
        foreach (['customer_id', 'ceps'] as $campo) {
            if ($request->has($campo)) {
                $config[$campo] = trim((string) ($validated[$campo] ?? ''));
            }
        }
        foreach (['hora_inicio', 'hora_fim', 'hora_inicio_sab', 'hora_fim_sab'] as $campo) {
            if (filled($validated[$campo] ?? null)) {
                $config[$campo] = (float) $validated[$campo];
            }
        }
        $gateway->config = $config;
        $gateway->ativo = $request->boolean('ativo');
        $gateway->save();

        $incompleto = $gateway->ativo && (blank($gateway->client_id) || blank($gateway->client_secret) || blank($config['customer_id'] ?? null));

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', 'Entrega Uber Direct salva.' . ($incompleto ? ' Atenção: faltam credenciais/customer_id — o despacho não vai funcionar até completar.' : ''))
            ->withFragment('integracao');
    }

    public function testar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $gateway = EmpresaGateway::where('empresa_id', $empresa->id)
            ->where('provedor', EmpresaGateway::PROVEDOR_UBER_DIRECT)
            ->first();

        if (! $gateway || blank($gateway->client_id) || blank($gateway->client_secret)) {
            return redirect()->route('admin.empresas.show', $empresa)
                ->with('error', 'Preencha client_id e client_secret antes de testar.')
                ->withFragment('integracao');
        }

        try {
            (new UberDirectService($gateway))->testarConexao();
            $gateway->update(['ultima_falha' => null]);
            $msg = 'Conexão com o Uber Direct OK (token emitido).';

            return redirect()->route('admin.empresas.show', $empresa)
                ->with('success', $msg)->withFragment('integracao');
        } catch (\Throwable $e) {
            $gateway->update(['ultima_falha' => mb_substr($e->getMessage(), 0, 500)]);

            return redirect()->route('admin.empresas.show', $empresa)
                ->with('error', 'Falha na conexão com o Uber: ' . $e->getMessage())
                ->withFragment('integracao');
        }
    }
}
