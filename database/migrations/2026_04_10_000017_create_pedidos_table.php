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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('orcamento_id')->nullable()->constrained('orcamentos')->nullOnDelete();

            $table->unsignedBigInteger('numero');
            $table->string('condicao_pagamento')->nullable();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('desconto_percentual', 5, 2)->default(0);
            $table->decimal('desconto_valor', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->enum('status', ['rascunho', 'confirmado', 'faturado', 'entregue', 'cancelado'])->default('rascunho');

            $table->text('observacoes_internas')->nullable();
            $table->text('observacoes_externas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'unidade_id']);
            $table->index(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
