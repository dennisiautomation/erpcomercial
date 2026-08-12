<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens da API de Integração (somente leitura) — consumida pelo Gersen.
 *
 * Um token por sistema consumidor, escopado à EMPRESA (quem mapeia unidade
 * e vendedor é o consumidor). Só o SHA-256 é persistido — o valor em claro
 * aparece uma única vez, na tela do admin que o gerou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integracao_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('nome', 80);               // ex.: "Gersen"
            $table->char('token_hash', 64)->unique(); // sha256 do token em claro
            $table->boolean('ativo')->default(true);

            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();

            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['empresa_id', 'ativo']);
        });

        // A API lista vendas por (loja, período de created_at) — não havia
        // índice que servisse essa consulta.
        Schema::table('vendas', function (Blueprint $table) {
            $table->index(['unidade_id', 'created_at'], 'vendas_unidade_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropIndex('vendas_unidade_created_idx');
        });

        Schema::dropIfExists('integracao_tokens');
    }
};
