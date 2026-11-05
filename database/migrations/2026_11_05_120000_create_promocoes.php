<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Períodos de promoção (05/11/2026): nome + período + lojas + produtos com preço
 * promocional fixo ou % de desconto sobre o preço à vista. Ver PromocaoService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->date('data_inicio');
            $table->date('data_fim');
            // Encerrada antes do fim: a promoção vale até o dia ANTERIOR a encerrada_em
            $table->timestamp('encerrada_em')->nullable();
            $table->foreignId('encerrada_por')->nullable()->constrained('users')->nullOnDelete();
            // De qual promoção esta foi duplicada
            $table->foreignId('origem_id')->nullable()->constrained('promocoes')->nullOnDelete();
            $table->foreignId('criada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'data_inicio', 'data_fim']);
        });

        Schema::create('promocao_unidade', function (Blueprint $table) {
            $table->foreignId('promocao_id')->constrained('promocoes')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->primary(['promocao_id', 'unidade_id']);
            $table->index('unidade_id');
        });

        Schema::create('promocao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promocao_id')->constrained('promocoes')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            // preco = preço à vista promocional; percentual = % de desconto sobre o preço à vista da loja
            $table->enum('tipo', ['preco', 'percentual']);
            $table->decimal('valor', 10, 2);
            // Preço à vista quando a promoção foi montada (só para mostrar o "de/por" e duplicar)
            $table->decimal('preco_referencia', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['promocao_id', 'produto_id']);
            $table->index('produto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocao_itens');
        Schema::dropIfExists('promocao_unidade');
        Schema::dropIfExists('promocoes');
    }
};
