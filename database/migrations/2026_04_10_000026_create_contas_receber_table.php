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
        Schema::create('contas_receber', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');

            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('venda_id')->nullable()->constrained('vendas');

            $table->string('descricao');
            $table->decimal('valor', 12, 2);
            $table->decimal('valor_pago', 12, 2)->default(0);

            $table->date('vencimento');
            $table->date('pago_em')->nullable();

            $table->string('forma_pagamento')->nullable();

            $table->integer('parcela')->default(1);
            $table->integer('total_parcelas')->default(1);

            $table->enum('status', ['pendente', 'paga', 'vencida', 'cancelada', 'renegociada']);

            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status', 'vencimento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contas_receber');
    }
};
