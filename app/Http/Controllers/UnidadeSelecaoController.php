<?php

namespace App\Http\Controllers;

use App\Models\Unidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnidadeSelecaoController extends Controller
{
    /**
     * Listar unidades disponiveis para o usuario.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Admin ve todas as unidades ativas
        if ($user->is_admin) {
            $unidades = Unidade::where('status', 'ativo')
                ->with('empresa')
                ->orderBy('nome')
                ->get();
        }
        // Dono ve todas as unidades da empresa
        elseif ($user->isDono()) {
            $unidades = Unidade::where('empresa_id', $user->empresa_id)
                ->where('status', 'ativo')
                ->orderBy('nome')
                ->get();
        }
        // Demais perfis veem apenas as unidades atribuidas
        else {
            $unidades = $user->unidades()
                ->where('status', 'ativo')
                ->orderBy('nome')
                ->get();
        }

        // Se so tem uma unidade, seleciona automaticamente
        if ($unidades->count() === 1) {
            session(['unidade_id' => $unidades->first()->id]);
            return redirect()->route('app.dashboard');
        }

        return view('selecionar-unidade', compact('unidades'));
    }

    /**
     * Gravar unidade na sessao e redirecionar.
     */
    public function selecionar(Request $request): RedirectResponse
    {
        $request->validate([
            'unidade_id' => ['required', 'exists:unidades,id'],
        ]);

        $user = $request->user();
        $unidadeId = $request->input('unidade_id');

        // Verificar se o usuario tem acesso a esta unidade
        if (! $user->is_admin && ! $user->isDono()) {
            $hasAccess = $user->unidades()->where('unidades.id', $unidadeId)->exists();

            if (! $hasAccess) {
                return back()->withErrors(['unidade_id' => 'Voce nao tem acesso a esta unidade.']);
            }
        }

        session(['unidade_id' => $unidadeId]);

        return redirect()->route('app.dashboard');
    }
}
