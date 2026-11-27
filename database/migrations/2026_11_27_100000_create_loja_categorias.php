<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorias da loja online (27/11/2026): o lojista monta as categorias da VITRINE
 * (nome, ordem, foto) e diz o que vai dentro — categorias do cadastro e/ou produtos
 * avulsos. Não muda o cadastro de categorias do ERP. Loja sem nenhuma continua
 * mostrando as categorias do cadastro, como sempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loja_categorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->string('nome', 60);
            $table->unsignedInteger('ordem')->default(0);
            $table->string('foto')->nullable();
            $table->boolean('ativa')->default(true);
            $table->json('categorias_erp')->nullable(); // ids de `categorias` que entram inteiras
            $table->json('produtos')->nullable();       // ids de `produtos` escolhidos um a um
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loja_categorias');
    }
};
