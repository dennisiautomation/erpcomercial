<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grade (tamanho/cor) no dia a dia do ERP — 16/11/2026.
 *
 * Chave POR LOJA, desligada por padrão: com ela ligada, o PDV e a lista de
 * produtos mostram a grade como UMA linha (e o PDV abre os botões de tamanho
 * e cor). Desligada, nada muda: cada variação continua aparecendo sozinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->boolean('agrupar_grade')->default(false)->after('pdv_escolher_estoque');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn('agrupar_grade');
        });
    }
};
