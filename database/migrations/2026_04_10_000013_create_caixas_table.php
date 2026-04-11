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
        Schema::create('caixas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->integer('numero_caixa');
            $table->decimal('valor_abertura', 12, 2);
            $table->decimal('valor_fechamento', 12, 2)->nullable();
            $table->decimal('valor_esperado', 12, 2)->nullable();

            $table->enum('status', ['aberto', 'fechado'])->default('aberto');

            $table->timestamp('aberto_em')->useCurrent();
            $table->timestamp('fechado_em')->nullable();

            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'unidade_id']);
            $table->index(['unidade_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caixas');
    }
};
