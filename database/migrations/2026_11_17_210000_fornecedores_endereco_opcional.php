<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro simples de fornecedor (17/11/2026): quem é pago pelo banco pode virar fornecedor só com
 * nome e CPF/CNPJ, direto da conciliação. O endereço e o telefone passam a aceitar vazio no banco
 * de dados; a tela completa de Fornecedores continua pedindo tudo, como sempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
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
        // Não volta para obrigatório: já pode haver fornecedor sem endereço.
    }
};
