<?php

namespace App\Services;

use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use Illuminate\Support\Facades\DB;

/**
 * Ponto ÚNICO de leitura de saldo de estoque.
 *
 * Não existe tabela de saldo: o saldo é o `quantidade_posterior` da última
 * movimentação de cada par (estoque, produto) — uma cadeia de saldo corrido.
 * Antes de 12/08/2026 a chave era (unidade, produto); com vários estoques por
 * loja passou a ser (estoque, produto), e o saldo da LOJA é a soma dos estoques
 * dela.
 *
 * Toda leitura/gravação de saldo deve passar por aqui. Derivar na mão foi o que
 * espalhou a regra por 10 arquivos e deixou o relatório somando unidades
 * diferentes por engano.
 */
class SaldoEstoque
{
    /** Saldo de um produto num estoque específico. */
    public static function noEstoque(int $estoqueId, int $produtoId): float
    {
        $ultima = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('estoque_id', $estoqueId)
            ->where('produto_id', $produtoId)
            ->orderByDesc('id')
            ->first(['quantidade_posterior']);

        return $ultima ? (float) $ultima->quantidade_posterior : 0.0;
    }

    /** Saldo de um produto numa loja = soma dos estoques dela. */
    public static function naUnidade(int $unidadeId, int $produtoId): float
    {
        return (float) DB::table('estoque_movimentacoes as e')
            ->whereIn('e.id', function ($q) use ($unidadeId, $produtoId) {
                $q->selectRaw('MAX(id)')
                    ->from('estoque_movimentacoes')
                    ->where('unidade_id', $unidadeId)
                    ->where('produto_id', $produtoId)
                    ->groupBy('estoque_id');
            })
            ->sum('e.quantidade_posterior');
    }

    /**
     * Saldo de um produto em cada estoque de uma loja.
     *
     * @return array<int, float> estoque_id => saldo
     */
    public static function porEstoqueDaUnidade(int $unidadeId, int $produtoId): array
    {
        $saldos = DB::table('estoque_movimentacoes as e')
            ->select('e.estoque_id', 'e.quantidade_posterior')
            ->whereIn('e.id', function ($q) use ($unidadeId, $produtoId) {
                $q->selectRaw('MAX(id)')
                    ->from('estoque_movimentacoes')
                    ->where('unidade_id', $unidadeId)
                    ->where('produto_id', $produtoId)
                    ->groupBy('estoque_id');
            })
            ->pluck('e.quantidade_posterior', 'e.estoque_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        // Estoques ativos sem movimentação nenhuma entram zerados
        $ativos = Estoque::withoutGlobalScopes()
            ->where('unidade_id', $unidadeId)
            ->where('status', 'ativo')
            ->pluck('id');

        foreach ($ativos as $id) {
            $saldos[$id] ??= 0.0;
        }

        return $saldos;
    }

    /**
     * Saldo de TODOS os produtos da empresa, somando os estoques.
     *
     * @return array<int, float> produto_id => saldo
     */
    public static function porProdutoDaEmpresa(int $empresaId): array
    {
        return DB::table('estoque_movimentacoes as e')
            ->selectRaw('e.produto_id, SUM(e.quantidade_posterior) as saldo')
            ->whereIn('e.id', function ($q) use ($empresaId) {
                $q->selectRaw('MAX(id)')
                    ->from('estoque_movimentacoes')
                    ->where('empresa_id', $empresaId)
                    ->groupBy('produto_id', 'estoque_id');
            })
            ->groupBy('e.produto_id')
            ->pluck('saldo', 'produto_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * O estoque de onde a venda baixa nesta loja.
     *
     * Enquanto a loja tiver um só estoque, é sempre o Principal — por isso o
     * PDV não ganhou seletor: a tela operacional não muda.
     */
    public static function estoqueDeVenda(int $unidadeId): ?Estoque
    {
        return Estoque::withoutGlobalScopes()
            ->where('unidade_id', $unidadeId)
            ->where('status', 'ativo')
            ->orderByDesc('permite_venda')
            ->orderByDesc('is_padrao')
            ->orderBy('id')
            ->first();
    }

    /** Só o id, que é o que a maioria dos call sites precisa. */
    public static function estoqueDeVendaId(int $unidadeId): ?int
    {
        return static::estoqueDeVenda($unidadeId)?->id;
    }

    /**
     * Grava uma movimentação mantendo a cadeia anterior→posterior do estoque.
     *
     * @param  array<string, mixed>  $atributos  demais campos da movimentação
     */
    public static function registrar(
        int $empresaId,
        int $unidadeId,
        int $estoqueId,
        int $produtoId,
        string $tipo,
        float $delta,
        array $atributos = []
    ): EstoqueMovimentacao {
        $anterior = static::noEstoque($estoqueId, $produtoId);

        return EstoqueMovimentacao::create(array_merge([
            'empresa_id'           => $empresaId,
            'unidade_id'           => $unidadeId,
            'estoque_id'           => $estoqueId,
            'produto_id'           => $produtoId,
            'tipo'                 => $tipo,
            'quantidade'           => abs($delta),
            'quantidade_anterior'  => $anterior,
            'quantidade_posterior' => $anterior + $delta,
            'user_id'              => auth()->id(),
        ], $atributos));
    }
}
