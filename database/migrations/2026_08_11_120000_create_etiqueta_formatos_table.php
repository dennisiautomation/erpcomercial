<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formatos de etiqueta cadastrados pelo próprio lojista.
 *
 * Antes disso, cada bobina de cliente novo virava um formato hardcoded no
 * print.blade.php (33x22, 36x20 Argox, tag 35x60) — cliente novo = deploy.
 * Aqui a medida é dado, não código. Os formatos fixos continuam existindo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etiqueta_formatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            // Medidas da ETIQUETA em milímetros (o lojista digita em cm na tela).
            $table->decimal('largura_mm', 6, 1);
            $table->decimal('altura_mm', 6, 1);
            $table->unsignedTinyInteger('colunas')->default(1);
            // Espaço entre colunas — a largura da página é colunas*largura + (colunas-1)*espaco
            $table->decimal('espaco_mm', 5, 1)->default(0);
            $table->boolean('mostrar_empresa')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etiqueta_formatos');
    }
};
