<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 cafeteria (30/10/2026): tipo do produto, ficha técnica, adicionais
 * (grupos de opções) e atalhos no PDV.
 *
 * Tudo nasce desligado: produto existente vira 'revenda' (o comportamento de
 * sempre), `empresas.usa_ficha_tecnica` e `configuracoes_loja.pdv_atalhos`
 * começam em false — nenhuma loja em uso muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // revenda | produzido | insumo | adicional — ver App\Enums\TipoProduto
            $table->string('tipo', 20)->default('revenda')->after('descricao_detalhada');
            $table->boolean('atalho_pdv')->default(false)->after('tipo');
            $table->index(['empresa_id', 'tipo']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('usa_ficha_tecnica')->default(false);
        });

        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->boolean('pdv_atalhos')->default(false);
        });

        // Receita do produto produzido: cada linha é um insumo e quanto dele sai
        // por UMA unidade vendida, na unidade de medida do insumo (kg, LT, UN).
        Schema::create('produto_ficha_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('produtos')->restrictOnDelete();
            $table->decimal('quantidade', 12, 4);
            $table->timestamps();
            $table->unique(['produto_id', 'insumo_id']);
        });

        // Grupo reutilizável ("Tamanho", "Leite", "Extras"): o mesmo grupo vale
        // para vários produtos. minimo 0 = opcional; maximo 1 = escolha única.
        Schema::create('grupos_opcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->unsignedTinyInteger('minimo')->default(0);
            $table->unsignedTinyInteger('maximo')->default(1);
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->timestamps();
            $table->index(['empresa_id', 'status']);
        });

        // Opção do grupo = um produto do tipo adicional. preco null = vale o
        // preço de venda do adicional.
        Schema::create('grupo_opcao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('grupo_opcao_id')->constrained('grupos_opcoes')->cascadeOnDelete();
            $table->foreignId('adicional_id')->constrained('produtos')->restrictOnDelete();
            $table->decimal('preco', 10, 2)->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
            $table->unique(['grupo_opcao_id', 'adicional_id']);
        });

        Schema::create('produto_grupo_opcao', function (Blueprint $table) {
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('grupo_opcao_id')->constrained('grupos_opcoes')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->primary(['produto_id', 'grupo_opcao_id']);
        });

        Schema::table('venda_itens', function (Blueprint $table) {
            // Adicionais escolhidos no PDV (snapshot: nome e preço do momento)
            $table->json('opcoes')->nullable();
            // O que de fato saiu do estoque por este item, quando não foi o próprio
            // produto (ficha técnica/adicionais) — o cancelamento devolve isto.
            $table->json('baixa_componentes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->dropColumn(['opcoes', 'baixa_componentes']);
        });
        Schema::dropIfExists('produto_grupo_opcao');
        Schema::dropIfExists('grupo_opcao_itens');
        Schema::dropIfExists('grupos_opcoes');
        Schema::dropIfExists('produto_ficha_itens');
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn('pdv_atalhos');
        });
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('usa_ficha_tecnica');
        });
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'tipo']);
            $table->dropColumn(['tipo', 'atalho_pdv']);
        });
    }
};
