<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valor de cada parcela no select do PDV, por loja.
 *
 * A lista de parcelas do PDV sempre disse só "2x", "3x". Quem cadastra tabela de
 * juros precisa ver "6x de R$ 180,00 · total R$ 1.080,00", senão o caixa não sabe
 * o que falar para o cliente. Mas a loja que NÃO cobra juros não pediu tela nova.
 *
 * Por isso a coluna nasce FALSE: quem não ligar continua com o PDV de sempre.
 *
 * ⚠️ O flag só LIGA, nunca desliga: loja com tabela de juros cadastrada mostra o
 * valor da parcela de qualquer jeito (ver JurosParcelamentoService::mostrarValorParcelas).
 * Esconder o acréscimo de uma venda que encarece é surpreender o cliente no total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->boolean('pdv_mostrar_valor_parcelas')->default(false)->after('juros_por_parcela');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn('pdv_mostrar_valor_parcelas');
        });
    }
};
