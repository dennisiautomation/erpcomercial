<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo exclusivo da loja online (17/11/2026, MISS MERLINDA).
 *
 * Um modelo pago por um cliente só aparece no editor da loja que tem a chave dele
 * aqui. Quem liga é a PLATAFORMA (admin → empresa → Integração → Loja online).
 * Nulo = a loja só vê os modelos públicos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lojas_online', function (Blueprint $table) {
            $table->string('modelo_exclusivo', 40)->nullable()->after('modelo');
        });
    }

    public function down(): void
    {
        Schema::table('lojas_online', function (Blueprint $table) {
            $table->dropColumn('modelo_exclusivo');
        });
    }
};
