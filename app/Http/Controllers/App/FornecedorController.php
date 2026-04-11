<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Fornecedor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FornecedorController extends Controller
{
    public function index(Request $request)
    {
        $query = Fornecedor::query();

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('nome_razao_social', 'like', "%{$busca}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$busca}%")
                  ->orWhere('nome_fantasia', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $fornecedores = $query->orderBy('nome_razao_social')->paginate(15)->withQueryString();

        return view('app.fornecedores.index', compact('fornecedores'));
    }

    public function create()
    {
        return view('app.fornecedores.create');
    }

    public function store(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $validated = $request->validate([
            'tipo_pessoa'       => 'required|in:PF,PJ',
            'cpf_cnpj'          => [
                'required',
                'string',
                'max:18',
                Rule::unique('fornecedores')->where('empresa_id', $empresaId)->whereNull('deleted_at'),
            ],
            'nome_razao_social' => 'required|string|max:255',
            'nome_fantasia'     => 'nullable|string|max:255',
            'ie'                => 'nullable|string|max:20',
            'cep'               => 'nullable|string|max:9',
            'logradouro'        => 'nullable|string|max:255',
            'numero'            => 'nullable|string|max:20',
            'complemento'       => 'nullable|string|max:255',
            'bairro'            => 'nullable|string|max:255',
            'cidade'            => 'nullable|string|max:255',
            'uf'                => 'nullable|string|max:2',
            'telefone'          => 'nullable|string|max:20',
            'whatsapp'          => 'nullable|string|max:20',
            'email'             => 'nullable|email|max:255',
            'observacoes'       => 'nullable|string',
        ]);

        $validated['status'] = 'ativo';

        Fornecedor::create($validated);

        return redirect()->route('app.fornecedores.index')
            ->with('success', 'Fornecedor cadastrado com sucesso!');
    }

    public function show(Fornecedor $fornecedore)
    {
        $fornecedore->load(['contasPagar' => function ($q) {
            $q->latest('vencimento')->limit(20);
        }]);

        return view('app.fornecedores.show', compact('fornecedore'));
    }

    public function edit(Fornecedor $fornecedore)
    {
        return view('app.fornecedores.edit', compact('fornecedore'));
    }

    public function update(Request $request, Fornecedor $fornecedore)
    {
        $empresaId = auth()->user()->empresa_id;

        $validated = $request->validate([
            'tipo_pessoa'       => 'required|in:PF,PJ',
            'cpf_cnpj'          => [
                'required',
                'string',
                'max:18',
                Rule::unique('fornecedores')->where('empresa_id', $empresaId)->whereNull('deleted_at')->ignore($fornecedore->id),
            ],
            'nome_razao_social' => 'required|string|max:255',
            'nome_fantasia'     => 'nullable|string|max:255',
            'ie'                => 'nullable|string|max:20',
            'cep'               => 'nullable|string|max:9',
            'logradouro'        => 'nullable|string|max:255',
            'numero'            => 'nullable|string|max:20',
            'complemento'       => 'nullable|string|max:255',
            'bairro'            => 'nullable|string|max:255',
            'cidade'            => 'nullable|string|max:255',
            'uf'                => 'nullable|string|max:2',
            'telefone'          => 'nullable|string|max:20',
            'whatsapp'          => 'nullable|string|max:20',
            'email'             => 'nullable|email|max:255',
            'status'            => 'required|in:ativo,inativo',
            'observacoes'       => 'nullable|string',
        ]);

        $fornecedore->update($validated);

        return redirect()->route('app.fornecedores.index')
            ->with('success', 'Fornecedor atualizado com sucesso!');
    }

    public function destroy(Fornecedor $fornecedore)
    {
        $fornecedore->delete();

        return redirect()->route('app.fornecedores.index')
            ->with('success', 'Fornecedor excluído com sucesso!');
    }
}
