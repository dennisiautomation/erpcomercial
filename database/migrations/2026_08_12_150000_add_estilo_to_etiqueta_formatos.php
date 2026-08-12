<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estilo do arranjo da etiqueta.
 *
 * 'padrao'    — como sempre foi: empresa, descrição, barras, preços, código.
 * 'nome_topo' — nome da loja em destaque no topo, preços pequenos à direita e
 *               o código de barras grande embaixo (pedido da MISS MERLINDA,
 *               que replica o layout do BarTender que ela já usava).
 *
 * Fica no FORMATO, que é por empresa — então um estilo novo nunca vaza para
 * outro cliente. Palavras neutras de gênero no enum (armadilhas 5 e 42).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etiqueta_formatos', function (Blueprint $table) {
            $table->enum('estilo', ['padrao', 'nome_topo'])
                ->default('padrao')
                ->after('mostrar_empresa');
        });
    }

    public function down(): void
    {
        Schema::table('etiqueta_formatos', function (Blueprint $table) {
            $table->dropColumn('estilo');
        });
    }
};
