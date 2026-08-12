<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transferência passa a ser de ESTOQUE para ESTOQUE — o que também permite
 * mover dentro da mesma loja (salão → depósito), não só entre lojas.
 *
 * As unidades continuam nas colunas antigas: quem lê a tela por loja não muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transferencias_estoque', function (Blueprint $table) {
            $table->foreignId('estoque_origem_id')->nullable()->after('unidade_origem_id')
                ->constrained('estoques');
            $table->foreignId('estoque_destino_id')->nullable()->after('unidade_destino_id')
                ->constrained('estoques');
        });

        // Histórico: origem e destino viram o Principal de cada unidade
        DB::statement('
            UPDATE transferencias_estoque t
            JOIN estoques eo ON eo.unidade_id = t.unidade_origem_id AND eo.is_padrao = 1
            JOIN estoques ed ON ed.unidade_id = t.unidade_destino_id AND ed.is_padrao = 1
            SET t.estoque_origem_id = eo.id, t.estoque_destino_id = ed.id
            WHERE t.estoque_origem_id IS NULL OR t.estoque_destino_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('transferencias_estoque', function (Blueprint $table) {
            $table->dropForeign(['estoque_origem_id']);
            $table->dropForeign(['estoque_destino_id']);
            $table->dropColumn(['estoque_origem_id', 'estoque_destino_id']);
        });
    }
};
