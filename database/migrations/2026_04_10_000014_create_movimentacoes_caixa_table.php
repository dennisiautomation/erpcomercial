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
        Schema::create('movimentacoes_caixa', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('caixa_id')->constrained('caixas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->enum('tipo', ['venda', 'sangria', 'suprimento', 'abertura', 'fechamento']);
            $table->decimal('valor', 12, 2);
            $table->string('descricao')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'unidade_id']);
            $table->index(['caixa_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_caixa');
    }
};
