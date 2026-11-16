<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaGateway;
use App\Services\Entrega\MelhorEnvioService;
use App\Services\Pagamento\CartaoPedidoService;
use Illuminate\Http\Request;

/**
 * "Pagamento e entrega" — o lojista liga as próprias contas (16/11/2026).
 *
 * Tela: /app/configuracoes/pagamento-entrega (menu Gestão, dono/admin da
 * empresa — permission:integracoes, o mesmo recorte dos tokens da API).
 * Aqui NÃO há regra de gravação: cada ação é repassada ao controller de Admin
 * que já grava aquele provedor (ver Concerns\ContextoIntegracao), com a
 * empresa fixada na do usuário logado. O que muda é só a porta de entrada e
 * para onde a pessoa volta.
 */
class PagamentoEntregaController extends Controller
{
    /** provedor => ação => [controller de Admin, método] */
    private const ACOES = [
        'pix' => [
            'salvar' => [Admin\EmpresaGatewayController::class, 'store'],
            'testar' => [Admin\EmpresaGatewayController::class, 'testar'],
            'webhook' => [Admin\EmpresaGatewayController::class, 'registrarWebhook'],
        ],
        'asaas' => [
            'salvar' => [Admin\EmpresaAsaasController::class, 'store'],
            'testar' => [Admin\EmpresaAsaasController::class, 'testar'],
        ],
        'infinitepay' => [
            'salvar' => [Admin\EmpresaInfinitePayController::class, 'store'],
            'testar' => [Admin\EmpresaInfinitePayController::class, 'testar'],
        ],
        'cartao' => [
            'escolher' => [Admin\AgenteIaController::class, 'cartao'],
        ],
        'melhor-envio' => [
            'salvar' => [Admin\EmpresaMelhorEnvioController::class, 'store'],
            'conectar' => [Admin\EmpresaMelhorEnvioController::class, 'conectar'],
            'testar' => [Admin\EmpresaMelhorEnvioController::class, 'testar'],
            'desconectar' => [Admin\EmpresaMelhorEnvioController::class, 'desconectar'],
        ],
        'uber' => [
            'salvar' => [Admin\EmpresaEntregaController::class, 'store'],
            'testar' => [Admin\EmpresaEntregaController::class, 'testar'],
        ],
    ];

    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        // Admin da plataforma tem empresa_id NULL: ele usa a aba Integração da empresa.
        if (! $empresaId) {
            return redirect()->route('admin.empresas.index')
                ->with('info', 'Escolha a empresa e use a aba Integração.');
        }

        $gateways = EmpresaGateway::where('empresa_id', $empresaId)->get()->keyBy('provedor');
        $asaas = $gateways->get(EmpresaGateway::PROVEDOR_ASAAS);
        $infinite = $gateways->get(EmpresaGateway::PROVEDOR_INFINITEPAY);

        return view('app.pagamento-entrega.index', [
            'pix' => $gateways->get(EmpresaGateway::PROVEDOR_SICREDI_PIX),
            'asaas' => $asaas,
            'infinite' => $infinite,
            'me' => $gateways->get(EmpresaGateway::PROVEDOR_MELHOR_ENVIO),
            'uber' => $gateways->get(EmpresaGateway::PROVEDOR_UBER_DIRECT),
            'meLiberado' => (bool) (MelhorEnvioService::app()['configurado'] ?? false),
            'pacotePadrao' => MelhorEnvioService::PACOTE_PADRAO,
            'cartaoEmUso' => CartaoPedidoService::provedorEmUso($empresaId),
            'cartoesAtivos' => collect([$asaas, $infinite])->filter(fn ($g) => $g?->capacidadeAtiva())->pluck('provedor')->all(),
        ]);
    }

    public function acao(Request $request, string $provedor, string $acao)
    {
        $empresaId = $request->user()->empresa_id;
        abort_unless($empresaId, 403);
        abort_unless(isset(self::ACOES[$provedor][$acao]), 404);

        $empresa = Empresa::findOrFail($empresaId);
        // Marca posta pelo servidor: é ela que libera o controller de Admin
        // para ESTA empresa e só para ela.
        $request->attributes->set('integracao_lojista', $empresa->id);

        [$classe, $metodo] = self::ACOES[$provedor][$acao];

        $resposta = app($classe)->{$metodo}($request, $empresa);

        // "conectar" sai do ERP (login do Melhor Envio); o resto volta para o
        // cartão do provedor na própria tela.
        return $acao === 'conectar' ? $resposta : $resposta->withFragment($provedor);
    }
}
