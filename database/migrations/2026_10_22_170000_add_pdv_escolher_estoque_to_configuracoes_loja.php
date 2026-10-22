<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escolher no PDV de qual estoque a venda sai (22/10/2026).
 *
 * Default false: a loja continua baixando sempre do estoque de venda, sem seletor
 * nenhum na tela do caixa, até alguém ligar em Configurações da Loja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->boolean('pdv_escolher_estoque')->default(false)->after('padrao_impressao');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn('pdv_escolher_estoque');
        });
    }
};
