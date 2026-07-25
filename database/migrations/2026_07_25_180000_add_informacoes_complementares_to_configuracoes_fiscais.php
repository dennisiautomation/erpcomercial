<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensagem fixa do rodapé das notas (o "Informações complementares" que o lojista
 * tinha no sistema anterior). Vai em `informacoes_adicionais_contribuinte` na NF-e
 * e na NFC-e, concatenada com as observações da venda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->text('informacoes_complementares')->nullable()->after('serie_nfse');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->dropColumn('informacoes_complementares');
        });
    }
};
