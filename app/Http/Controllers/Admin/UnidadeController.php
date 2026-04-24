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

        $validated = $request->validate(self::validationRules(), self::validationMessages());

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

        $validated = $request->validate(self::validationRules(), self::validationMessages());

        $unidade->update($validated);

        return redirect()
            ->route('admin.unidades.show', $unidade)
            ->with('success', 'Unidade atualizada com sucesso.');
    }

    /**
     * Regras de validação compartilhadas entre store e update.
     * Campos NOT NULL no schema são required para evitar erro 500 no banco.
     */
    public static function validationRules(): array
    {
        return [
            'nome'        => ['required', 'string', 'max:255'],
            'cnpj'        => ['required', 'string', 'max:18'],
            'ie'          => ['nullable', 'string', 'max:20'],
            'im'          => ['nullable', 'string', 'max:20'],
            'cep'         => ['required', 'string', 'max:10'],
            'logradouro'  => ['required', 'string', 'max:255'],
            'numero'      => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro'      => ['required', 'string', 'max:100'],
            'cidade'      => ['required', 'string', 'max:100'],
            'uf'          => ['required', 'string', 'size:2'],
            'telefone'    => ['required', 'string', 'max:20'],
            'gerente_id'  => ['nullable', 'exists:users,id'],
            'status'      => ['required', 'in:ativa,inativa,em_implantacao'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'nome.required'       => 'Informe o nome da unidade (ex: Matriz, Filial Centro).',
            'cnpj.required'       => 'Informe o CNPJ da unidade. Use o mesmo da empresa se a unidade não tem CNPJ próprio.',
            'cep.required'        => 'Informe o CEP.',
            'logradouro.required' => 'Informe o logradouro (rua/avenida).',
            'numero.required'     => 'Informe o número.',
            'bairro.required'     => 'Informe o bairro.',
            'cidade.required'     => 'Informe a cidade.',
            'uf.required'         => 'Selecione o estado (UF).',
            'telefone.required'   => 'Informe um telefone de contato.',
            'status.required'     => 'Selecione o status (ativa/inativa/em implantação).',
        ];
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
