<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_precos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->enum('modalidade', ['dinheiro_pix', 'debito', 'credito']);
            $table->decimal('valor', 10, 2);
            $table->timestamps();

            $table->unique(['produto_id', 'modalidade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_precos');
    }
};
