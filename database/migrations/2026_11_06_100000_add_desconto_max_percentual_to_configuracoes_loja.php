<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desconto máximo permitido na venda (06/11/2026).
 *
 * NULL = sem limite (comportamento de sempre: lojas existentes não mudam).
 * 0    = ninguém dá desconto. N > 0 = desconto total (itens + geral) de até N%
 *        do valor bruto da venda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->decimal('desconto_max_percentual', 5, 2)->nullable()->after('desconto_avista_percentual');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn('desconto_max_percentual');
        });
    }
};
