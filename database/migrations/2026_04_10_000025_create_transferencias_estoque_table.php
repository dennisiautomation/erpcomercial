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
        Schema::create('transferencias_estoque', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_origem_id')->constrained('unidades');
            $table->foreignId('unidade_destino_id')->constrained('unidades');

            $table->foreignId('user_solicitante_id')->constrained('users');
            $table->foreignId('user_aprovador_id')->nullable()->constrained('users');

            $table->enum('status', ['solicitada', 'aprovada', 'em_transito', 'concluida', 'cancelada']);

            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transferencia_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transferencia_estoque_id')->constrained('transferencias_estoque')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');

            $table->decimal('quantidade', 12, 3);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transferencia_itens');
        Schema::dropIfExists('transferencias_estoque');
    }
};
