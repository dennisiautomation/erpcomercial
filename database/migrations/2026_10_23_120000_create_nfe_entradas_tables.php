<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrada de mercadoria pela NF-e de compra (23/10/2026).
 *
 * - nfe_entradas: uma por nota (chave única por empresa — a mesma nota não entra
 *   duas vezes, nem por outra loja). Nasce como rascunho na leitura do XML e vira
 *   concluída quando o estoque é lançado.
 * - nfe_entrada_itens: os itens do XML com o que o lojista decidiu na conferência
 *   (vincular a um produto, criar um novo ou ignorar) e o fator de conversão.
 * - produto_fornecedor_codigos: "o código X deste CNPJ é o meu produto Y" — da
 *   segunda nota em diante o item já vem casado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_entradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->foreignId('estoque_id')->nullable()->constrained('estoques');
            $table->foreignId('nfe_recebida_id')->nullable()->constrained('nfes_recebidas')->nullOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores');
            $table->string('chave_acesso', 44);
            $table->string('numero', 20)->nullable();
            $table->string('serie', 5)->nullable();
            $table->string('cnpj_emitente', 14)->nullable();
            $table->string('nome_emitente')->nullable();
            $table->string('cnpj_destinatario', 14)->nullable();
            $table->date('data_emissao')->nullable();
            $table->decimal('valor_produtos', 14, 2)->default(0);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->json('duplicatas')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('status', 20)->default('rascunho'); // rascunho | concluida
            $table->boolean('atualizou_custo')->default(false);
            $table->unsignedSmallInteger('contas_pagar_geradas')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('concluida_por')->nullable()->constrained('users');
            $table->timestamp('concluida_em')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'chave_acesso']);
            $table->index(['unidade_id', 'status']);
        });

        Schema::create('nfe_entrada_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nfe_entrada_id')->constrained('nfe_entradas')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_item');
            $table->string('codigo_fornecedor', 60)->nullable();
            $table->string('ean', 20)->nullable();
            $table->string('descricao');
            $table->string('ncm', 10)->nullable();
            $table->string('cfop', 5)->nullable();
            $table->string('unidade_comercial', 10)->nullable();
            $table->decimal('quantidade', 15, 4);
            $table->decimal('valor_unitario', 15, 6)->default(0);
            $table->decimal('valor_total', 14, 2)->default(0);
            // Custo do item já com frete, seguro, outras, IPI e ST somados e desconto abatido
            $table->decimal('custo_total', 14, 2)->default(0);
            $table->string('acao', 10)->default('vincular'); // vincular | criar | ignorar
            $table->foreignId('produto_id')->nullable()->constrained('produtos');
            $table->decimal('fator_conversao', 12, 4)->default(1);
            $table->foreignId('estoque_movimentacao_id')->nullable()->constrained('estoque_movimentacoes');
            $table->timestamps();
        });

        Schema::create('produto_fornecedor_codigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('cnpj_emitente', 14);
            $table->string('codigo_fornecedor', 60);
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->decimal('fator_conversao', 12, 4)->default(1);
            $table->timestamps();

            $table->unique(['empresa_id', 'cnpj_emitente', 'codigo_fornecedor'], 'pfc_empresa_cnpj_codigo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_fornecedor_codigos');
        Schema::dropIfExists('nfe_entrada_itens');
        Schema::dropIfExists('nfe_entradas');
    }
};
