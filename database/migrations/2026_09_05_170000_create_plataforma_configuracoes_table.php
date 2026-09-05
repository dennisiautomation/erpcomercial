<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações da PLATAFORMA (IA365), chave → valor, cifradas (05/09/2026).
 *
 * Primeiro uso: credenciais do aplicativo "IA365" no Melhor Envio (client_id,
 * secret, e-mail de suporte do User-Agent, ambiente). São da plataforma, não
 * de uma empresa-cliente — cada empresa só AUTORIZA a própria conta via OAuth
 * (tokens em empresa_gateways). Tela: /admin/integracoes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plataforma_configuracoes', function (Blueprint $table) {
            $table->string('chave', 80)->primary();
            $table->text('valor')->nullable(); // cast encrypted no model
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plataforma_configuracoes');
    }
};
