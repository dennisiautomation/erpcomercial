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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->enum('tipo_pessoa', ['pf', 'pj']);
            $table->string('cpf_cnpj', 18);
            $table->string('nome_razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('ie', 20)->nullable();

            // Endereço
            $table->string('cep', 9);
            $table->string('logradouro');
            $table->string('numero', 20);
            $table->string('complemento')->nullable();
            $table->string('bairro');
            $table->string('cidade');
            $table->string('uf', 2);

            $table->string('telefone', 20);
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();

            $table->decimal('limite_credito', 12, 2)->nullable();
            $table->enum('status', ['ativo', 'inativo', 'bloqueado']);
            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'cpf_cnpj']);
            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
