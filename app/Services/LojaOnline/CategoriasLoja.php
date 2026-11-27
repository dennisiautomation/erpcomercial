<?php

namespace App\Services\LojaOnline;

use App\Models\LojaCategoria;
use App\Models\LojaOnline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Categorias da VITRINE montadas pelo lojista (27/11/2026).
 *
 * Onde vive: `loja_categorias` (nome, ordem, foto, ligada) + o que vai dentro
 * (`categorias_erp` = categorias do cadastro que entram inteiras; `produtos` =
 * produtos escolhidos um a um). O cadastro de categorias do ERP NÃO muda.
 *
 * Regra única: a loja que tem pelo menos UMA categoria própria ligada mostra SÓ as
 * próprias (menu, bloco "Categorias", filtro da lista, etiqueta do cartão), na ordem
 * que o lojista deu. Loja sem nenhuma continua nas categorias do cadastro.
 *
 * Na vitrine a categoria própria viaja com o id NEGATIVO (`?categoria=-5`): o id
 * positivo continua sendo o da categoria do cadastro, e nenhum link antigo quebra.
 */
class CategoriasLoja
{
    /** @var Collection<int, LojaCategoria>|null */
    private ?Collection $proprias = null;

    public function __construct(private readonly LojaOnline $loja)
    {
    }

    /** Categorias próprias LIGADAS, na ordem do lojista. */
    public function proprias(): Collection
    {
        return $this->proprias ??= LojaCategoria::where('empresa_id', $this->loja->empresa_id)
            ->where('ativa', true)->orderBy('ordem')->orderBy('id')->get();
    }

    public function emUso(): bool
    {
        return $this->proprias()->isNotEmpty();
    }

    public function achar(int $idNegativo): ?LojaCategoria
    {
        return $this->proprias()->firstWhere('id', abs($idNegativo));
    }

    /** Restringe a consulta de produtos ao que está dentro da categoria própria. */
    public function filtrar(Builder $q, ?LojaCategoria $categoria): Builder
    {
        if (! $categoria) {
            return $q->whereRaw('1 = 0');
        }
        $cats = $categoria->idsCategorias();
        $prods = $categoria->idsProdutos();
        if ($cats === [] && $prods === []) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where(function (Builder $w) use ($cats, $prods) {
            if ($cats !== []) {
                $w->whereIn('categoria_id', $cats);
            }
            if ($prods !== []) {
                $w->orWhereIn('id', $prods);
            }
        });
    }

    /** A categoria própria de um produto (a primeira, na ordem do lojista) ou null. */
    public function doProduto(int $produtoId, ?int $categoriaErpId): ?LojaCategoria
    {
        foreach ($this->proprias() as $c) {
            if (in_array($produtoId, $c->idsProdutos(), true) || ($categoriaErpId && in_array((int) $categoriaErpId, $c->idsCategorias(), true))) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Lista para a vitrine (menu, bloco, filtro): só as que têm produto na loja.
     *
     * @param  callable(): Builder  $vitrine  consulta base dos produtos da loja (CatalogoLoja::vitrine)
     * @return Collection<int, array{id: int, nome: string, total: int, foto: ?string}>
     */
    public function paraVitrine(callable $vitrine): Collection
    {
        return $this->proprias()->map(function (LojaCategoria $c) use ($vitrine) {
            $r = $this->filtrar($vitrine(), $c)->selectRaw('COUNT(*) as total, MAX(foto) as foto')->first();

            return [
                'id' => -$c->id,
                'nome' => $c->nome,
                'total' => (int) ($r->total ?? 0),
                'foto' => LojaContexto::arquivo($c->foto ?: ($r->foto ?? null)),
            ];
        })->filter(fn (array $c) => $c['total'] > 0)->values();
    }
}
