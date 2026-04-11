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
        Schema::create('devolucao_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('devolucao_id')->constrained('devolucoes')->cascadeOnDelete();
            $table->foreignId('venda_item_id')->constrained('venda_itens')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();

            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor_unitario', 12, 2);
            $table->decimal('total', 12, 2);

            $table->timestamps();
            $table->softDeletes();

            $table->index('devolucao_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devolucao_itens');
    }
};
