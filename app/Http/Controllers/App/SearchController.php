<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\User;
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
}
