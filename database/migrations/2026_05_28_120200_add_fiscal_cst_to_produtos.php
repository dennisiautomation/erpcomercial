<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Substitui os hard-codes do payload NF-e/NFC-e:
 *   - cst_pis = '99', cst_cofins = '99', cst_ipi = '50'
 *   - icms_modalidade_bc = '3' (valor da operação)
 *
 * Sem isso, produtos isentos, monofásicos (combustível/bebida) e ST/MVA quebravam.
 *
 * Defaults preservam o comportamento atual para retrocompatibilidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('cst_pis', 3)->default('99')->after('pis_aliquota');
            $table->string('cst_cofins', 3)->default('99')->after('cofins_aliquota');
            $table->string('cst_ipi', 3)->default('50')->after('ipi_aliquota');
            $table->string('icms_modalidade_bc', 1)->default('3')->after('cst_csosn');
            $table->string('icms_modalidade_bc_st', 1)->default('4')->after('icms_modalidade_bc'); // 4 = MVA
            $table->decimal('mva_st', 8, 2)->nullable()->after('icms_modalidade_bc_st');
            $table->decimal('percentual_tributos_ibpt', 5, 2)->nullable()->after('mva_st')->comment('IBPT para cálculo valor_total_tributos (LC 165/2018)');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn([
                'cst_pis', 'cst_cofins', 'cst_ipi',
                'icms_modalidade_bc', 'icms_modalidade_bc_st',
                'mva_st', 'percentual_tributos_ibpt',
            ]);
        });
    }
};
