<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanoController extends Controller
{
    /**
     * Show current plan info + upgrade options.
     */
    public function index(Request $request): View
    {
        $empresa = $request->user()->empresa;
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
    public function expirado(Request $request): View
    {
        $empresa = $request->user()->empresa;
        $planos = Plano::ativo()->orderBy('ordem')->get();

        return view('app.plano.expirado', compact('empresa', 'planos'));
    }

    /**
     * Show plan comparison page (pricing table).
     */
    public function comparar(Request $request): View
    {
        $empresa = $request->user()->empresa;
        $planoAtual = $empresa->getPlanoAtivo();
        $planos = Plano::ativo()->orderBy('ordem')->get();

        return view('app.plano.comparar', compact('empresa', 'planoAtual', 'planos'));
    }
}
