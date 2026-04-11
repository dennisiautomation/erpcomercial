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
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('nome');
            $table->string('cnpj', 18);
            $table->string('ie', 20)->nullable();
            $table->string('im', 20)->nullable();

            // Endereço
            $table->string('cep', 9);
            $table->string('logradouro');
            $table->string('numero', 20);
            $table->string('complemento')->nullable();
            $table->string('bairro');
            $table->string('cidade');
            $table->string('uf', 2);

            $table->string('telefone', 20);
            $table->foreignId('gerente_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('logo')->nullable();

            $table->enum('status', ['ativa', 'inativa', 'em_implantacao']);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
