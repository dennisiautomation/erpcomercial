<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QR Code + protocolo de autorização da SEFAZ.
 *
 * O cupom térmico do ERP (cupom-nao-fiscal em modo DANFE NFC-e) já esperava
 * `qrcode_url` e `protocolo` — mas as colunas nunca existiram, então o cupom
 * fiscal imprimia sem QR (obrigatório na NFC-e). A Focus devolve os dois na
 * emissão/consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->text('qrcode_url')->nullable()->after('danfe_url');
            $table->string('protocolo', 30)->nullable()->after('qrcode_url');
        });
    }

    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropColumn(['qrcode_url', 'protocolo']);
        });
    }
};
