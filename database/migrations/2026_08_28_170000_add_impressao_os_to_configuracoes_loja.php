<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Textos e blocos da impressão da Ordem de Serviço, por loja.
 *
 * Antes disso a OS impressa era HTML fixo no `print.blade.php`: incluir termo de
 * garantia ou texto legal exigia mexer no código. Tudo nasce vazio/ligado — a OS
 * de quem não configurar nada sai idêntica à de sempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->text('os_cabecalho')->nullable()->after('padrao_impressao');
            $table->text('os_termos_garantia')->nullable()->after('os_cabecalho');
            $table->text('os_texto_legal')->nullable()->after('os_termos_garantia');
            $table->text('os_rodape')->nullable()->after('os_texto_legal');

            $table->boolean('os_mostrar_assinatura')->default(true)->after('os_rodape');
            $table->boolean('os_mostrar_laudo')->default(true)->after('os_mostrar_assinatura');
            $table->boolean('os_mostrar_valores')->default(true)->after('os_mostrar_laudo');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn([
                'os_cabecalho',
                'os_termos_garantia',
                'os_texto_legal',
                'os_rodape',
                'os_mostrar_assinatura',
                'os_mostrar_laudo',
                'os_mostrar_valores',
            ]);
        });
    }
};
