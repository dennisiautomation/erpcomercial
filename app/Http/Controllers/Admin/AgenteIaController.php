<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IndexarEmpresaAgenteJob;
use App\Models\AgenteIaConfig;
use App\Models\Empresa;
use App\Models\EmpresaGateway;
use App\Services\Pagamento\CartaoPedidoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ativação do Agente IA por empresa — aba Integração de /admin/empresas/{id}.
 *
 * Ativar dispara a indexação inicial (embeddings) na fila; desativar só
 * congela indexação e config, sem apagar o índice (barato e inofensivo).
 */
class AgenteIaController extends Controller
{
    public function toggle(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $config = AgenteIaConfig::firstOrCreate(['empresa_id' => $empresa->id]);

        $config->update(['ativo' => ! $config->ativo]);

        if ($config->ativo) {
            IndexarEmpresaAgenteJob::dispatch($empresa->id);

            $mensagem = 'Agente IA ativado — a indexação dos produtos começou (acompanhe nesta aba).';
        } else {
            $mensagem = 'Agente IA desativado — produtos desta empresa não serão mais indexados.';
        }

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', $mensagem)
            ->with('abrir_integracao', true);
    }

    /**
     * Qual cartão o agente usa quando a empresa tem Asaas E InfinitePay
     * ligados (25/09/2026). Trocar avisa o app.ia365 (AgenteIaConfigObserver),
     * que troca o treinamento do agente sozinho.
     */
    public function cartao(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'provedor_cartao' => ['required', 'string', 'in:' . implode(',', EmpresaGateway::PROVEDORES_CARTAO)],
        ]);

        $gateway = EmpresaGateway::where('empresa_id', $empresa->id)
            ->where('provedor', $validated['provedor_cartao'])
            ->first();
        if (! $gateway?->capacidadeAtiva()) {
            return redirect()->route('admin.empresas.show', $empresa)
                ->with('error', CartaoPedidoService::rotulo($validated['provedor_cartao']) . ' não está ativo e configurado — ligue o card antes de escolher.')
                ->with('abrir_integracao', true);
        }

        $config = AgenteIaConfig::firstOrCreate(['empresa_id' => $empresa->id]);
        $config->update(['provedor_cartao' => $validated['provedor_cartao']]);

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', 'O agente passa a mandar o link de cartão da ' . CartaoPedidoService::rotulo($validated['provedor_cartao']) . '.')
            ->with('abrir_integracao', true);
    }

    public function reindexar(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        abort_unless(AgenteIaConfig::ativaPara($empresa->id), 404);

        IndexarEmpresaAgenteJob::dispatch($empresa->id);

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', 'Reindexação enviada para a fila.')
            ->with('abrir_integracao', true);
    }
}
