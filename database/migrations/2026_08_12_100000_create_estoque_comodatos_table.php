<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peças que saíram em bonificação mas DEVEM RETORNAR (influencer, showroom,
 * prova, editorial). A baixa de estoque é a movimentação de bonificação normal;
 * esta tabela é o controle de quem está com a peça e até quando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_comodatos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->foreignId('estoque_movimentacao_id')->constrained('estoque_movimentacoes');
            $table->foreignId('produto_id')->constrained('produtos');

            $table->decimal('quantidade', 12, 3);
            $table->decimal('quantidade_devolvida', 12, 3)->default(0);

            // Com quem a peça está
            $table->string('responsavel', 120);
            $table->string('contato', 120)->nullable();

            $table->date('data_saida');
            $table->date('data_prevista_retorno');
            $table->date('data_retorno')->nullable();

            // Palavras neutras de propósito — ver armadilhas 5 e 42 (gênero do enum)
            $table->enum('status', ['pendente', 'parcial', 'devolvido', 'perdido'])
                ->default('pendente');

            $table->text('observacoes')->nullable();
            $table->foreignId('user_id')->constrained('users');

            $table->timestamps();
            // sem softDeletes — é trilha de responsabilidade sobre a peça

            $table->index(['empresa_id', 'status']);
            $table->index(['unidade_id', 'data_prevista_retorno']);
            $table->index(['produto_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_comodatos');
    }
};
