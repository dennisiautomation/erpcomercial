<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot fiscal por item: garante que re-emissão / consulta histórica
 * use os dados do momento da venda, não os atuais do produto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->string('snapshot_ncm', 10)->nullable()->after('total');
            $table->string('snapshot_cest', 10)->nullable()->after('snapshot_ncm');
            $table->string('snapshot_cfop', 10)->nullable()->after('snapshot_cest');
            $table->string('snapshot_cst_csosn', 10)->nullable()->after('snapshot_cfop');
            $table->string('snapshot_cst_pis', 3)->nullable()->after('snapshot_cst_csosn');
            $table->string('snapshot_cst_cofins', 3)->nullable()->after('snapshot_cst_pis');
            $table->string('snapshot_cst_ipi', 3)->nullable()->after('snapshot_cst_cofins');
            $table->string('snapshot_origem', 1)->nullable()->after('snapshot_cst_ipi');
            $table->decimal('snapshot_icms_aliquota', 5, 2)->nullable()->after('snapshot_origem');
            $table->decimal('snapshot_pis_aliquota', 5, 2)->nullable()->after('snapshot_icms_aliquota');
            $table->decimal('snapshot_cofins_aliquota', 5, 2)->nullable()->after('snapshot_pis_aliquota');
            $table->decimal('snapshot_ipi_aliquota', 5, 2)->nullable()->after('snapshot_cofins_aliquota');
            $table->string('snapshot_unidade_medida', 6)->nullable()->after('snapshot_ipi_aliquota');
        });
    }

    public function down(): void
    {
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->dropColumn([
                'snapshot_ncm', 'snapshot_cest', 'snapshot_cfop', 'snapshot_cst_csosn',
                'snapshot_cst_pis', 'snapshot_cst_cofins', 'snapshot_cst_ipi',
                'snapshot_origem', 'snapshot_icms_aliquota', 'snapshot_pis_aliquota',
                'snapshot_cofins_aliquota', 'snapshot_ipi_aliquota', 'snapshot_unidade_medida',
            ]);
        });
    }
};
