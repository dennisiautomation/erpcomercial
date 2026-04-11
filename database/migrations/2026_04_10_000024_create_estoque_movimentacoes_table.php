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
        Schema::create('estoque_movimentacoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->foreignId('produto_id')->constrained('produtos');

            $table->enum('tipo', ['entrada', 'saida', 'ajuste', 'perda', 'bonificacao', 'transferencia', 'devolucao']);

            $table->decimal('quantidade', 12, 3);
            $table->decimal('quantidade_anterior', 12, 3);
            $table->decimal('quantidade_posterior', 12, 3);
            $table->decimal('custo_unitario', 12, 2)->nullable();

            $table->string('origem_tipo')->nullable(); // venda, compra, ajuste_manual, transferencia, devolucao
            $table->unsignedBigInteger('origem_id')->nullable(); // polymorphic

            $table->foreignId('user_id')->constrained('users');
            $table->text('observacoes')->nullable();

            $table->timestamps();
            // NO softDeletes - audit trail

            $table->index(['unidade_id', 'produto_id']);
            $table->index(['origem_tipo', 'origem_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estoque_movimentacoes');
    }
};
