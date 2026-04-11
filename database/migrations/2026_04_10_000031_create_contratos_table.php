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
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades');

            $table->foreignId('cliente_id')->constrained('clientes');
            $table->text('descricao');

            $table->decimal('valor', 12, 2);
            $table->enum('periodicidade', ['mensal', 'trimestral', 'semestral', 'anual']);

            $table->date('inicio');
            $table->date('fim')->nullable();

            $table->enum('status', ['ativo', 'vencido', 'cancelado', 'suspenso']);

            $table->date('proximo_faturamento')->nullable();

            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
