<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * unidade_origem_id no venda_item: identifica de qual unidade o
 * produto saiu fisicamente (quando ≠ unidade da venda, configura
 * "venda remota" — gera transferência automática).
 *
 * Nullable por compatibilidade. Quando NULL, equivale a venda.unidade_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->foreignId('unidade_origem_id')->nullable()
                  ->after('servico_id')
                  ->constrained('unidades')
                  ->nullOnDelete();
            $table->index('unidade_origem_id');
        });
    }

    public function down(): void
    {
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->dropForeign(['unidade_origem_id']);
            $table->dropIndex(['unidade_origem_id']);
            $table->dropColumn('unidade_origem_id');
        });
    }
};
