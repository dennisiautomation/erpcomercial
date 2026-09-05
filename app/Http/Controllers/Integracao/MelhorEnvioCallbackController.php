<?php

namespace App\Http\Controllers\Integracao;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaGateway;
use App\Services\Entrega\MelhorEnvioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Retorno da autorização OAuth do Melhor Envio (05/09/2026).
 *
 * UMA URL para todas as empresas (é a URL do aplicativo IA365, cadastrada no
 * painel do Melhor Envio): quem diz de qual empresa é a autorização é o
 * `state` cifrado que o ERP mandou ao abrir a tela (validade 15 min). O
 * `code` é de uso único e é trocado por tokens na hora. Não exige sessão —
 * o admin normalmente ainda está logado no mesmo navegador e volta para a
 * aba Integração da empresa; se não estiver, cai no login e depois lá.
 */
class MelhorEnvioCallbackController extends Controller
{
    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        try {
            $empresaId = MelhorEnvioService::lerState($state);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.empresas.index')->with('error', 'Melhor Envio: ' . $e->getMessage());
        }

        $empresa = Empresa::find($empresaId);
        if (! $empresa) {
            return redirect()->route('admin.empresas.index')->with('error', 'Melhor Envio: empresa da autorização não encontrada.');
        }

        $voltar = fn (string $tipo, string $msg) => redirect()
            ->route('admin.empresas.show', $empresa)
            ->with($tipo, $msg)
            ->withFragment('integracao');

        if ($request->filled('error')) {
            return $voltar('error', 'O Melhor Envio não autorizou: ' . $request->query('error_description', $request->query('error')));
        }
        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $voltar('error', 'O Melhor Envio voltou sem o código de autorização.');
        }

        $gateway = EmpresaGateway::firstOrCreate(
            ['empresa_id' => $empresa->id, 'provedor' => EmpresaGateway::PROVEDOR_MELHOR_ENVIO],
            ['base_url' => MelhorEnvioService::baseUrl()]
        );

        try {
            $tokens = MelhorEnvioService::trocarCodigo($code);
            $svc = MelhorEnvioService::paraGateway($gateway);
            $svc->gravarTokens($tokens);
            $gateway->ativo = true;
            $gateway->base_url = MelhorEnvioService::baseUrl();
            $gateway->save();
            $me = $svc->testarConexao();
        } catch (\Throwable $e) {
            Log::channel('integracao')->error('Melhor Envio: callback falhou', [
                'empresa_id' => $empresa->id, 'erro' => $e->getMessage(),
            ]);
            $gateway->update(['ultima_falha' => mb_substr($e->getMessage(), 0, 1000)]);

            return $voltar('error', 'Melhor Envio: não consegui trocar a autorização por token — ' . $e->getMessage());
        }

        Log::channel('integracao')->info('Melhor Envio: empresa conectada', [
            'empresa_id' => $empresa->id, 'conta' => $me['email'],
        ]);

        return $voltar('success', 'Melhor Envio conectado à conta ' . ($me['nome'] ?: $me['email']) . '. O Vendedor IA já cota frete para outras cidades.');
    }
}
