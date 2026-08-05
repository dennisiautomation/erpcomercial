<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importação de base migrada de outro sistema traz clientes sem CPF/CNPJ.
 * NULL passa no unique (empresa_id, cpf_cnpj) — múltiplos clientes sem
 * documento convivem; a deduplicação desses é por nome exato no import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('cpf_cnpj', 18)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('cpf_cnpj', 18)->nullable(false)->change();
        });
    }
};
