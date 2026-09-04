<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo "vendedor só opera o PDV", por empresa-cliente (04/09/2026).
 *
 * Nasce DESLIGADA — as 6 empresas continuam exatamente como hoje, e as 3 que
 * nem têm vendedor (EB GESTAO, STILO VINTE, DONA DOURO) são inertes de qualquer
 * forma.
 *
 * É por EMPRESA e não por loja de propósito: o mesmo vendedor atende em lojas
 * diferentes da mesma rede, e um recorte por unidade faria o menu dele mudar
 * conforme a loja da sessão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('vendedor_apenas_pdv')
                ->default(false)
                ->after('exige_documento_cadastro');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('vendedor_apenas_pdv');
        });
    }
};
