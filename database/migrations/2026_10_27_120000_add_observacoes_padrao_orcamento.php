<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observações para o cliente com texto pronto + escolha de imprimir (27/10/2026, DK).
 *
 * configuracoes_loja.orcamento_observacoes: texto que o orçamento novo já traz em
 *   "Observações para o Cliente" (o vendedor muda naquele orçamento).
 * orcamentos.imprimir_observacoes: caixinha "Imprimir as observações no orçamento".
 *   Default 1 = orçamentos que já existem continuam saindo como antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->text('orcamento_observacoes')->nullable()->after('orcamento_condicoes');
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            $table->boolean('imprimir_observacoes')->default(true)->after('observacoes_externas');
        });
    }

    public function down(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            $table->dropColumn('imprimir_observacoes');
        });

        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn('orcamento_observacoes');
        });
    }
};
