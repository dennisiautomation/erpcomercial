<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanoController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $planos = Plano::orderBy('ordem')->get();

        return view('admin.planos.index', compact('planos'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        return view('admin.planos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate($this->rules());

        Plano::create($validated);

        return redirect()->route('admin.planos.index')
            ->with('success', 'Plano criado com sucesso.');
    }

    public function edit(Request $request, Plano $plano): View
    {
        abort_unless($request->user()->is_admin, 403);

        return view('admin.planos.edit', compact('plano'));
    }

    public function update(Request $request, Plano $plano): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate($this->rules($plano->id));

        $plano->update($validated);

        return redirect()->route('admin.planos.index')
            ->with('success', 'Plano atualizado com sucesso.');
    }

    public function destroy(Request $request, Plano $plano): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if ($plano->empresas()->count() > 0) {
            return back()->with('error', 'Nao e possivel excluir um plano com empresas vinculadas.');
        }

        $plano->delete();

        return redirect()->route('admin.planos.index')
            ->with('success', 'Plano excluido com sucesso.');
    }

    protected function rules(?int $ignoreId = null): array
    {
        return [
            'nome'                   => ['required', 'string', 'max:255'],
            'slug'                   => ['required', 'string', 'max:255', 'unique:planos,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'descricao'              => ['nullable', 'string'],
            'preco_mensal'           => ['required', 'numeric', 'min:0'],
            'preco_anual'            => ['required', 'numeric', 'min:0'],
            'max_unidades'           => ['required', 'integer', 'min:1'],
            'max_usuarios'           => ['required', 'integer', 'min:1'],
            'max_produtos'           => ['required', 'integer', 'min:1'],
            'max_notas_mes'          => ['required', 'integer', 'min:1'],
            'pdv_habilitado'         => ['boolean'],
            'fiscal_habilitado'      => ['boolean'],
            'multilojas_habilitado'  => ['boolean'],
            'os_habilitado'          => ['boolean'],
            'contratos_habilitado'   => ['boolean'],
            'conciliacao_habilitada' => ['boolean'],
            'dre_habilitado'         => ['boolean'],
            'boletos_habilitado'     => ['boolean'],
            'api_habilitada'         => ['boolean'],
            'dias_trial'             => ['required', 'integer', 'min:0'],
            'ativo'                  => ['boolean'],
            'ordem'                  => ['required', 'integer', 'min:0'],
        ];
    }
}
