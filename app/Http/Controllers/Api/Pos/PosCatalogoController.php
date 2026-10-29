<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\App\ClienteController;
use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ConfiguracaoLoja;
use App\Models\Produto;
use App\Services\Pos\PosPermissoes;
use App\Services\SaldoEstoque;
use App\Services\TabelaPrecoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Busca de produtos e clientes para o app POS (sempre da empresa do operador). */
class PosCatalogoController extends Controller
{
    public const ORDENS = ['nome', 'mais_vendidos', 'menor_preco', 'maior_preco', 'estoque'];

    /**
     * GET produtos?q=&categoria_id=&pagina=&ordem=
     * `q` casa código de barras/interno EXATO primeiro (bipe do leitor) e depois
     * descrição. Preço das 3 tabelas (dinheiro/PIX, débito, crédito) + saldo da loja.
     * `ordem`: nome (padrão) · mais_vendidos (90 dias, nesta loja) · menor_preco ·
     * maior_preco · estoque (maior saldo na loja). Só leitura: não exige caixa aberto.
     */
    public function produtos(Request $request, TabelaPrecoService $tabelas): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'integer'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'ordem' => ['nullable', 'string', 'in:' . implode(',', self::ORDENS)],
        ]);

        $empresaId = (int) session('empresa_id');
        $q = trim((string) $request->input('q', ''));
        $porPagina = 30;
        $pagina = max(1, (int) $request->input('pagina', 1));

        $query = Produto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->with('precos:id,produto_id,modalidade,valor');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('codigo_barras', $q)
                    ->orWhere('codigo_interno', $q)
                    ->orWhere('descricao', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%');
            })->orderByRaw('(codigo_barras = ? OR codigo_interno = ?) DESC', [$q, $q]);
        }
        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', (int) $request->categoria_id);
        }

        $total = (clone $query)->count();
        $this->ordenar($query, (string) $request->input('ordem', 'nome'), (int) session('unidade_id'));
        $produtos = $query->orderBy('descricao')->orderBy('id')
            ->skip(($pagina - 1) * $porPagina)->take($porPagina)
            ->get();

        $config = ConfiguracaoLoja::daUnidade($empresaId, (int) session('unidade_id'));

        return response()->json([
            'produtos' => $produtos->map(fn ($p) => $this->produtoJson($p, $tabelas, $config))->values(),
            'pagina' => $pagina,
            'tem_mais' => $pagina * $porPagina < $total,
            'total' => $total,
        ]);
    }

    public function produto(int $id, TabelaPrecoService $tabelas): JsonResponse
    {
        $p = Produto::withoutGlobalScopes()
            ->where('empresa_id', (int) session('empresa_id'))
            ->with('precos:id,produto_id,modalidade,valor')
            ->findOrFail($id);

        $config = ConfiguracaoLoja::daUnidade((int) session('empresa_id'), (int) session('unidade_id'));

        return response()->json($this->produtoJson($p, $tabelas, $config));
    }

    public function categorias(): JsonResponse
    {
        $empresaId = (int) session('empresa_id');

        // Só categorias que têm produto ativo: chip vazio no POS é toque perdido.
        $categorias = Categoria::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', Produto::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)->where('status', 'ativo')
                ->whereNotNull('categoria_id')->select('categoria_id'))
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return response()->json(['categorias' => $categorias]);
    }

    /** GET clientes?q= — nome, CPF/CNPJ (com ou sem máscara) ou telefone. */
    public function clientes(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $q = trim($request->q);
        $digitos = preg_replace('/\D/', '', $q);

        $clientes = Cliente::withoutGlobalScopes()
            ->where('empresa_id', (int) session('empresa_id'))
            ->where(function ($w) use ($q, $digitos) {
                $w->where('nome_razao_social', 'like', "%{$q}%");
                if (strlen($digitos) >= 3) {
                    $w->orWhere('cpf_cnpj', 'like', "%{$digitos}%")
                        ->orWhere('telefone', 'like', "%{$digitos}%");
                }
            })
            ->orderBy('nome_razao_social')
            ->limit(20)
            ->get(['id', 'nome_razao_social', 'cpf_cnpj', 'telefone', 'tipo_pessoa', 'email']);

        return response()->json(['clientes' => $clientes]);
    }

    /** POST clientes — cadastro rápido, mesma validação do ERP (ClienteController::quickStore). */
    public function criarCliente(Request $request): JsonResponse
    {
        if (! PosPermissoes::pode($request->user(), 'clientes', 'criar')) {
            return response()->json(['erro' => 'Seu perfil não cadastra clientes.'], 403);
        }

        return app(ClienteController::class)->quickStore($request);
    }

    /** Critério principal; o desempate por descrição/id fica no chamador (paginação estável). */
    private function ordenar($query, string $ordem, int $unidadeId): void
    {
        switch ($ordem) {
            case 'menor_preco':
                $query->orderBy('preco_venda');
                break;
            case 'maior_preco':
                $query->orderByDesc('preco_venda');
                break;
            case 'mais_vendidos':
                $query->orderByDesc(DB::table('venda_itens as vi')
                    ->join('vendas as v', 'v.id', '=', 'vi.venda_id')
                    ->whereColumn('vi.produto_id', 'produtos.id')
                    ->where('v.unidade_id', $unidadeId)
                    ->where('v.status', 'concluida')
                    ->where('v.created_at', '>=', now()->subDays(90))
                    ->selectRaw('COALESCE(SUM(vi.quantidade), 0)'));
                break;
            case 'estoque':
                // Mesmo saldo do SaldoEstoque::naUnidade: última movimentação de cada estoque da loja.
                $query->orderByRaw('(SELECT COALESCE(SUM(e.quantidade_posterior), 0) FROM estoque_movimentacoes e
                    WHERE e.id IN (SELECT MAX(m.id) FROM estoque_movimentacoes m
                        WHERE m.unidade_id = ? AND m.produto_id = produtos.id GROUP BY m.estoque_id)) DESC', [$unidadeId]);
                break;
        }
    }

    private function produtoJson(Produto $p, TabelaPrecoService $tabelas, ConfiguracaoLoja $config): array
    {
        $unidadeId = (int) session('unidade_id');

        return [
            'id' => $p->id,
            'descricao' => $p->descricao,
            'codigo_interno' => $p->codigo_interno,
            'codigo_barras' => $p->codigo_barras,
            'unidade_medida' => $p->unidade_medida,
            'categoria_id' => $p->categoria_id,
            'preco' => (float) $p->preco_venda,
            'precos' => $tabelas->precosDoProduto($p, $config),
            'estoque' => $unidadeId ? SaldoEstoque::naUnidade($unidadeId, (int) $p->id) : null,
            'foto_url' => $p->foto ? url('storage/' . ltrim($p->foto, '/')) : null,
        ];
    }
}
