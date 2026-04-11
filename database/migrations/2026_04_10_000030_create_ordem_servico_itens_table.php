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
        Schema::create('ordem_servico_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();

            $table->enum('tipo', ['produto', 'servico']);
            $table->foreignId('produto_id')->nullable()->constrained('produtos');
            $table->foreignId('servico_id')->nullable()->constrained('servicos');

            $table->string('descricao');
            $table->decimal('quantidade', 12, 3);
            $table->decimal('preco_unitario', 12, 2);
            $table->decimal('total', 12, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordem_servico_itens');
    }
};
