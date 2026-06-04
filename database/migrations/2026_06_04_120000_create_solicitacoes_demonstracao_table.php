<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads de demonstração capturados pela landing pública (/).
 * NÃO é multi-tenant: são contatos pré-venda, sem empresa_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes_demonstracao', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('empresa');
            $table->string('email');
            $table->string('whatsapp', 40);
            $table->string('qtd_lojas', 40)->nullable();
            $table->enum('status', ['novo', 'contatado', 'convertido', 'descartado'])
                  ->default('novo')->index();
            $table->string('origem', 60)->default('landing');
            $table->ipAddress('ip')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_demonstracao');
    }
};
