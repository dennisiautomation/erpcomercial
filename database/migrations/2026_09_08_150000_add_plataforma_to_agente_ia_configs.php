<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sincronização automática ERP → agente do app.ia365 (08/09/2026).
 *
 * O ERP nunca soube onde a plataforma mora: ela chama o ERP, nunca o
 * contrário. Agora o `POST /agente/ativar` recebe `callback_url` e grava
 * aqui; toda mudança de integração (empresa_gateways) vira um aviso
 * assinado para esse endereço (NotificarPlataformaIntegracaoJob).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agente_ia_configs', function (Blueprint $table) {
            // Endereço-base da plataforma (ex.: http://172.17.0.1:3023 — o
            // container do ERP não alcança app.ia365.com.br pelo IP público).
            $table->string('plataforma_url', 255)->nullable()->after('ultima_falha');
            // Token de integração que registrou o endereço: o segredo do HMAC
            // é o token_hash DESSE token (o mesmo gsn_ que a plataforma guarda).
            $table->foreignId('plataforma_token_id')->nullable()->after('plataforma_url')
                ->constrained('integracao_tokens')->nullOnDelete();
            $table->timestamp('plataforma_notificado_em')->nullable()->after('plataforma_token_id');
            $table->text('plataforma_ultima_falha')->nullable()->after('plataforma_notificado_em');
        });
    }

    public function down(): void
    {
        Schema::table('agente_ia_configs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plataforma_token_id');
            $table->dropColumn(['plataforma_url', 'plataforma_notificado_em', 'plataforma_ultima_falha']);
        });
    }
};
