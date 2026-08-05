<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanoController extends Controller
{
    /**
     * Plano é um conceito da empresa — admin da plataforma (empresa_id null)
     * não tem plano próprio; gerencia os planos das empresas em /admin.
     */
    private function redirectSemEmpresa(): RedirectResponse
    {
        return redirect()->route('admin.dashboard')
            ->with('warning', 'Seu usuário não está vinculado a uma empresa. Planos das empresas são gerenciados em Admin > Empresas.');
    }

    /**
     * Plano/assinatura é assunto do dono — funcionários não veem valores
     * nem opções de upgrade (pedido do Dennis 05/08). A tela de plano
     * expirado continua aberta a todos (é a tela de bloqueio).
     */
    private function redirectNaoDono(): RedirectResponse
    {
        return redirect()->route('app.dashboard')
            ->with('warning', 'O plano da empresa é gerenciado pelo proprietário da conta.');
    }

    /**
     * Show current plan info + upgrade options.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $empresa = $request->user()->empresa;
        if (! $empresa) {
            return $this->redirectSemEmpresa();
        }
        if (! $request->user()->isDono()) {
            return $this->redirectNaoDono();
        }
        $planoAtual = $empresa->getPlanoAtivo();
        $planos = Plano::ativo()->orderBy('ordem')->get();

        // Usage stats
        $uso = [
            'unidades' => [
                'atual'  => $empresa->unidades()->count(),
                'limite' => $planoAtual?->getLimit('unidades') ?? 0,
            ],
            'usuarios' => [
                'atual'  => $empresa->users()->count(),
                'limite' => $planoAtual?->getLimit('usuarios') ?? 0,
            ],
            'produtos' => [
                'atual'  => $empresa->produtos()->count(),
                'limite' => $planoAtual?->getLimit('produtos') ?? 0,
            ],
            'notas' => [
                'atual'  => $empresa->notasFiscaisDoMes(),
                'limite' => $planoAtual?->getLimit('notas') ?? 0,
            ],
        ];

        return view('app.plano.index', compact('empresa', 'planoAtual', 'planos', 'uso'));
    }

    /**
     * Show "plan expired" page.
     */
    public function expirado(Request $request): View|RedirectResponse
    {
        $empresa = $request->user()->empresa;
        if (! $empresa) {
            return $this->redirectSemEmpresa();
        }
        $planos = Plano::ativo()->orderBy('ordem')->get();

        return view('app.plano.expirado', compact('empresa', 'planos'));
    }

    /**
     * Show plan comparison page (pricing table).
     */
    public function comparar(Request $request): View|RedirectResponse
    {
        $empresa = $request->user()->empresa;
        if (! $empresa) {
            return $this->redirectSemEmpresa();
        }
        if (! $request->user()->isDono()) {
            return $this->redirectNaoDono();
        }
        $planoAtual = $empresa->getPlanoAtivo();
        $planos = Plano::ativo()->orderBy('ordem')->get();

        return view('app.plano.comparar', compact('empresa', 'planoAtual', 'planos'));
    }
}
