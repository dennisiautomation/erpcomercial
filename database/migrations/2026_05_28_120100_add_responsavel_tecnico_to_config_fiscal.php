<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos do Responsável Técnico — exigidos na NF-e desde a NT 2018/003.
 * Vai no payload dentro de cnpj_responsavel_tecnico, contato_responsavel_tecnico,
 * email_responsavel_tecnico, telefone_responsavel_tecnico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->string('responsavel_tecnico_cnpj', 14)->nullable()->after('certificado_nome');
            $table->string('responsavel_tecnico_nome', 60)->nullable()->after('responsavel_tecnico_cnpj');
            $table->string('responsavel_tecnico_email', 60)->nullable()->after('responsavel_tecnico_nome');
            $table->string('responsavel_tecnico_telefone', 14)->nullable()->after('responsavel_tecnico_email');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->dropColumn([
                'responsavel_tecnico_cnpj',
                'responsavel_tecnico_nome',
                'responsavel_tecnico_email',
                'responsavel_tecnico_telefone',
            ]);
        });
    }
};
