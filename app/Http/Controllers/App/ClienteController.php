<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::query();

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

        $clientes = $query->orderBy('nome_razao_social')->paginate(15)->withQueryString();

        return view('app.clientes.index', compact('clientes'));
    }

    public function create()
    {
        return view('app.clientes.create');
    }

    public function store(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $validated = $request->validate([
            'tipo_pessoa'       => 'required|in:pf,pj',
            'cpf_cnpj'          => [
                'required',
                'string',
                'max:18',
                Rule::unique('clientes')->where('empresa_id', $empresaId)->whereNull('deleted_at'),
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
            'limite_credito'    => 'nullable|numeric|min:0',
            'observacoes'       => 'nullable|string',
        ]);

        $validated['status'] = 'ativo';

        Cliente::create($validated);

        return redirect()->route('app.clientes.index')
            ->with('success', 'Cliente cadastrado com sucesso!');
    }

    public function show(Cliente $cliente)
    {
        $cliente->load(['vendas' => function ($q) {
            $q->with('vendedor:id,name')->latest()->limit(20);
        }, 'contasReceber' => function ($q) {
            $q->latest('vencimento');
        }]);

        return view('app.clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente)
    {
        return view('app.clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $empresaId = auth()->user()->empresa_id;

        $validated = $request->validate([
            'tipo_pessoa'       => 'required|in:pf,pj',
            'cpf_cnpj'          => [
                'required',
                'string',
                'max:18',
                Rule::unique('clientes')->where('empresa_id', $empresaId)->whereNull('deleted_at')->ignore($cliente->id),
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
            'limite_credito'    => 'nullable|numeric|min:0',
            'status'            => 'required|in:ativo,inativo',
            'observacoes'       => 'nullable|string',
        ]);

        $cliente->update($validated);

        return redirect()->route('app.clientes.index')
            ->with('success', 'Cliente atualizado com sucesso!');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('app.clientes.index')
            ->with('success', 'Cliente excluído com sucesso!');
    }
}
