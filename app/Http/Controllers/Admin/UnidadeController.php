<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnidadeController extends Controller
{
    public function index(Request $request, Empresa $empresa): View
    {
        abort_unless($request->user()->is_admin, 403);

        $unidades = $empresa->unidades()
            ->orderBy('nome')
            ->paginate(15);

        return view('admin.unidades.index', compact('empresa', 'unidades'));
    }

    public function create(Request $request, Empresa $empresa): View
    {
        abort_unless($request->user()->is_admin, 403);

        $gerentes = User::where('empresa_id', $empresa->id)
            ->where('status', 'ativo')
            ->orderBy('name')
            ->get();

        return view('admin.unidades.create', compact('empresa', 'gerentes'));
    }

    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'nome'        => ['required', 'string', 'max:255'],
            'cnpj'        => ['nullable', 'string', 'max:18'],
            'ie'          => ['nullable', 'string', 'max:20'],
            'im'          => ['nullable', 'string', 'max:20'],
            'cep'         => ['nullable', 'string', 'max:10'],
            'logradouro'  => ['nullable', 'string', 'max:255'],
            'numero'      => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro'      => ['nullable', 'string', 'max:100'],
            'cidade'      => ['nullable', 'string', 'max:100'],
            'uf'          => ['nullable', 'string', 'size:2'],
            'telefone'    => ['nullable', 'string', 'max:20'],
            'gerente_id'  => ['nullable', 'exists:users,id'],
            'status'      => ['required', 'string'],
        ]);

        $validated['empresa_id'] = $empresa->id;

        $unidade = Unidade::create($validated);

        return redirect()
            ->route('admin.unidades.show', $unidade)
            ->with('success', 'Unidade cadastrada com sucesso.');
    }

    public function show(Request $request, Unidade $unidade): View
    {
        abort_unless($request->user()->is_admin, 403);

        $unidade->load('empresa', 'gerente');

        return view('admin.unidades.show', compact('unidade'));
    }

    public function edit(Request $request, Unidade $unidade): View
    {
        abort_unless($request->user()->is_admin, 403);

        $unidade->load('empresa');

        $gerentes = User::where('empresa_id', $unidade->empresa_id)
            ->where('status', 'ativo')
            ->orderBy('name')
            ->get();

        return view('admin.unidades.edit', compact('unidade', 'gerentes'));
    }

    public function update(Request $request, Unidade $unidade): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'nome'        => ['required', 'string', 'max:255'],
            'cnpj'        => ['nullable', 'string', 'max:18'],
            'ie'          => ['nullable', 'string', 'max:20'],
            'im'          => ['nullable', 'string', 'max:20'],
            'cep'         => ['nullable', 'string', 'max:10'],
            'logradouro'  => ['nullable', 'string', 'max:255'],
            'numero'      => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro'      => ['nullable', 'string', 'max:100'],
            'cidade'      => ['nullable', 'string', 'max:100'],
            'uf'          => ['nullable', 'string', 'size:2'],
            'telefone'    => ['nullable', 'string', 'max:20'],
            'gerente_id'  => ['nullable', 'exists:users,id'],
            'status'      => ['required', 'string'],
        ]);

        $unidade->update($validated);

        return redirect()
            ->route('admin.unidades.show', $unidade)
            ->with('success', 'Unidade atualizada com sucesso.');
    }

    public function destroy(Request $request, Unidade $unidade): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $empresaId = $unidade->empresa_id;
        $unidade->delete();

        return redirect()
            ->route('admin.empresas.unidades.index', $empresaId)
            ->with('success', 'Unidade removida com sucesso.');
    }
}
