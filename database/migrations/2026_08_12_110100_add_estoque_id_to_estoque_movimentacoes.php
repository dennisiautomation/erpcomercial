<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A chave do saldo passa de (unidade, produto) para (unidade, estoque, produto).
 *
 * Entra NULLABLE, faz o backfill de todo o histórico para o estoque "Principal"
 * da unidade, e só então vira obrigatória. É isso que preserva as cadeias
 * quantidade_anterior→posterior existentes: como todo o histórico cai no mesmo
 * estoque, a última movimentação de cada par continua sendo a mesma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            $table->foreignId('estoque_id')->nullable()->after('unidade_id')
                ->constrained('estoques');
        });

        // Backfill: tudo vai para o Principal da própria unidade
        DB::statement('
            UPDATE estoque_movimentacoes m
            JOIN estoques e ON e.unidade_id = m.unidade_id AND e.is_padrao = 1
            SET m.estoque_id = e.id
            WHERE m.estoque_id IS NULL
        ');

        $orfas = DB::table('estoque_movimentacoes')->whereNull('estoque_id')->count();

        if ($orfas > 0) {
            throw new RuntimeException(
                "Backfill incompleto: {$orfas} movimentações sem estoque_id. "
                . 'Confira se toda unidade com movimentação tem estoque padrão antes de seguir.'
            );
        }

        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            $table->foreignId('estoque_id')->nullable(false)->change();
        });

        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            // O índice que sustenta a busca do saldo atual
            $table->index(['estoque_id', 'produto_id', 'id'], 'em_estoque_produto_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            $table->dropIndex('em_estoque_produto_id_idx');
            $table->dropForeign(['estoque_id']);
            $table->dropColumn('estoque_id');
        });
    }
};
