<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgenteIaConfig;
use App\Models\Empresa;
use App\Models\EmpresaGateway;
use App\Models\PlataformaConfiguracao;
use App\Services\Entrega\MelhorEnvioService;
use App\Services\Pagamento\CartaoPedidoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Menu Integrações do admin.
 *
 * 25/09/2026: virou o PAINEL de integrações — em cima, toda empresa com agente
 * ou integração e a situação de cada uma (só leitura; configura-se na aba
 * Integração da empresa). Embaixo, o que é da PLATAFORMA (05/09/2026): hoje só
 * o aplicativo "IA365" no Melhor Envio, porque é o único provedor que exige
 * um app da IA365 (OAuth). PIX Sicredi, Asaas, InfinitePay e Uber usam só a
 * conta de cada empresa — não têm configuração da plataforma.
 */
class IntegracoesPlataformaController extends Controller
{
    /** Colunas do painel por empresa (provedor → rótulo), na ordem da aba Integração. */
    private const COLUNAS = [
        EmpresaGateway::PROVEDOR_SICREDI_PIX => 'PIX Sicredi',
        EmpresaGateway::PROVEDOR_ASAAS => 'Asaas',
        EmpresaGateway::PROVEDOR_INFINITEPAY => 'InfinitePay',
        EmpresaGateway::PROVEDOR_UBER_DIRECT => 'Uber Direct',
        EmpresaGateway::PROVEDOR_MELHOR_ENVIO => 'Melhor Envio',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $app = MelhorEnvioService::app();

        // 25/09/2026: painel geral — toda empresa que tem agente ou alguma
        // integração, com a situação de cada uma lado a lado. As integrações
        // são configuradas na aba Integração de cada empresa; aqui só se vê.
        $gateways = EmpresaGateway::all()->groupBy('empresa_id');
        $agentes = AgenteIaConfig::all()->keyBy('empresa_id');
        $ids = $gateways->keys()->merge($agentes->keys())->unique();

        $empresas = Empresa::whereIn('id', $ids)
            ->orderBy('nome_fantasia')
            ->get(['id', 'nome_fantasia', 'razao_social'])
            ->map(function (Empresa $empresa) use ($gateways, $agentes) {
                $porProvedor = ($gateways->get($empresa->id) ?? collect())->keyBy('provedor');

                return [
                    'empresa' => $empresa,
                    'agente' => $agentes->get($empresa->id),
                    'gateways' => $porProvedor,
                    'cartao' => CartaoPedidoService::provedorEmUso($empresa->id),
                    'falhas' => $porProvedor->filter(fn (EmpresaGateway $g) => filled($g->ultima_falha))
                        ->map(fn (EmpresaGateway $g) => $g->ultima_falha)->all(),
                ];
            });

        return view('admin.integracoes.index', [
            'melhorEnvio' => $app,
            'callbackUrl' => MelhorEnvioService::callbackUrl(),
            'scopesPadrao' => MelhorEnvioService::SCOPES_PADRAO,
            'empresas' => $empresas,
            'colunas' => self::COLUNAS,
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
