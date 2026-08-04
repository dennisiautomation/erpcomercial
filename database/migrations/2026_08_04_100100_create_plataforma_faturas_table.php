<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faturas da PLATAFORMA (IA365 → empresa-cliente). Não confundir com o
 * financeiro interno da empresa (contas_receber/contas_pagar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plataforma_faturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('competencia', 7);                 // mensal: 2026-08 | anual: 2026
            $table->string('descricao')->nullable();
            $table->decimal('valor', 10, 2);
            $table->date('vencimento');
            $table->string('status', 15)->default('pendente'); // pendente|paga|cancelada
            $table->date('pago_em')->nullable();
            $table->string('forma_pagamento', 30)->nullable(); // pix|transferencia|dinheiro|boleto|outro
            $table->text('observacao')->nullable();
            $table->boolean('gerada_automaticamente')->default(false);
            $table->foreignId('marcada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'competencia']);
            $table->index(['status', 'vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plataforma_faturas');
    }
};
