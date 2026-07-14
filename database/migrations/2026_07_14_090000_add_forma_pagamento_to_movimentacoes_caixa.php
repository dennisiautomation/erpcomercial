<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes_caixa', function (Blueprint $table) {
            // Forma de pagamento da venda (dinheiro, pix, cartao_credito...).
            // NULL em aberturas/sangrias/suprimentos/fechamentos e em vendas
            // antigas (legado = tratado como dinheiro na conferência).
            $table->string('forma_pagamento', 30)->nullable()->after('valor');
            $table->index(['caixa_id', 'forma_pagamento']);
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes_caixa', function (Blueprint $table) {
            $table->dropIndex(['caixa_id', 'forma_pagamento']);
            $table->dropColumn('forma_pagamento');
        });
    }
};
