<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IndexarEmpresaAgenteJob;
use App\Models\AgenteIaConfig;
use App\Models\Empresa;
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
