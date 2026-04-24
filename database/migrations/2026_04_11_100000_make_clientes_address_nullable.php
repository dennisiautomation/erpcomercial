<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('cep', 9)->nullable()->change();
            $table->string('logradouro')->nullable()->change();
            $table->string('numero', 20)->nullable()->change();
            $table->string('bairro')->nullable()->change();
            $table->string('cidade')->nullable()->change();
            $table->string('uf', 2)->nullable()->change();
            $table->string('telefone', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // irreversivel com seguranca — deixado em branco
    }
};
