<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('cpf_cnpj', 18);
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();

            // Endereço
            $table->string('cep', 9);
            $table->string('logradouro');
            $table->string('numero', 20);
            $table->string('complemento')->nullable();
            $table->string('bairro');
            $table->string('cidade');
            $table->string('uf', 2);

            $table->string('contato_representante')->nullable();
            $table->string('telefone', 20);
            $table->string('email')->nullable();
            $table->text('condicoes_comerciais')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'cpf_cnpj']);
            $table->index(['empresa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
