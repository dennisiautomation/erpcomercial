<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Troca de peça SEM venda nenhuma no sistema (09/09/2026, 2ª rodada).
 *
 * A MISS MERLINDA recebe peça de troca que não foi vendida pelo ERP (venda
 * anterior à migração, ou de outro sistema). O F6 passa a aceitar a troca sem
 * escolher venda: `devolucoes.venda_id` NULL = sem venda de origem. A FK fica;
 * nenhuma linha existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE devolucoes MODIFY venda_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::table('devolucao_itens')->whereIn('devolucao_id', DB::table('devolucoes')->whereNull('venda_id')->pluck('id'))->delete();
        DB::table('devolucoes')->whereNull('venda_id')->delete();
        DB::statement('ALTER TABLE devolucoes MODIFY venda_id BIGINT UNSIGNED NOT NULL');
    }
};
