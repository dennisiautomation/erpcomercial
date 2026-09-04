<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quarta regra de preço para pagamento dividido: acréscimo só sobre a parte
 * paga no cartão.
 *
 * As três regras existentes respondem "qual TABELA vale para a venda inteira":
 * a maior entre as formas, sempre a menor, sempre a maior. Todas cobram o
 * acréscimo sobre o total — numa venda de R$ 300 paga R$ 100 no PIX e R$ 200 no
 * crédito a 10%, o cliente paga R$ 330, inclusive os 10% sobre o que foi no PIX.
 *
 * `por_parte` responde outra pergunta: cada forma paga a tabela dela. O mesmo
 * caso vira R$ 320 — os 10% incidem só sobre os R$ 200 do cartão.
 *
 * ⚠️ ENUM: acrescentar valor exige ALTER TABLE cru (o Doctrine do Laravel não
 * enxerga enum). A lista tem que vir INTEIRA e com o mesmo COLLATE, senão os valores
 * antigos somem e as lojas configuradas hoje viram string vazia.
 *
 * Nenhuma loja muda de comportamento: o default segue `cartao_maior` e ninguém
 * é migrado para a regra nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE configuracoes_loja MODIFY regra_preco_split
             ENUM('cartao_maior','sempre_menor','sempre_maior','por_parte')
             COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cartao_maior'"
        );
    }

    public function down(): void
    {
        // Quem já estiver em por_parte volta para o default, senão o ALTER falha
        DB::table('configuracoes_loja')
            ->where('regra_preco_split', 'por_parte')
            ->update(['regra_preco_split' => 'cartao_maior']);

        DB::statement(
            "ALTER TABLE configuracoes_loja MODIFY regra_preco_split
             ENUM('cartao_maior','sempre_menor','sempre_maior')
             COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cartao_maior'"
        );
    }
};
