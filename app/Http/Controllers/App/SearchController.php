<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Empresa do usuário ou, para o admin da plataforma (empresa_id null),
     * a empresa da unidade selecionada na sessão — senão as buscas do
     * admin voltavam sempre vazias.
     */
    private function empresaId(): ?int
    {
        return auth()->user()->empresa_id
            ?? (session('empresa_id') ? (int) session('empresa_id') : null);
    }

    public function clientes(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $clientes = Cliente::where('empresa_id', $this->empresaId())
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

        $produtos = Produto::where('empresa_id', $this->empresaId())
            ->where(function ($query) use ($q) {
                $query->where('descricao', 'like', "%{$q}%")
                      ->orWhere('codigo_interno', 'like', "%{$q}%")
                      ->orWhere('codigo_barras', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%");
            })
            ->where('status', 'ativo')
            ->select('id', 'descricao', 'codigo_interno', 'preco_venda', 'unidade_medida')
            ->with('precos:id,produto_id,modalidade,valor')
            ->limit(10)
            ->get();

        // Orçamento, pedido e venda balcão recebem o "preço cadastrado" da regra da
        // loja: na regra do cartão, o do cartão — também no produto com cartão fixo,
        // que antes vinha com o preço do PIX (01/11/2026)
        $tabela = app(\App\Services\TabelaPrecoService::class);
        $config = \App\Models\ConfiguracaoLoja::daUnidade();
        $produtos = $produtos->map(fn ($p) => [
            'id'             => $p->id,
            'descricao'      => $p->descricao,
            'codigo_interno' => $p->codigo_interno,
            'preco_venda'    => number_format($tabela->precoDoCadastro($p, $config), 2, '.', ''),
            'unidade_medida' => $p->unidade_medida,
            // As 3 tabelas: o pedido repreça o item quando muda a condição (03/11/2026)
            'precos'         => $tabela->precosDoProduto($p, $config),
        ]);

        return response()->json($produtos);
    }

    public function servicos(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $servicos = Servico::where('empresa_id', $this->empresaId())
            ->where(function ($query) use ($q) {
                $query->where('descricao', 'like', "%{$q}%")
                      ->orWhere('codigo_lc116', 'like', "%{$q}%");
            })
            ->where('status', 'ativo')
            ->select('id', 'descricao', 'codigo_lc116', 'valor_padrao')
            ->limit(10)
            ->get();

        return response()->json($servicos);
    }

    public function fornecedores(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $fornecedores = Fornecedor::where('empresa_id', $this->empresaId())
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

        $users = User::where('empresa_id', $this->empresaId())
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
        $empresaId = $this->empresaId();

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
