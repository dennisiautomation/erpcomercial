<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->json('focus_webhook_ids')->nullable()->after('webhook_secret');
            $table->timestamp('webhooks_sincronizados_em')->nullable()->after('focus_webhook_ids');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->dropColumn(['focus_webhook_ids', 'webhooks_sincronizados_em']);
        });
    }
};
