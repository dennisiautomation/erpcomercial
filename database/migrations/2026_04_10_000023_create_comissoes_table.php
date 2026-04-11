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
        Schema::create('comissoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();

            $table->decimal('valor_venda', 12, 2);
            $table->decimal('percentual', 5, 2);
            $table->decimal('valor_comissao', 12, 2);

            $table->enum('status', ['pendente', 'paga', 'cancelada'])->default('pendente');
            $table->timestamp('pago_em')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'unidade_id']);
            $table->index(['user_id', 'status']);
            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comissoes');
    }
};
