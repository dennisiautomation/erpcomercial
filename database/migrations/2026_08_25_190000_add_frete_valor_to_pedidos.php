<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frete repassado ao cliente no pedido do Agente IA (modelo China Mix:
 * preco = fee da cotação Uber / 100, total = subtotal − desconto + frete).
 * NULL = pedido sem frete (retirada, canal antigo, cotação indisponível).
 * ⚠️ O recálculo de total do PedidoController::update SOMA este campo —
 * sem isso a edição humana do pedido apagaria o frete do total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('frete_valor', 10, 2)->nullable()->after('metodo_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('frete_valor');
        });
    }
};
