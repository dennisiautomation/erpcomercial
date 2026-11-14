<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App de vendas na maquininha Stone (28/10/2026) — API api/integracao/pos/v1.
 *
 * - pos_tokens: login do OPERADOR no POS (token por usuário + loja + aparelho),
 *   diferente do integracao_tokens, que é da empresa e não sabe quem vendeu.
 *   Só o sha256 do token é guardado.
 * - pos_operacoes: chave de idempotência gerada no app (uuid). A venda paga na
 *   Stone é reenviada se a rede cair; a mesma chave nunca cria duas vendas.
 * - pos_eventos: log de atividade do app (login, pagamento, recusa, estorno,
 *   impressão, erro). O contrato da Stone exige 180 dias disponíveis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('dispositivo_serial', 60)->nullable();
            $table->string('dispositivo_modelo', 60)->nullable();
            $table->string('app_versao', 20)->nullable();
            $table->timestamp('expira_em');
            $table->timestamp('revogado_em')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'user_id']);
        });

        Schema::create('pos_operacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('chave', 64);
            $table->string('tipo', 30); // venda | pedido | faturar_pedido | cancelar_venda
            $table->string('status', 20)->default('processando'); // processando | concluida
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'chave']);
        });

        Schema::create('pos_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->string('dispositivo_serial', 60)->nullable();
            $table->string('tipo', 60);
            $table->string('mensagem', 500)->nullable();
            $table->json('dados')->nullable();
            $table->timestamp('ocorrido_em');
            $table->timestamp('created_at')->nullable();

            $table->index(['empresa_id', 'ocorrido_em']);
            $table->index(['empresa_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_eventos');
        Schema::dropIfExists('pos_operacoes');
        Schema::dropIfExists('pos_tokens');
    }
};
