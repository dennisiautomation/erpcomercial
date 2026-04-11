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
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedBigInteger('numero');
            $table->date('validade_ate')->nullable();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('desconto_percentual', 5, 2)->default(0);
            $table->decimal('desconto_valor', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->enum('status', ['em_aberto', 'aprovado', 'recusado', 'expirado', 'convertido'])->default('em_aberto');

            $table->text('observacoes_internas')->nullable();
            $table->text('observacoes_externas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'unidade_id']);
            $table->index(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orcamentos');
    }
};
