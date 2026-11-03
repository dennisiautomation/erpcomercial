<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forma de pagamento Credishop no PDV, por empresa-cliente (03/11/2026).
 *
 * Nasce DESLIGADA para todas; só a STILO VINTE (empresa 3) liga — pedido do
 * Dennis. Credishop é cartão de crédito de rede própria: no preço, na NFC-e
 * (tPag 03) e no cupom automático conta como cartão de crédito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('pdv_credishop')
                ->default(false)
                ->after('pdv_vendedores_da_loja');
        });

        DB::table('empresas')
            ->where('id', 3)
            ->where('nome_fantasia', 'STILO VINTE')
            ->update(['pdv_credishop' => true]);
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('pdv_credishop');
        });
    }
};
