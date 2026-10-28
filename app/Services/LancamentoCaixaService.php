<?php

namespace App\Services;

use App\Enums\StatusCaixa;
use App\Enums\TipoMovimentacaoCaixa;
use App\Models\Caixa;
use App\Models\MovimentacaoCaixa;
use App\Models\Venda;
use App\Scopes\UnidadeScope;

/**
 * Entrada de uma venda no caixa — fora do PDV (28/10/2026).
 *
 * Até aqui só `PdvController::registrarVenda` lançava a venda no caixa. O
 * faturamento do pedido e a venda de balcão criavam a venda sem `caixa_id` e
 * sem movimentação nenhuma: o fechamento e o relatório de caixa da DK Med não
 * enxergavam as vendas de pedido ("as vendas finalizadas não entram no caixa").
 *
 * A regra é a mesma do PDV: uma movimentação por forma de pagamento, com
 * `venda_id` (é o que o cancelamento usa para estornar — VendaController::destroy).
 * O PDV continua com o código dele: aqui só entra quem vende fora do PDV.
 */
class LancamentoCaixaService
{
    /**
     * Caixa aberto do usuário na loja informada. NULL = ninguém abriu.
     *
     * Mesma regra do estorno no cancelamento: o caixa é de quem está logado,
     * na loja DA VENDA (dono faturando pedido de outra loja cai na loja do
     * pedido, não na da sessão). Fora só o UnidadeScope — armadilha 38.
     */
    public function caixaAbertoDoUsuario(int $unidadeId, int $userId): ?Caixa
    {
        return Caixa::withoutGlobalScope(UnidadeScope::class)
            ->where('unidade_id', $unidadeId)
            ->where('user_id', $userId)
            ->where('status', StatusCaixa::Aberto)
            ->first();
    }

    /**
     * Lança as entradas da venda no caixa, uma por forma de pagamento.
     *
     * `$pagamentos` é o `pagamento_detalhes` da venda: `[{forma, valor, ...}]`.
     * Troco (venda de balcão/pedido não tem) sai do dinheiro, como no PDV.
     * Grava `caixa_id` na venda quando ainda não tem.
     *
     * @return MovimentacaoCaixa[]
     */
    public function lancarVenda(Venda $venda, Caixa $caixa, array $pagamentos, int $userId): array
    {
        if (! $venda->caixa_id) {
            $venda->forceFill(['caixa_id' => $caixa->id])->save();
        }

        $trocoRestante = (float) $venda->troco;
        $movimentacoes = [];

        foreach ($pagamentos as $pgto) {
            $valorMov = (float) ($pgto['valor'] ?? 0);
            $forma = (string) ($pgto['forma'] ?? 'dinheiro');

            if ($forma === 'dinheiro' && $trocoRestante > 0) {
                $abate = min($valorMov, $trocoRestante);
                $valorMov -= $abate;
                $trocoRestante -= $abate;
            }
            if ($valorMov <= 0) {
                continue;
            }

            $movimentacoes[] = MovimentacaoCaixa::create([
                'empresa_id'      => $venda->empresa_id,
                'unidade_id'      => $caixa->unidade_id,
                'caixa_id'        => $caixa->id,
                'venda_id'        => $venda->id,
                'tipo'            => TipoMovimentacaoCaixa::Venda,
                'valor'           => round($valorMov, 2),
                'forma_pagamento' => $forma,
                'descricao'       => "Venda #{$venda->numero}",
                'user_id'         => $userId,
            ]);
        }

        return $movimentacoes;
    }
}
