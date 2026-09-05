<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melhor Envio — frete para OUTRA cidade no Agente IA (05/09/2026).
 *
 * Tudo aditivo e NULL; nenhuma linha existente é tocada.
 *  - empresa_gateways: tokens OAuth da conta Melhor Envio da EMPRESA
 *    (access_token 30 dias + refresh_token; ambos cifrados no model).
 *  - produtos: medidas em cm para a cotação (peso já existia). Produto sem
 *    medida usa o pacote padrão da loja (config do gateway).
 *  - pedidos: que provedor/serviço de frete o cliente escolheu na conversa
 *    (uber_direct | melhor_envio; PAC/SEDEX/Jadlog…; prazo em dias úteis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_gateways', function (Blueprint $table) {
            $table->text('access_token')->nullable()->after('client_secret');
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->timestamp('token_expira_em')->nullable()->after('refresh_token');
        });
        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('altura_cm', 8, 2)->nullable()->after('peso_liquido');
            $table->decimal('largura_cm', 8, 2)->nullable()->after('altura_cm');
            $table->decimal('comprimento_cm', 8, 2)->nullable()->after('largura_cm');
        });
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('frete_provedor', 20)->nullable()->after('frete_valor');
            $table->string('frete_servico_id', 20)->nullable()->after('frete_provedor');
            $table->string('frete_servico_nome', 100)->nullable()->after('frete_servico_id');
            $table->unsignedSmallInteger('frete_prazo_dias')->nullable()->after('frete_servico_nome');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['frete_provedor', 'frete_servico_id', 'frete_servico_nome', 'frete_prazo_dias']);
        });
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['altura_cm', 'largura_cm', 'comprimento_cm']);
        });
        Schema::table('empresa_gateways', function (Blueprint $table) {
            $table->dropColumn(['access_token', 'refresh_token', 'token_expira_em']);
        });
    }
};
