<?php

namespace App\Services;

use App\Enums\TipoMovimentacaoEstoque;
use App\Enums\TipoProduto;
use App\Models\GrupoOpcaoItem;
use App\Models\Produto;
use Illuminate\Support\Collection;

/**
 * O que sai do estoque quando um item é vendido (Fase 1 cafeteria, 30/10/2026).
 *
 * - Revenda/insumo sem adicionais: o próprio produto — e quem chama segue o
 *   caminho de sempre (`precisaComposicao()` = false), sem mudar uma linha.
 * - Produzido: os insumos da ficha técnica × quantidade vendida.
 * - Adicionais escolhidos: a ficha de cada adicional × quantidade (adicional
 *   sem ficha não baixa nada — "shot extra" é café, quem tem saldo é o café).
 *
 * O que saiu fica gravado no item (`venda_itens.baixa_componentes`) e é isso
 * que o cancelamento devolve — a ficha pode mudar depois da venda.
 */
class ComposicaoEstoque
{
    /** O item precisa da baixa por componentes, em vez de baixar o próprio produto? */
    public static function precisaComposicao(Produto $produto, Collection|array $opcoes = []): bool
    {
        return $produto->ehProduzido() || collect($opcoes)->isNotEmpty();
    }

    /**
     * Componentes que saem do estoque por este item, somados por produto.
     *
     * @param  Collection<int, GrupoOpcaoItem>|array  $opcoes
     * @return array<int, array{produto_id:int, quantidade:float}>
     */
    public static function componentes(Produto $produto, float $quantidade, Collection|array $opcoes = []): array
    {
        $soma = [];
        $somar = function (int $produtoId, float $qtd) use (&$soma) {
            if ($qtd <= 0) {
                return;
            }
            $soma[$produtoId] = round(($soma[$produtoId] ?? 0) + $qtd, 4);
        };

        if ($produto->ehProduzido()) {
            foreach ($produto->fichaItens()->get() as $linha) {
                $somar((int) $linha->insumo_id, (float) $linha->quantidade * $quantidade);
            }
        } else {
            $somar((int) $produto->id, $quantidade);
        }

        foreach (collect($opcoes) as $opcao) {
            $adicional = $opcao->adicional;
            if (! $adicional) {
                continue;
            }
            foreach ($adicional->fichaItens()->get() as $linha) {
                $somar((int) $linha->insumo_id, (float) $linha->quantidade * $quantidade);
            }
        }

        $lista = [];
        foreach ($soma as $produtoId => $qtd) {
            $lista[] = ['produto_id' => (int) $produtoId, 'quantidade' => (float) $qtd];
        }

        return $lista;
    }

    /**
     * Custo de UMA unidade pela ficha técnica (soma de custo do insumo × quantidade).
     */
    public static function custoDaFicha(Produto $produto): float
    {
        $custo = 0.0;
        foreach ($produto->fichaItens()->with('insumo:id,preco_custo')->get() as $linha) {
            $custo += (float) ($linha->insumo?->preco_custo ?? 0) * (float) $linha->quantidade;
        }

        return round($custo, 2);
    }

    /**
     * Opções escolhidas no PDV, validadas no servidor (o id vem do navegador):
     * cada uma precisa ser de um grupo ativo ligado a ESTE produto, e cada grupo
     * respeita mínimo/máximo.
     *
     * @param  array<int, int|string>  $ids  ids de grupo_opcao_itens
     * @return Collection<int, GrupoOpcaoItem>
     *
     * @throws \DomainException
     */
    public static function resolverOpcoes(Produto $produto, array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn ($v) => (int) $v > 0))));

        $grupos = $produto->gruposOpcoes()
            ->withoutGlobalScopes()
            ->where('grupos_opcoes.empresa_id', $produto->empresa_id)
            ->where('grupos_opcoes.status', 'ativo')
            ->with(['itens' => fn ($q) => $q->withoutGlobalScopes(), 'itens.adicional' => fn ($q) => $q->withoutGlobalScopes()])
            ->get();

        $escolhidas = collect();
        foreach ($grupos as $grupo) {
            $doGrupo = $grupo->itens->filter(fn ($i) => in_array((int) $i->id, $ids, true) && $i->adicional);
            if ($doGrupo->count() < $grupo->minimo) {
                throw new \DomainException("{$produto->descricao}: escolha {$grupo->nome}.");
            }
            if ($grupo->maximo > 0 && $doGrupo->count() > $grupo->maximo) {
                throw new \DomainException("{$produto->descricao}: no máximo {$grupo->maximo} em {$grupo->nome}.");
            }
            $escolhidas = $escolhidas->merge($doGrupo);
        }

        if ($escolhidas->count() !== count($ids)) {
            throw new \DomainException("{$produto->descricao}: uma das opções escolhidas não vale para este produto. Lance o item de novo.");
        }

        return $escolhidas->values();
    }

    /**
     * Snapshot das opções para o item da venda (nome e preço do momento).
     *
     * @param  Collection<int, GrupoOpcaoItem>  $opcoes
     * @param  callable(GrupoOpcaoItem): float  $preco
     * @return array<int, array{id:int, adicional_id:int, grupo:string, descricao:string, preco:float}>
     */
    public static function snapshotOpcoes(Collection $opcoes, callable $preco): array
    {
        return $opcoes->map(fn (GrupoOpcaoItem $o) => [
            'id'           => (int) $o->id,
            'adicional_id' => (int) $o->adicional_id,
            'grupo'        => (string) ($o->grupo?->nome ?? ''),
            'descricao'    => (string) $o->adicional->descricao,
            'preco'        => round($preco($o), 2),
        ])->values()->all();
    }

    /** "Cappuccino + M + Leite de aveia" — o que vai no cupom e na nota. */
    public static function descricaoComOpcoes(string $descricao, array $snapshot): string
    {
        if ($snapshot === []) {
            return $descricao;
        }

        return mb_substr($descricao . ' + ' . implode(' + ', array_column($snapshot, 'descricao')), 0, 255);
    }

    /**
     * Baixa os componentes no estoque informado e devolve o que saiu (para
     * gravar em `baixa_componentes`).
     *
     * @param  array<int, array{produto_id:int, quantidade:float}>  $componentes
     * @param  array<string, mixed>  $atributos  origem_tipo/origem_id/observacoes
     * @return array<int, array{produto_id:int, quantidade:float, estoque_id:int}>
     */
    public static function baixar(int $empresaId, int $unidadeId, int $estoqueId, array $componentes, array $atributos): array
    {
        $saiu = [];
        foreach ($componentes as $c) {
            $insumo = Produto::withoutGlobalScopes()->find($c['produto_id'], ['id', 'preco_custo']);
            SaldoEstoque::registrar(
                $empresaId,
                $unidadeId,
                $estoqueId,
                (int) $c['produto_id'],
                TipoMovimentacaoEstoque::Saida->value,
                -(float) $c['quantidade'],
                $atributos + ['custo_unitario' => (float) ($insumo?->preco_custo ?? 0)]
            );
            $saiu[] = ['produto_id' => (int) $c['produto_id'], 'quantidade' => (float) $c['quantidade'], 'estoque_id' => $estoqueId];
        }

        return $saiu;
    }

    /**
     * Baixa de um item FORA do PDV (balcão, pedido, OS — sem adicionais): se o
     * produto é Produzido, saem os insumos da ficha e o retorno é o que saiu;
     * senão roda `$legado` (a baixa de sempre, intocada) e o retorno é null.
     *
     * @param  array<string, mixed>  $atributos  origem_tipo/origem_id/observacoes
     * @return array<int, array{produto_id:int, quantidade:float, estoque_id:int}>|null
     */
    public static function baixarItem(int $empresaId, int $unidadeId, ?int $estoqueId, int $produtoId, float $quantidade, array $atributos, callable $legado): ?array
    {
        $produto = Produto::withoutGlobalScopes()->find($produtoId);
        if (! $produto || ! $produto->ehProduzido() || ! $estoqueId) {
            $legado();

            return null;
        }

        return static::baixar($empresaId, $unidadeId, $estoqueId, static::componentes($produto, $quantidade), $atributos);
    }

    /**
     * Devolve ao estoque o que um item baixou por componentes (cancelamento).
     *
     * @param  array<int, array{produto_id:int, quantidade:float, estoque_id?:int}>  $baixa
     * @param  array<string, mixed>  $atributos
     */
    public static function devolver(int $empresaId, int $unidadeId, array $baixa, array $atributos): void
    {
        foreach ($baixa as $c) {
            $estoqueId = (int) ($c['estoque_id'] ?? 0) ?: SaldoEstoque::estoqueDeVendaId($unidadeId);
            if (! $estoqueId) {
                continue;
            }
            SaldoEstoque::registrar(
                $empresaId,
                $unidadeId,
                $estoqueId,
                (int) $c['produto_id'],
                TipoMovimentacaoEstoque::Devolucao->value,
                (float) $c['quantidade'],
                $atributos
            );
        }
    }

    /** Tipos que um produto pode usar como insumo na ficha (produzido dentro de produzido não). */
    public static function tiposQueServemDeInsumo(): array
    {
        return [TipoProduto::Insumo->value, TipoProduto::Revenda->value];
    }
}
