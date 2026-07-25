<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * URL de consulta pública da nota no site da SEFAZ do estado — item obrigatório
 * do DANFE NFC-e ("Consulte pela chave de acesso em <url>", Manual DANFE NFC-e).
 * A Focus devolve em `url_consulta_nf`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->string('url_consulta')->nullable()->after('qrcode_url');
        });
    }

    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropColumn('url_consulta');
        });
    }
};
