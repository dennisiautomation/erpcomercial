<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layout livre da etiqueta — posição de cada item desenhada pelo lojista.
 *
 * Até aqui o arranjo era DERIVADO das medidas (EtiquetaFormato::layout()) e cada
 * cliente com um desenho diferente virava um valor novo no enum `estilo`, ou
 * seja: cliente novo = deploy. Com o layout em JSON, o desenho é dado.
 *
 * NULL = continua no layout automático de sempre. Nenhum formato já cadastrado
 * muda de comportamento por causa desta coluna — só quem abrir o editor e salvar
 * passa para o modo livre, e o botão "voltar ao automático" é um UPDATE para NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etiqueta_formatos', function (Blueprint $table) {
            $table->json('layout_json')->nullable()->after('estilo');
        });
    }

    public function down(): void
    {
        Schema::table('etiqueta_formatos', function (Blueprint $table) {
            $table->dropColumn('layout_json');
        });
    }
};
