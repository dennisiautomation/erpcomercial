<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Política de visibilidade/venda de estoque entre unidades da mesma empresa.
 *
 *  - silos: cada loja só vê o próprio estoque (comportamento legado)
 *  - ver_apenas: visualiza estoque de outras lojas, mas só transfere
 *    (não vende direto)
 *  - ver_e_vender: PDV pode vender produto de outra loja; sistema
 *    cria transferência automática origem→destino + baixa estoque
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->enum('politica_estoque_inter_unidade', ['silos', 'ver_apenas', 'ver_e_vender'])
                  ->default('ver_e_vender')
                  ->after('regime_tributario');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('politica_estoque_inter_unidade');
        });
    }
};
