<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function clientes(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $clientes = Cliente::where('empresa_id', auth()->user()->empresa_id)
            ->where(function ($query) use ($q) {
                $query->where('nome_razao_social', 'like', "%{$q}%")
                      ->orWhere('cpf_cnpj', 'like', "%{$q}%")
                      ->orWhere('nome_fantasia', 'like', "%{$q}%");
            })
            ->where('status', 'ativo')
            ->select('id', 'nome_razao_social', 'cpf_cnpj', 'telefone')
            ->limit(10)
            ->get();

        return response()->json($clientes);
    }

    public function produtos(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $produtos = Produto::where('empresa_id', auth()->user()->empresa_id)
            ->where(function ($query) use ($q) {
                $query->where('descricao', 'like', "%{$q}%")
                      ->orWhere('codigo_interno', 'like', "%{$q}%")
                      ->orWhere('codigo_barras', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%");
            })
            ->where('status', 'ativo')
            ->select('id', 'descricao', 'codigo_interno', 'preco_venda', 'unidade_medida')
            ->limit(10)
            ->get();

        return response()->json($produtos);
    }

    public function fornecedores(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $fornecedores = Fornecedor::where('empresa_id', auth()->user()->empresa_id)
            ->where(function ($query) use ($q) {
                $query->where('razao_social', 'like', "%{$q}%")
                      ->orWhere('cpf_cnpj', 'like', "%{$q}%");
            })
            ->select('id', 'razao_social', 'cpf_cnpj')
            ->limit(10)
            ->get();

        return response()->json($fornecedores);
    }

    public function vendedores(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $users = User::where('empresa_id', auth()->user()->empresa_id)
            ->where('name', 'like', "%{$q}%")
            ->whereIn('perfil', ['vendedor', 'gerente', 'dono'])
            ->where('status', 'ativo')
            ->select('id', 'name', 'perfil')
            ->limit(10)
            ->get();

        return response()->json($users);
    }

    public function global(Request $request): JsonResponse
    {
        $q = $request->input('q', '');
        $empresaId = auth()->user()->empresa_id;

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->where(fn ($query) => $query->where('nome_razao_social', 'like', "%{$q}%")->orWhere('cpf_cnpj', 'like', "%{$q}%"))
            ->limit(5)->get()
            ->map(fn ($c) => ['label' => $c->nome_razao_social, 'detail' => $c->cpf_cnpj, 'url' => route('app.clientes.show', $c)]);

        $produtos = Produto::where('empresa_id', $empresaId)
            ->where(fn ($query) => $query->where('descricao', 'like', "%{$q}%")->orWhere('codigo_barras', 'like', "%{$q}%")->orWhere('codigo_interno', 'like', "%{$q}%"))
            ->limit(5)->get()
            ->map(fn ($p) => ['label' => $p->descricao, 'detail' => 'R$ ' . number_format($p->preco_venda, 2, ',', '.'), 'url' => route('app.produtos.show', $p)]);

        $vendas = Venda::where('empresa_id', $empresaId)
            ->where('numero', 'like', "%{$q}%")
            ->limit(5)->get()
            ->map(fn ($v) => ['label' => "Venda #{$v->numero}", 'detail' => 'R$ ' . number_format($v->total, 2, ',', '.'), 'url' => route('app.vendas.show', $v)]);

        return response()->json(['clientes' => $clientes, 'produtos' => $produtos, 'vendas' => $vendas]);
    }
}
