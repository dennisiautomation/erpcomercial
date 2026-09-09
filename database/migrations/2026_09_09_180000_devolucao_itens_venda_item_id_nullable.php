<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Peça que NÃO está na venda encontrada pode entrar na troca (09/09/2026).
 *
 * Até aqui toda linha de devolução apontava para um `venda_itens` — a peça só
 * voltava se estivesse no cupom daquela venda. O caixa da MISS MERLINDA precisa
 * bipar, no F6, uma peça que o cliente trouxe sem estar naquela venda: ela entra
 * pelo preço de venda atual, marcada "sem cupom desta venda".
 *
 * `venda_item_id` NULL = peça sem cupom. `produto_id` continua obrigatório nesse
 * caso (é o que identifica a peça); a FK para `venda_itens` fica como está.
 * Nenhuma linha existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE devolucao_itens MODIFY venda_item_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Linhas sem cupom não têm para onde apontar — saem antes de voltar o NOT NULL
        DB::table('devolucao_itens')->whereNull('venda_item_id')->delete();
        DB::statement('ALTER TABLE devolucao_itens MODIFY venda_item_id BIGINT UNSIGNED NOT NULL');
    }
};
