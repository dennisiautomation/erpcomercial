<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código IBGE do município (7 dígitos) — necessário para NFS-e
 * (campo codigo_municipio no payload Focus, formato aninhado).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['empresas', 'unidades', 'clientes'] as $tabela) {
            if (! Schema::hasColumn($tabela, 'codigo_municipio')) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->string('codigo_municipio', 7)->nullable()->after('cidade');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['empresas', 'unidades', 'clientes'] as $tabela) {
            if (Schema::hasColumn($tabela, 'codigo_municipio')) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->dropColumn('codigo_municipio');
                });
            }
        }
    }
};
