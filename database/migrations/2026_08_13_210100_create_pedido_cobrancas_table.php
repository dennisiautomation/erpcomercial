<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_cobrancas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('provedor', 30)->default('sicredi_pix');
            $table->string('txid', 35)->unique();
            $table->decimal('valor', 12, 2);
            $table->string('chave')->nullable();
            // Status BACEN: ATIVA, CONCLUIDA, REMOVIDA_PELO_USUARIO_RECEBEDOR,
            // REMOVIDA_PELO_PSP + 'ERRO' local quando a criação falhou.
            $table->string('status', 40)->default('ATIVA');
            $table->text('copia_cola')->nullable();
            $table->string('location')->nullable();
            $table->string('e2eid', 64)->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'pedido_id']);
            $table->index(['status', 'expira_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_cobrancas');
    }
};
