<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Config do Agente IA por empresa (módulo ativável no /admin).
 *
 * Enquanto `ativo` = false a empresa não gera embedding nenhum — quem não
 * usa o agente não gera custo OpenAI nem carga nos workers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agente_ia_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas');
            $table->boolean('ativo')->default(false);
            // Vendedor atribuído aos pedidos criados pelo agente (fallback: dono)
            $table->foreignId('vendedor_padrao_id')->nullable()->constrained('users');
            $table->timestamp('indexado_em')->nullable();
            $table->unsignedInteger('produtos_indexados')->default(0);
            $table->text('ultima_falha')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_ia_configs');
    }
};
