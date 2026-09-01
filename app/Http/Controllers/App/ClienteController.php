<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    /**
     * Regras do CPF/CNPJ. A empresa decide se o documento é obrigatório
     * (`empresas.exige_documento_cadastro`); o default é exigir, então empresa
     * que nunca mexeu na configuração continua como sempre foi.
     */
    private function regrasDocumento(?int $empresaId, ?int $ignoreId = null): array
    {
        $exige = Empresa::find($empresaId)?->exigeDocumentoCadastro() ?? true;

        $unique = Rule::unique('clientes')
            ->where('empresa_id', $empresaId)
            ->whereNull('deleted_at');

        if ($ignoreId) {
            $unique->ignore($ignoreId);
        }

        return [$exige ? 'required' : 'nullable', 'string', 'max:18', $unique];
    }

    /**
     * Documento em branco tem que virar NULL, nunca string vazia: o unique é
     * (empresa_id, cpf_cnpj) e o MySQL aceita vários NULL, mas duas strings
     * vazias colidem — o segundo cliente sem documento levaria "já existe".
     */
    private function normalizarDocumento(Request $request): void
    {
        $doc = trim((string) $request->input('cpf_cnpj'));
        $request->merge(['cpf_cnpj' => $doc === '' ? null : $doc]);
    }

    public function index(Request $request)
    {
        $query = Cliente::query();

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('nome_razao_social', 'like', "%{$busca}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$busca}%")
                  ->orWhere('nome_fantasia', 'like', "%{$busca}%")
                  ->orWhere('email', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tipo_pessoa')) {
            $query->where('tipo_pessoa', $request->tipo_pessoa);
        }

        if ($request->filled('cidade')) {
            $query->where('cidade', 'like', "%{$request->cidade}%");
        }

        $clientes = $query->orderBy('nome_razao_social')->paginate(15)->withQueryString();

        // Contadores para badges
        $totalAtivos = Cliente::where('status', 'ativo')->count();
        $totalInativos = Cliente::where('status', 'inativo')->count();
        $totalBloqueados = Cliente::where('status', 'bloqueado')->count();
        $totalGeral = $totalAtivos + $totalInativos + $totalBloqueados;

        return view('app.clientes.index', compact(
            'clientes',
            'totalAtivos',
            'totalInativos',
            'totalBloqueados',
            'totalGeral',
        ));
    }

    public function create()
    {
        return view('app.clientes.create');
    }

    public function store(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;
        $this->normalizarDocumento($request);

        $validated = $request->validate([
            'tipo_pessoa'       => 'required|in:pf,pj',
            'cpf_cnpj'          => $this->regrasDocumento($empresaId),
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
            'tipo_preco'        => 'nullable|in:varejo,atacado',
            'observacoes'       => 'nullable|string',
        ]);

        $validated['status'] = 'ativo';
        $validated['tipo_preco'] = $validated['tipo_preco'] ?? 'varejo';

        Cliente::create($validated);

        return redirect()->route('app.clientes.index')
            ->with('success', 'Cliente cadastrado com sucesso!');
    }

    public function quickStore(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;
        $this->normalizarDocumento($request);

        $validated = $request->validate([
            'tipo_pessoa'       => 'required|in:pf,pj',
            'cpf_cnpj'          => $this->regrasDocumento($empresaId),
            'nome_razao_social' => 'required|string|max:255',
            'telefone'          => 'nullable|string|max:20',
            'email'             => 'nullable|email|max:255',
        ]);

        $validated['status'] = 'ativo';
        $cliente = Cliente::create($validated);

        return response()->json([
            'id' => $cliente->id,
            'nome_razao_social' => $cliente->nome_razao_social,
            'cpf_cnpj' => $cliente->cpf_cnpj,
            'telefone' => $cliente->telefone,
        ]);
    }

    public function show(Cliente $cliente)
    {
        $cliente->load(['vendas' => function ($q) {
            $q->with('vendedor:id,name')->latest()->limit(20);
        }, 'contasReceber' => function ($q) {
            $q->latest('vencimento');
        }]);

        // Estatisticas do cliente
        $totalCompras = $cliente->vendas->where('status', \App\Enums\StatusVenda::Concluida)->count();
        $valorTotalCompras = $cliente->vendas->where('status', \App\Enums\StatusVenda::Concluida)->sum('total');
        $ultimaCompra = $cliente->vendas->where('status', \App\Enums\StatusVenda::Concluida)->first();
        $saldoDevedor = $cliente->contasReceber->where('status', 'pendente')->sum('valor');

        return view('app.clientes.show', compact(
            'cliente',
            'totalCompras',
            'valorTotalCompras',
            'ultimaCompra',
            'saldoDevedor',
        ));
    }

    public function edit(Cliente $cliente)
    {
        return view('app.clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $empresaId = auth()->user()->empresa_id;
        $this->normalizarDocumento($request);

        $validated = $request->validate([
            'tipo_pessoa'       => 'required|in:pf,pj',
            'cpf_cnpj'          => $this->regrasDocumento($empresaId, $cliente->id),
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
            'tipo_preco'        => 'nullable|in:varejo,atacado',
            'status'            => 'required|in:ativo,inativo,bloqueado',
            'observacoes'       => 'nullable|string',
        ]);

        $validated['tipo_preco'] = $validated['tipo_preco'] ?? 'varejo';

        $cliente->update($validated);

        return redirect()->route('app.clientes.index')
            ->with('success', 'Cliente atualizado com sucesso!');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('app.clientes.index')
            ->with('success', 'Cliente excluido com sucesso!');
    }
}
