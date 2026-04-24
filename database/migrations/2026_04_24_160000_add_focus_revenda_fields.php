<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos do modelo revenda Focus NFe.
 *
 * A IA365 opera como revenda: tem um Token Principal de Produção
 * (master, em .env) que cria empresas-filhas via POST /v2/empresas.
 * Cada empresa-filha recebe seu próprio focus_empresa_id e dois
 * tokens (produção e homologação) — guardados aqui.
 *
 * O focus_token legado vira alias do focus_token_producao (mantém
 * retrocompatibilidade durante transição). O webhook_secret é único
 * por unidade e valida o Authorization header no nosso endpoint
 * /webhooks/focusnfe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->unsignedInteger('focus_empresa_id')->nullable()->after('focus_token');
            $table->string('focus_token_producao', 255)->nullable()->after('focus_empresa_id');
            $table->string('focus_token_homologacao', 255)->nullable()->after('focus_token_producao');
            $table->string('webhook_secret', 64)->nullable()->after('focus_token_homologacao');
            $table->timestamp('focus_sincronizado_em')->nullable()->after('webhook_secret');

            $table->index('focus_empresa_id');
        });

        // Migra focus_token existente para focus_token_producao (quando ambiente era producao)
        // ou focus_token_homologacao (quando ambiente era homologacao).
        \DB::table('configuracoes_fiscais')
            ->whereNotNull('focus_token')
            ->where('ambiente', 'producao')
            ->update(['focus_token_producao' => \DB::raw('focus_token')]);

        \DB::table('configuracoes_fiscais')
            ->whereNotNull('focus_token')
            ->where('ambiente', 'homologacao')
            ->update(['focus_token_homologacao' => \DB::raw('focus_token')]);
    }

    public function down(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->dropIndex(['focus_empresa_id']);
            $table->dropColumn([
                'focus_empresa_id',
                'focus_token_producao',
                'focus_token_homologacao',
                'webhook_secret',
                'focus_sincronizado_em',
            ]);
        });
    }
};
