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
        Schema::create('plano_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->string('codigo'); // e.g., 1, 1.1, 1.1.1
            $table->string('nome');
            $table->enum('tipo', ['receita', 'despesa', 'custo']);
            $table->enum('natureza', ['sintetica', 'analitica']); // sintetica=grupo, analitica=lancavel
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plano_contas');
    }
};
