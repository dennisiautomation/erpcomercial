<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Juros de parcelamento no cartão de crédito, por loja.
 *
 * Até aqui parcelar era de graça: R$ 1.000 em 1x e em 12x davam o mesmo total e
 * o contas a receber só dividia o valor.
 *
 * `juros_por_parcela` é a tabela da loja: quantidade de parcelas → acréscimo
 * TOTAL em % ({"6": 8.00, "12": 16.00} = 6x encarece 8%, 12x encarece 16%).
 * É o formato em que a adquirente manda a tabela dela, então o lojista confere
 * linha a linha com o extrato da maquininha. Parcela ausente ou 0 = sem juros.
 *
 * Nasce VAZIO — quem não configurar nada continua com o PDV de sempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->json('juros_por_parcela')->nullable()->after('max_parcelas');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn('juros_por_parcela');
        });
    }
};
