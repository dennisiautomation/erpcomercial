<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaGateway;
use App\Services\Pix\SicrediPixService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gateway PIX Sicredi por empresa — aba Integração de /admin/empresas/{id}.
 *
 * O certificado (.cer/.pem) e a chave privada (.key SEM SENHA) sobem para
 * storage/app/private/gateways/{empresa} (volume app_storage — sobrevive a
 * rebuild). client_id/client_secret ficam cifrados no banco (cast encrypted).
 * A chave de cert precisa ser legível pelo www-data (o storeAs já grava
 * como o usuário do PHP — sem o problema do chmod 600 do JL).
 */
class EmpresaGatewayController extends Controller
{
    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'chave_pix' => ['nullable', 'string', 'max:77'],
            'expiracao_segundos' => ['nullable', 'integer', 'min:300', 'max:604800'],
            'ativo' => ['nullable', 'boolean'],
            'certificado' => ['nullable', 'file', 'max:64'],
            'chave_privada' => ['nullable', 'file', 'max:64'],
        ]);

        $gateway = EmpresaGateway::firstOrCreate(
            ['empresa_id' => $empresa->id, 'provedor' => EmpresaGateway::PROVEDOR_SICREDI_PIX]
        );

        foreach (['client_id', 'client_secret', 'chave_pix'] as $campo) {
            if (filled($validated[$campo] ?? null)) {
                $gateway->{$campo} = trim($validated[$campo]);
            }
        }

        if (filled($validated['expiracao_segundos'] ?? null)) {
            $gateway->expiracao_segundos = (int) $validated['expiracao_segundos'];
        }

        if ($request->hasFile('certificado')) {
            $gateway->cert_path = $request->file('certificado')
                ->storeAs("gateways/{$empresa->id}", 'sicredi-cert.pem', 'local');
        }

        if ($request->hasFile('chave_privada')) {
            $gateway->key_path = $request->file('chave_privada')
                ->storeAs("gateways/{$empresa->id}", 'sicredi-key.pem', 'local');
        }

        $gateway->ativo = $request->boolean('ativo');
        $gateway->save();

        $aviso = $gateway->ativo && ! $gateway->utilizavel()
            ? ' Atenção: ainda faltam campos (credenciais, chave PIX ou certificado) — o PIX não vai funcionar até completar.'
            : '';

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', 'Gateway PIX salvo.' . $aviso)
            ->with('abrir_integracao', true);
    }

    public function testar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $gateway = EmpresaGateway::where('empresa_id', $empresa->id)
            ->where('provedor', EmpresaGateway::PROVEDOR_SICREDI_PIX)
            ->first();

        if (! $gateway || ! $gateway->utilizavel()) {
            return $this->voltar($empresa, 'error', 'Configure credenciais, chave PIX e certificado antes de testar.');
        }

        $service = new SicrediPixService($gateway);
        $token = $service->getAccessToken();

        if (! $token) {
            return $this->voltar($empresa, 'error', 'Token Sicredi FALHOU — confira client_id/secret e o par certificado/chave (detalhe em "última falha").');
        }

        $webhook = $service->consultarWebhook();
        $webhookInfo = $webhook['success']
            ? 'webhook registrado: ' . ($webhook['data']['webhookUrl'] ?? '?')
            : 'webhook ainda NÃO registrado para a chave';

        return $this->voltar($empresa, 'success', "Token Sicredi OK ({$webhookInfo}).");
    }

    public function registrarWebhook(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $gateway = EmpresaGateway::where('empresa_id', $empresa->id)
            ->where('provedor', EmpresaGateway::PROVEDOR_SICREDI_PIX)
            ->first();

        if (! $gateway || ! $gateway->utilizavel()) {
            return $this->voltar($empresa, 'error', 'Complete a configuração antes de registrar o webhook.');
        }

        // ⚠️ Webhook Sicredi é POR CHAVE PIX (um só): registrar aqui SOBRESCREVE
        // qualquer webhook anterior da mesma chave em outro sistema.
        $url = rtrim(config('app.url'), '/') . '/api/integracao/v1/webhooks/sicredi';
        $resultado = (new SicrediPixService($gateway))->registrarWebhook($url);

        return $resultado['success']
            ? $this->voltar($empresa, 'success', "Webhook registrado no Sicredi: {$url} (entrega em /pix).")
            : $this->voltar($empresa, 'error', 'Falha ao registrar webhook: ' . ($resultado['error'] ?? '?'));
    }

    private function voltar(Empresa $empresa, string $tipo, string $mensagem): RedirectResponse
    {
        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with($tipo, $mensagem)
            ->with('abrir_integracao', true);
    }
}
