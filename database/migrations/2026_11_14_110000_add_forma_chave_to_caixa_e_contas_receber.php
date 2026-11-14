<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qual BOTÃO de pagamento gerou a movimentação do caixa / a conta a receber, e
 * regra de máquina de cartão por botão (14/11/2026).
 *
 * `forma_pagamento` continua com o código de sempre (dinheiro, pix...) — é por
 * ele que a gaveta fecha. As duas colunas novas só deixam o caixa separar a
 * forma criada pelo lojista ("Cartão Elo") e mostrar o nome configurado.
 * Nulas nas movimentações antigas: nada é reescrito.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['movimentacoes_caixa', 'contas_receber'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('forma_chave', 40)->nullable()->after('forma_pagamento');
                $table->string('forma_nome', 40)->nullable()->after('forma_chave');
            });
        }

        // Regra de máquina por BOTÃO (forma criada, Credishop): a coluna era enum
        // de crédito/débito. Vira texto; os valores gravados não mudam.
        DB::statement("ALTER TABLE adquirente_taxas MODIFY forma VARCHAR(40) NOT NULL");
    }

    public function down(): void
    {
        // Volta ao enum só se não houver regra de botão (senão o MySQL truncaria)
        if (! DB::table('adquirente_taxas')->whereNotIn('forma', ['cartao_debito', 'cartao_credito'])->exists()) {
            DB::statement("ALTER TABLE adquirente_taxas MODIFY forma ENUM('cartao_debito','cartao_credito') NOT NULL");
        }

        foreach (['movimentacoes_caixa', 'contas_receber'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn(['forma_chave', 'forma_nome']);
            });
        }
    }
};
