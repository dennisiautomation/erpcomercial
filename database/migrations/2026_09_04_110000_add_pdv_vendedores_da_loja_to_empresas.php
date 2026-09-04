<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Select de vendedor do PDV (F3) mostra só quem está vinculado à loja da sessão
 * (04/09/2026). Por empresa, como a chave irmã `vendedor_apenas_pdv`.
 *
 * Nasce DESLIGADA — o select continua listando a empresa inteira, como sempre.
 *
 * Motivo real: MISS MERLINDA tem 6 lojas e 4 vendedores, cada um vinculado a UMA
 * loja; N S BORBA tem 3 lojas e 5 vendedores, idem. O caixa de qualquer loja
 * enxergava os 4 (ou 5) no mesmo select. ia365 tem o vendedor ligado às 4 lojas,
 * então para ela ligar a chave não muda nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('pdv_vendedores_da_loja')
                ->default(false)
                ->after('vendedor_apenas_pdv');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('pdv_vendedores_da_loja');
        });
    }
};
