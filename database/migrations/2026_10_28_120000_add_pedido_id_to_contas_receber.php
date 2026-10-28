<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conta a receber do pedido ganha vínculo por id (28/10/2026).
 *
 * Confirmar o pedido cria uma conta a receber "Pedido #N" (pendente, 30 dias).
 * Faturar nunca a baixava — a única ligação era o texto da descrição. Com o
 * faturamento passando a lançar a venda no caixa, a conta precisa ser
 * encontrada com segurança para virar paga com `venda_id`, forma e data.
 *
 * Backfill: pelo par empresa + descrição "Pedido #N" (o número do pedido é
 * único por empresa), só nas contas sem venda — as que já tinham `venda_id`
 * nasceram do PDV/balcão e não são de pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->foreignId('pedido_id')->nullable()->after('venda_id')
                ->constrained('pedidos')->nullOnDelete();
        });

        DB::statement("
            UPDATE contas_receber c
            JOIN pedidos p ON p.empresa_id = c.empresa_id
                          AND c.descricao = CONCAT('Pedido #', p.numero)
            SET c.pedido_id = p.id
            WHERE c.pedido_id IS NULL AND c.venda_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pedido_id');
        });
    }
};
