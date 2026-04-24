<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->timestamp('certificado_enviado_em')->nullable()->after('certificado_validade');
            $table->string('certificado_cnpj', 14)->nullable()->after('certificado_enviado_em');
            $table->string('certificado_nome', 255)->nullable()->after('certificado_cnpj');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->dropColumn(['certificado_enviado_em', 'certificado_cnpj', 'certificado_nome']);
        });
    }
};
