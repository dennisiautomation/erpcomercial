<?php

namespace App\Services;

use App\Models\Promocao;
use App\Models\PromocaoItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Promoções por período (05/11/2026).
 *
 * Regra de preço (decisão do Dennis, 05/11): o preço promocional é o preço À
 * VISTA (Dinheiro/PIX); débito e crédito levam o acréscimo da regra da loja por
 * cima — TabelaPrecoService::precosDoProduto aplica. Cliente de atacado segue o
 * preço de atacado dele (a promoção não entra no atacado).
 *
 * Um produto só pode estar em UMA promoção por loja e por dia: a sobreposição é
 * bloqueada na hora de salvar (conflitos()).
 *
 * Registrado como `scoped`: o cache por loja/dia vive uma requisição (ou um job).
 */
class PromocaoService
{
    /** @var array<string, Collection<int, PromocaoItem>> "empresa:unidade:dia" => itens por produto_id */
    private array $cache = [];

    /** Item de promoção que vale hoje (ou no dia) para o produto naquela loja. */
    public function itemVigente(int $empresaId, int $unidadeId, int $produtoId, ?CarbonInterface $dia = null): ?PromocaoItem
    {
        if (! $empresaId || ! $unidadeId) {
            return null;
        }

        return $this->itensDaLoja($empresaId, $unidadeId, $dia)->get($produtoId);
    }

    /** @return Collection<int, PromocaoItem> por produto_id */
    public function itensDaLoja(int $empresaId, int $unidadeId, ?CarbonInterface $dia = null): Collection
    {
        $dia = ($dia ?? now())->copy()->startOfDay();
        $chave = "{$empresaId}:{$unidadeId}:{$dia->toDateString()}";

        return $this->cache[$chave] ??= PromocaoItem::query()
            ->whereHas('promocao', fn ($q) => $q->withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->valendoEm($dia)
                ->whereHas('unidades', fn ($u) => $u->withoutGlobalScopes()->where('unidades.id', $unidadeId)))
            ->with(['promocao' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderBy('id')
            ->get()
            ->keyBy('produto_id');
    }

    /**
     * Produtos que já estão em outra promoção, numa das lojas, em algum dia do período.
     *
     * @param  array<int>  $produtoIds
     * @param  array<int>  $unidadeIds
     * @return Collection<int, array{produto_id:int, produto:string, promocao:string, promocao_id:int, periodo:string, lojas:string}>
     */
    public function conflitos(int $empresaId, array $produtoIds, array $unidadeIds, CarbonInterface $inicio, CarbonInterface $fim, ?int $ignorarPromocaoId = null): Collection
    {
        if (! $produtoIds || ! $unidadeIds) {
            return collect();
        }

        $promocoes = Promocao::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->when($ignorarPromocaoId, fn ($q) => $q->whereKeyNot($ignorarPromocaoId))
            ->whereDate('data_inicio', '<=', $fim->toDateString())
            ->whereDate('data_fim', '>=', $inicio->toDateString())
            ->whereHas('unidades', fn ($u) => $u->withoutGlobalScopes()->whereIn('unidades.id', $unidadeIds))
            ->with([
                'unidades' => fn ($u) => $u->withoutGlobalScopes()->whereIn('unidades.id', $unidadeIds),
                'itens' => fn ($i) => $i->whereIn('produto_id', $produtoIds)->with('produto:id,descricao'),
            ])
            ->get()
            // Encerrada antes: só conta até o último dia em que valeu
            ->filter(fn (Promocao $p) => $p->ultimoDia()->gte($inicio->copy()->startOfDay())
                && $p->ultimoDia()->gte($p->data_inicio));

        return $promocoes->flatMap(fn (Promocao $p) => $p->itens->map(fn (PromocaoItem $i) => [
            'produto_id' => (int) $i->produto_id,
            'produto' => (string) ($i->produto->descricao ?? "Produto {$i->produto_id}"),
            'promocao' => $p->nome,
            'promocao_id' => $p->id,
            'periodo' => $p->data_inicio->format('d/m/Y') . ' a ' . $p->ultimoDia()->format('d/m/Y'),
            'lojas' => $p->unidades->pluck('nome')->join(', '),
        ]))->values();
    }
}
