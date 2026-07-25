<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "CPF na nota" avulso no PDV — documento do consumidor para o cupom fiscal
 * (NFC-e) sem precisar cadastrar o cliente. Aceita CNPJ alfanumérico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->string('cpf_cnpj_nota', 18)->nullable()->after('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('cpf_cnpj_nota');
        });
    }
};
