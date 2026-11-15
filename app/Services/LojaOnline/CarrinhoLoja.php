<?php

namespace App\Services\LojaOnline;

use App\Models\LojaOnline;
use Illuminate\Support\Collection;

/**
 * Carrinho da loja online — vive na SESSÃO do visitante, por loja (14/11/2026).
 *
 * Guarda só {produto_id: quantidade}. Preço, nome e saldo são relidos do
 * catálogo a cada tela: produto que saiu da loja some do carrinho sozinho e
 * quantidade acima do saldo é reduzida, com aviso — o carrinho nunca promete o
 * que a loja não tem mais.
 */
class CarrinhoLoja
{
    public const MAXIMO_POR_ITEM = 999;

    public const MAXIMO_ITENS = 30;

    /** Avisos gerados na última leitura (produto removido, quantidade reduzida). */
    public array $avisos = [];

    private CatalogoLoja $catalogo;

    public function __construct(private readonly LojaOnline $loja, ?CatalogoLoja $catalogo = null)
    {
        $this->catalogo = $catalogo ?? new CatalogoLoja($loja);
    }

    private function chave(): string
    {
        return 'loja_carrinho.' . $this->loja->id;
    }

    /** @return array<int, int> */
    private function bruto(): array
    {
        $dados = session($this->chave(), []);

        return is_array($dados) ? array_map('intval', $dados) : [];
    }

    private function gravar(array $itens): void
    {
        session([$this->chave() => $itens]);
    }

    public function quantidadeTotal(): int
    {
        return array_sum($this->bruto());
    }

    /** Soma a quantidade ao que já está no carrinho. Devolve a quantidade final do item. */
    public function adicionar(int $produtoId, int $quantidade = 1): int
    {
        $itens = $this->bruto();
        if (! isset($itens[$produtoId]) && count($itens) >= self::MAXIMO_ITENS) {
            return 0;
        }
        $itens[$produtoId] = max(1, min(self::MAXIMO_POR_ITEM, ($itens[$produtoId] ?? 0) + max(1, $quantidade)));
        $this->gravar($itens);

        return $itens[$produtoId];
    }

    public function definir(int $produtoId, int $quantidade): void
    {
        $itens = $this->bruto();
        if ($quantidade <= 0) {
            unset($itens[$produtoId]);
        } elseif (isset($itens[$produtoId])) {
            $itens[$produtoId] = min(self::MAXIMO_POR_ITEM, $quantidade);
        }
        $this->gravar($itens);
    }

    public function remover(int $produtoId): void
    {
        $this->definir($produtoId, 0);
    }

    public function limpar(): void
    {
        session()->forget($this->chave());
    }

    /**
     * Itens conferidos contra o catálogo de agora.
     *
     * @return Collection<int, array<string, mixed>>  cada item: dados do cartão de vitrine + quantidade, total, produto (model)
     */
    public function itens(): Collection
    {
        $this->avisos = [];
        $bruto = $this->bruto();
        if ($bruto === []) {
            return collect();
        }

        $produtos = $this->catalogo->base()->whereIn('id', array_keys($bruto))->get()->keyBy('id');
        $vitrine = $this->catalogo->paraVitrine($produtos->values())->keyBy('id');
        $semEstoque = (bool) $this->loja->opcao('vender_sem_estoque');

        $itens = collect();
        $corrigido = $bruto;

        foreach ($bruto as $id => $quantidade) {
            $dados = $vitrine[$id] ?? null;
            if (! $dados) {
                unset($corrigido[$id]);
                $this->avisos[] = 'Um produto do seu carrinho não está mais disponível e foi retirado.';
                continue;
            }

            if (! $semEstoque) {
                $teto = (int) floor($dados['saldo']);
                if ($teto < 1) {
                    unset($corrigido[$id]);
                    $this->avisos[] = "“{$dados['nome']}” esgotou e saiu do carrinho.";
                    continue;
                }
                if ($quantidade > $teto) {
                    $quantidade = $teto;
                    $corrigido[$id] = $teto;
                    $this->avisos[] = "Só há {$teto} de “{$dados['nome']}” — ajustamos a quantidade.";
                }
            }

            $itens->push($dados + [
                'quantidade' => $quantidade,
                'total' => round($quantidade * $dados['preco'], 2),
                'produto' => $produtos[$id],
            ]);
        }

        if ($corrigido !== $bruto) {
            $this->gravar($corrigido);
        }

        return $itens;
    }

    public static function subtotal(Collection $itens): float
    {
        return round((float) $itens->sum('total'), 2);
    }
}
