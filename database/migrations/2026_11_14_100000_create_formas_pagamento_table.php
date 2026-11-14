<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formas de pagamento configuráveis por empresa (14/11/2026).
 *
 * Nasce VAZIA: empresa sem linha nenhuma continua com os botões de sempre
 * (FormaPagamentoService::padrao). As linhas só são gravadas quando o lojista
 * salva a tela de Formas de pagamento — nenhuma empresa muda no deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            // 'dinheiro', 'pix', ... para as do sistema; 'fp_xxxxxxxx' para as criadas pelo lojista
            $table->string('chave', 40);
            $table->string('nome', 40);
            // Como a forma se comporta (nota fiscal, caixa, tabela de preço, parcelas):
            // dinheiro, cartao_credito, cartao_debito, pix, boleto, crediario, transferencia, vale
            $table->string('tipo', 30);
            $table->boolean('sistema')->default(false);
            $table->string('icone', 40)->default('bi-wallet2');
            $table->string('cor', 9)->nullable();
            $table->unsignedTinyInteger('largura')->default(1); // 1 = meia linha, 2 = linha inteira no PDV
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formas_pagamento');
    }
};
