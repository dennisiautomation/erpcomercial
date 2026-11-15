<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loja online (e-commerce) por empresa — 14/11/2026.
 *
 * - lojas_online: UMA por empresa. `ativo` é a chave da PLATAFORMA (admin liga;
 *   no futuro, a autocontratação); `publicada` é a do lojista (no ar ou não).
 * - produto_fotos: fotos além da capa (`produtos.foto` continua sendo a capa).
 * - produtos.loja_online: o produto aparece na loja (default false — ninguém
 *   entra na vitrine sem alguém marcar).
 * - pedidos.loja_token: endereço público do pedido para o comprador acompanhar.
 *
 * Tudo aditivo e nullable/default: o código antigo não lê nada daqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lojas_online', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
            $table->boolean('ativo')->default(false);
            $table->boolean('publicada')->default(false);
            $table->string('slug', 60)->unique();
            $table->string('dominio', 190)->nullable()->unique();
            $table->string('nome', 120);
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->foreignId('estoque_id')->nullable()->constrained('estoques')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('modelo', 30)->default('classico');
            $table->json('tema')->nullable();
            $table->json('blocos')->nullable();
            $table->json('rascunho')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('publicada_em')->nullable();
            $table->timestamps();
        });

        Schema::create('produto_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('caminho', 255);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
            $table->index(['produto_id', 'ordem']);
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->boolean('loja_online')->default(false)->after('status');
            $table->boolean('loja_online_destaque')->default(false)->after('loja_online');
            $table->index(['empresa_id', 'loja_online']);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('loja_token', 40)->nullable()->unique()->after('canal');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropUnique(['loja_token']);
            $table->dropColumn('loja_token');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'loja_online']);
            $table->dropColumn(['loja_online', 'loja_online_destaque']);
        });

        Schema::dropIfExists('produto_fotos');
        Schema::dropIfExists('lojas_online');
    }
};
