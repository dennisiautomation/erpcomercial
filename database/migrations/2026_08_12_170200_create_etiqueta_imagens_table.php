<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galeria de imagens da empresa para usar nas etiquetas (selo de promoção,
 * logo alternativo, arte da coleção).
 *
 * A imagem é um REGISTRO, não um caminho solto dentro do layout_json: o item da
 * etiqueta guarda só o `imagem_id`, e a impressão resolve o arquivo por aqui,
 * conferindo a empresa. Guardar o caminho direto no JSON deixaria o navegador
 * escolher o que renderizar — e o JSON vem do cliente.
 *
 * Fica solta do formato de propósito: a mesma arte serve para a etiqueta da
 * bobina e para a de A4, sem reenviar o arquivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etiqueta_imagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 60);
            // Caminho relativo no disco 'public' (ex.: etiquetas/3/selo.png).
            $table->string('caminho');
            $table->timestamps();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etiqueta_imagens');
    }
};
