<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regras_icms', function (Blueprint $table) {
            $table->id();
            $table->string('uf_origem', 2);
            $table->string('uf_destino', 2);
            $table->decimal('aliquota_interna', 5, 2);        // alíquota ICMS interna do destino
            $table->decimal('aliquota_interestadual', 5, 2);   // 7% ou 12% conforme origem/destino
            $table->decimal('mva', 8, 2)->default(0);          // Margem Valor Agregado (%)
            $table->decimal('fcp', 5, 2)->default(0);          // Fundo Combate Pobreza (%)
            $table->boolean('tem_st')->default(false);
            $table->timestamps();
            $table->unique(['uf_origem', 'uf_destino']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regras_icms');
    }
};
