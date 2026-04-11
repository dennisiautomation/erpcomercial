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
        Schema::create('servicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('codigo_lc116', 10)->nullable();
            $table->string('descricao');
            $table->decimal('valor_padrao', 12, 2);
            $table->decimal('iss_aliquota', 5, 2)->nullable();
            $table->string('cnae', 10)->nullable();

            $table->enum('status', ['ativo', 'inativo']);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicos');
    }
};
