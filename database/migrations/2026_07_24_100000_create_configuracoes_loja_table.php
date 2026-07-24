<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_loja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();

            // Caixa / PDV
            $table->boolean('vendedor_responsavel_caixa')->default(false);

            // Tabelas de preço (regra geral — override por produto em produto_precos)
            $table->enum('regra_preco_split', ['cartao_maior', 'sempre_menor', 'sempre_maior'])
                ->default('cartao_maior');
            $table->decimal('percentual_debito', 5, 2)->default(0);
            $table->decimal('percentual_credito', 5, 2)->default(0);
            $table->unsignedTinyInteger('max_parcelas')->default(6);

            // Emissão na finalização da venda
            $table->boolean('cupom_automatico_cartao')->default(false);
            $table->boolean('cpf_emite_fiscal')->default(false);
            $table->enum('padrao_impressao', ['recibo', 'cupom_fiscal'])->default('recibo');

            $table->timestamps();

            $table->unique(['empresa_id', 'unidade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_loja');
    }
};
