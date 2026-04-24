<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartas_correcao', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->foreignId('user_id')->nullable()->constrained('users');

            $table->unsignedSmallInteger('numero_sequencia');
            $table->text('correcao');

            $table->enum('status', ['pendente', 'autorizada', 'rejeitada'])->default('pendente');
            $table->string('protocolo')->nullable();
            $table->text('mensagem_sefaz')->nullable();

            $table->text('xml_url')->nullable();
            $table->text('pdf_url')->nullable();

            $table->timestamp('enviada_em')->nullable();

            $table->timestamps();

            $table->unique(['nota_fiscal_id', 'numero_sequencia']);
            $table->index(['empresa_id', 'nota_fiscal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartas_correcao');
    }
};
