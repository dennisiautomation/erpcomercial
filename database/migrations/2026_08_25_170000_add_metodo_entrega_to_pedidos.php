<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Método de entrega escolhido na criação do pedido (Agente IA §272.4 → fase
 * "entrega na conversa"). NULL = pedido anterior a esta coluna (ou canal que
 * não pergunta) — o despacho automático mantém o comportamento antigo;
 * 'retirada' = o DespacharEntregaUberJob NUNCA despacha; 'entrega' = cliente
 * pediu entrega e o endereço foi coletado na conversa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('metodo_entrega', 10)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('metodo_entrega');
        });
    }
};
