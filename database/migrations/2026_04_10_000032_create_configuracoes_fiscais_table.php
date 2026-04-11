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
        Schema::create('configuracoes_fiscais', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');

            $table->enum('ambiente', ['homologacao', 'producao'])->default('homologacao');

            $table->string('focus_token')->nullable(); // encrypted in model
            $table->string('serie_nfe')->nullable();
            $table->string('serie_nfce')->nullable();
            $table->string('csc_nfce')->nullable();
            $table->string('csc_id_nfce')->nullable();

            $table->date('certificado_validade')->nullable();

            $table->boolean('emissao_fiscal_ativa')->default(false);
            $table->enum('tipo_cupom_pdv', ['fiscal', 'nao_fiscal'])->default('nao_fiscal');

            $table->timestamps();

            $table->unique(['empresa_id', 'unidade_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracoes_fiscais');
    }
};
