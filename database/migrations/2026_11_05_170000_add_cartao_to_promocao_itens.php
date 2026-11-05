<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promoção com preço do CARTÃO por produto (05/11/2026, pedido da STILO): além do à vista,
 * o lojista define o cartão (R$ ou % sobre o preço normal do cartão). Vazio = regra da loja
 * (acréscimo do cartão sobre o à vista promocional), como era.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promocao_itens', function (Blueprint $table) {
            $table->enum('cartao_tipo', ['preco', 'percentual'])->nullable()->after('preco_referencia');
            $table->decimal('cartao_valor', 10, 2)->nullable()->after('cartao_tipo');
            $table->decimal('preco_cartao_referencia', 10, 2)->nullable()->after('cartao_valor');
        });
    }

    public function down(): void
    {
        Schema::table('promocao_itens', function (Blueprint $table) {
            $table->dropColumn(['cartao_tipo', 'cartao_valor', 'preco_cartao_referencia']);
        });
    }
};
