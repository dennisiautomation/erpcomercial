<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgenteIaConfig;
use App\Models\Empresa;
use App\Models\EmpresaGateway;
use App\Services\Pagamento\CartaoPedidoService;
use App\Services\Pagamento\InfinitePayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * InfinitePay por empresa (25/09/2026) — cartão/PIX por link no pedido do
 * agente. A única credencial é a InfiniteTag DA EMPRESA (cada cliente cola a
 * sua; o campo nasce vazio). O webhook_token é gerado aqui e vai no caminho
 * do webhook de cada link — não há nada para cadastrar no painel da InfinitePay.
 */
class EmpresaInfinitePayController extends Controller
{
    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        // Limpa o que o copiar/colar traz (invisíveis, traço tipográfico) ANTES de validar.
        if ($request->filled('handle')) {
            $request->merge(['handle' => InfinitePayService::normalizarHandle($request->input('handle'))]);
        }

        $validated = $request->validate([
            'handle' => ['nullable', 'string', 'max:80', 'regex:/^\\s*\\$?[A-Za-z0-9._-]+\\s*$/'],
            'ativo' => ['nullable', 'boolean'],
        ], [
            'handle.regex' => 'A InfiniteTag só tem letras, números, ponto, hífen ou sublinhado (o $ do começo é opcional).',
        ]);

        $emUsoAntes = CartaoPedidoService::provedorEmUso($empresa->id);

        $gateway = EmpresaGateway::firstOrCreate(
            ['empresa_id' => $empresa->id, 'provedor' => EmpresaGateway::PROVEDOR_INFINITEPAY],
            ['base_url' => config('services.infinitepay.base_url')]
        );

        $config = $gateway->config ?? [];
        if (filled($validated['handle'] ?? null)) {
            $config['handle'] = InfinitePayService::normalizarHandle($validated['handle']);
        }
        if (blank($config['webhook_token'] ?? null)) {
            $config['webhook_token'] = Str::random(40);
        }
        $gateway->config = $config;
        $gateway->ativo = $request->boolean('ativo');
        $gateway->save();
        CartaoPedidoService::fixarEscolhaSeVazia($empresa->id, $emUsoAntes);

        $aviso = '';
        if ($gateway->ativo && blank($config['handle'] ?? null)) {
            $aviso = ' Atenção: falta a InfiniteTag — enquanto isso o agente não oferece a InfinitePay.';
        } elseif ($gateway->capacidadeAtiva()) {
            $outro = EmpresaGateway::where('empresa_id', $empresa->id)
                ->where('provedor', EmpresaGateway::PROVEDOR_ASAAS)
                ->first();
            $escolhido = AgenteIaConfig::where('empresa_id', $empresa->id)->value('provedor_cartao');
            if ($outro?->capacidadeAtiva() && $escolhido !== EmpresaGateway::PROVEDOR_INFINITEPAY) {
                $aviso = ' O Asaas também está ativo: escolha em "Cartão usado pelo agente" qual dos dois vale.';
            }
        }

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', 'InfinitePay salva.' . $aviso)
            ->with('abrir_integracao', true);
    }

    public function testar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $gateway = EmpresaGateway::where('empresa_id', $empresa->id)
            ->where('provedor', EmpresaGateway::PROVEDOR_INFINITEPAY)
            ->first();

        if (! $gateway || blank($gateway->config['handle'] ?? null)) {
            return redirect()->route('admin.empresas.show', $empresa)
                ->with('error', 'Salve a InfiniteTag da empresa antes de testar.')
                ->with('abrir_integracao', true);
        }

        try {
            $link = (new InfinitePayService($gateway))->testar();
            $gateway->forceFill(['ultima_falha' => null])->saveQuietly();

            return redirect()->route('admin.empresas.show', $empresa)
                ->with('success', 'InfinitePay OK — link de teste de R$ 1,00 gerado (não precisa pagar): ' . $link)
                ->with('abrir_integracao', true);
        } catch (\Throwable $e) {
            $gateway->forceFill(['ultima_falha' => mb_substr($e->getMessage(), 0, 500)])->saveQuietly();

            return redirect()->route('admin.empresas.show', $empresa)
                ->with('error', 'Falha na InfinitePay: ' . $e->getMessage())
                ->with('abrir_integracao', true);
        }
    }
}
