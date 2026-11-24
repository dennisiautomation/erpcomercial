<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devolução de compra ao fornecedor (24/11/2026, DK): a entrada de NF-e já
 * concluída (estoque + contas a pagar) pode voltar, toda ou em parte, sem o ERP
 * emitir nota — o documento de devolução, se houver, é só anotado.
 *
 * contas_pagar.nfe_entrada_id: até aqui a conta só citava a chave na observação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_entrada_devolucoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('nfe_entrada_id')->constrained('nfe_entradas')->cascadeOnDelete();
            $table->unsignedInteger('numero');                       // 1, 2… dentro da entrada
            $table->string('motivo', 500);
            $table->string('documento', 60)->nullable();             // nº/chave da nota de devolução, se alguém emitiu
            $table->decimal('valor_total', 12, 2);                   // valor dos itens devolvidos
            $table->decimal('valor_abatido', 12, 2)->default(0);     // tirado das contas a pagar em aberto
            $table->decimal('credito_fornecedor', 12, 2)->default(0); // o que já estava pago: fica a receber do fornecedor
            $table->json('contas_ajustadas')->nullable();            // [{id, antes, depois, status_antes, status_depois}]
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['empresa_id', 'nfe_entrada_id']);
        });

        Schema::create('nfe_entrada_devolucao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucao_id')->constrained('nfe_entrada_devolucoes')->cascadeOnDelete();
            $table->foreignId('nfe_entrada_item_id')->constrained('nfe_entrada_itens')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->decimal('quantidade_nota', 14, 4);               // na unidade da nota (ex.: 2 CX)
            $table->decimal('quantidade_estoque', 14, 3)->default(0); // o que saiu do estoque (ex.: 24 UN)
            $table->decimal('valor', 12, 2);
            $table->unsignedBigInteger('estoque_movimentacao_id')->nullable();
            $table->timestamps();
        });

        Schema::table('nfe_entradas', function (Blueprint $table) {
            // null = nada devolvido; parcial | total
            $table->string('devolucao_situacao', 10)->nullable()->after('status');
        });

        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->foreignId('nfe_entrada_id')->nullable()->after('fornecedor_id')
                ->constrained('nfe_entradas')->nullOnDelete();
        });

        // Liga as contas já criadas pela entrada (observação "Entrada por NF-e · chave …")
        DB::statement("
            UPDATE contas_pagar cp
            JOIN nfe_entradas e ON e.empresa_id = cp.empresa_id
             AND cp.observacoes LIKE CONCAT('Entrada por NF-e · chave ', e.chave_acesso, '%')
            SET cp.nfe_entrada_id = e.id
            WHERE cp.nfe_entrada_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nfe_entrada_id');
        });
        Schema::table('nfe_entradas', function (Blueprint $table) {
            $table->dropColumn('devolucao_situacao');
        });
        Schema::dropIfExists('nfe_entrada_devolucao_itens');
        Schema::dropIfExists('nfe_entrada_devolucoes');
    }
};
