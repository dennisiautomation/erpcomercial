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
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categorias')->nullOnDelete();

            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->enum('status', ['ativa', 'inativa']);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'parent_id']);
            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
