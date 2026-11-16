<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiado / pagamento direto com a loja (16/11/2026).
 *
 * A forma "crediário" (que cada loja chama como quiser — "FIADO" na N S BORBA)
 * passa a deixar a conta a receber EM ABERTO no nome do cliente. O cliente paga
 * depois, no caixa: cada pagamento vira um `fiado_recebimentos` e uma entrada
 * no caixa do tipo `recebimento`, na forma em que o cliente pagou.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE movimentacoes_caixa MODIFY COLUMN tipo ENUM('venda','sangria','suprimento','abertura','fechamento','devolucao','estorno','recebimento') NOT NULL");

        Schema::create('fiado_recebimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete();
            $table->foreignId('movimentacao_caixa_id')->nullable()->constrained('movimentacoes_caixa')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('numero');
            $table->decimal('valor', 12, 2);
            $table->string('forma_pagamento', 30);
            $table->string('forma_chave', 40)->nullable();
            $table->string('forma_nome', 60)->nullable();
            $table->json('contas');              // [{conta_id, descricao, abatido, restante}]
            $table->decimal('saldo_antes', 12, 2);
            $table->decimal('saldo_depois', 12, 2);
            $table->string('observacoes', 255)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'cliente_id']);
            $table->unique(['empresa_id', 'numero']);
        });
    }

    public function down(): void
    {
        if (DB::table('movimentacoes_caixa')->where('tipo', 'recebimento')->exists()) {
            throw new RuntimeException('Há recebimentos de fiado no caixa — rollback exige decisão manual.');
        }
        Schema::dropIfExists('fiado_recebimentos');
        DB::statement("ALTER TABLE movimentacoes_caixa MODIFY COLUMN tipo ENUM('venda','sangria','suprimento','abertura','fechamento','devolucao','estorno') NOT NULL");
    }
};
