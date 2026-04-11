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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();

            $table->string('cnpj', 18)->unique();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('ie', 20)->nullable();
            $table->string('im', 20)->nullable();

            $table->enum('regime_tributario', ['simples_nacional', 'lucro_presumido', 'lucro_real']);

            // Endereço
            $table->string('cep', 9);
            $table->string('logradouro');
            $table->string('numero', 20);
            $table->string('complemento')->nullable();
            $table->string('bairro');
            $table->string('cidade');
            $table->string('uf', 2);

            $table->string('telefone', 20);
            $table->string('email');
            $table->string('logo')->nullable();

            $table->enum('plano', ['basico', 'profissional', 'enterprise']);
            $table->enum('status', ['em_implantacao', 'ativo', 'suspenso', 'cancelado'])->default('em_implantacao');
            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
