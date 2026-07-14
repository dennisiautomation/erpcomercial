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
            $unidades = Unidade::withoutGlobalScopes()
                ->where('status', 'ativa')
                ->with('empresa')
                ->orderBy('nome')
                ->get();
        }
        // Dono e Admin da empresa veem todas as unidades da empresa
        elseif ($user->isDono() || $user->isAdmin()) {
            $unidades = Unidade::withoutGlobalScopes()
                ->where('empresa_id', $user->empresa_id)
                ->where('status', 'ativa')
                ->orderBy('nome')
                ->get();
        }
        // Demais perfis veem apenas as unidades atribuidas
        else {
            $unidades = $user->unidades()
                ->wherePivot('user_id', $user->id)
                ->where('unidades.status', 'ativa')
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

        // Admin da PLATAFORMA pode selecionar qualquer unidade; todos os
        // demais (inclusive dono/admin da empresa) só unidades da própria
        // empresa — evita ler dados fiscais de outro tenant via sessão.
        if (! $user->is_admin) {
            $daEmpresa = Unidade::withoutGlobalScopes()
                ->where('id', $unidadeId)
                ->where('empresa_id', $user->empresa_id)
                ->exists();

            if (! $daEmpresa) {
                return back()->withErrors(['unidade_id' => 'Voce nao tem acesso a esta unidade.']);
            }
        }

        // Perfis restritos: precisa também estar vinculado à unidade
        if (! $user->is_admin && ! $user->isDono() && ! $user->isAdmin()) {
            $hasAccess = $user->unidades()->where('unidades.id', $unidadeId)->exists();

            if (! $hasAccess) {
                return back()->withErrors(['unidade_id' => 'Voce nao tem acesso a esta unidade.']);
            }
        }

        session(['unidade_id' => $unidadeId]);

        return redirect()->route('app.dashboard');
    }
}
