<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personalização de layout dos formatos FIXOS (2x5, 3x7, 4x10, térmicas).
 *
 * Os formatos fixos continuam existindo e sendo escolhidos pela mesma chave de
 * sempre — eles são constante de código, compartilhada por todas as empresas, e
 * ninguém edita a constante. Quando o lojista abre o editor em cima de um deles,
 * nasce AQUI um registro que guarda só o desenho, amarrado ao fixo pelo
 * `formato_base` e à empresa pelo `empresa_id`.
 *
 * Na impressão, o formato fixo continua mandando na página e na grade (é o que
 * faz a folha A4 sair com 10/21/40 etiquetas); o registro daqui manda apenas no
 * miolo de cada etiqueta. Empresa sem personalização imprime idêntico a antes.
 *
 * Estes registros NÃO aparecem em "Meus formatos" (a lista filtra por
 * formato_base NULL) — senão o mesmo formato apareceria duas vezes na tela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etiqueta_formatos', function (Blueprint $table) {
            $table->string('formato_base', 40)->nullable()->after('empresa_id');
            $table->unique(['empresa_id', 'formato_base']);
        });
    }

    public function down(): void
    {
        Schema::table('etiqueta_formatos', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'formato_base']);
            $table->dropColumn('formato_base');
        });
    }
};
