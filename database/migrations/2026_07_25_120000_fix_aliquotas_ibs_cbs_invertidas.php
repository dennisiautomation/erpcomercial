<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * As versões anteriores gravavam as alíquotas-teste 2026 INVERTIDAS
 * (IBS 0,9% / CBS 0,1% — o correto pela LC 214/2025 é CBS 0,9% / IBS 0,1%).
 *
 * Anula exatamente o par invertido legado para que essas configs voltem a
 * usar os defaults corretos do ReformaTributariaCalculator. Configurações
 * com valores personalizados (qualquer outro par) não são tocadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuracoes_fiscais')
            ->where('ibs_aliquota_padrao', 0.9)
            ->where('cbs_aliquota_padrao', 0.1)
            ->update([
                'ibs_aliquota_padrao' => null,
                'cbs_aliquota_padrao' => null,
            ]);
    }

    public function down(): void
    {
        // Sem rollback — o par invertido era um bug, não um estado desejado.
    }
};
