<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reforma Tributária (EC 132/2023) + padrão NFS-e Nacional.
 *
 * A partir de 2026 a Receita Federal introduz cobrança-teste dos novos tributos:
 *   - IBS (Imposto sobre Bens e Serviços)        — estadual/municipal
 *   - CBS (Contribuição sobre Bens e Serviços)   — federal
 *   - IS  (Imposto Seletivo)                     — federal, sobre itens nocivos
 *
 * Substituem gradualmente ICMS, ISS, PIS, COFINS, IPI em transição 2026-2033.
 * Em 2026 as alíquotas iniciais são de teste (CBS 0,1% + IBS 0,9%) com
 * compensação via PIS/COFINS — mas os campos nos leiautes da NF-e/NFS-e
 * já precisam existir para quem quer se antecipar.
 *
 * Também abrimos suporte ao padrão NFS-e Nacional (Portal Nacional via RFB),
 * que está substituindo gradualmente as prefeituras municipais.
 *
 * Campos adicionados são todos nullable/false por padrão — plataforma fica
 * retrocompatível, cada empresa habilita quando quiser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Reforma Tributária (EC 132/2023)
            $table->decimal('ibs_aliquota', 6, 4)->nullable()->after('ipi_aliquota');
            $table->decimal('cbs_aliquota', 6, 4)->nullable()->after('ibs_aliquota');
            $table->decimal('is_aliquota', 6, 4)->nullable()->after('cbs_aliquota');
            // CST IBS/CBS (padrão novo, 3 dígitos — ex: 000, 050, 090)
            $table->string('cst_ibs_cbs', 3)->nullable()->after('is_aliquota');
            // Classificação tributária do IBS (cClassTrib: 000001..etc)
            $table->string('classificacao_ibs', 10)->nullable()->after('cst_ibs_cbs');
        });

        Schema::table('servicos', function (Blueprint $table) {
            // Reforma Tributária para serviços
            $table->decimal('ibs_aliquota', 6, 4)->nullable()->after('iss_aliquota');
            $table->decimal('cbs_aliquota', 6, 4)->nullable()->after('ibs_aliquota');
            $table->string('cst_ibs_cbs', 3)->nullable()->after('cbs_aliquota');
            $table->string('classificacao_ibs', 10)->nullable()->after('cst_ibs_cbs');
        });

        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            // Padrão NFS-e: 'municipal' (legado por cidade) ou 'nacional' (Portal RFB)
            $table->string('nfse_padrao', 20)->default('municipal')->after('emite_nfse');
            // Flags para começar a calcular e incluir tributos da Reforma nas notas
            $table->boolean('ibs_ativo')->default(false)->after('nfse_padrao');
            $table->boolean('cbs_ativo')->default(false)->after('ibs_ativo');
            $table->boolean('is_ativo')->default(false)->after('cbs_ativo');
            // Alíquotas padrão (em percentual) — aplicadas quando o produto não define o seu
            $table->decimal('ibs_aliquota_padrao', 6, 4)->nullable()->after('is_ativo');
            $table->decimal('cbs_aliquota_padrao', 6, 4)->nullable()->after('ibs_aliquota_padrao');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn([
                'ibs_aliquota',
                'cbs_aliquota',
                'is_aliquota',
                'cst_ibs_cbs',
                'classificacao_ibs',
            ]);
        });

        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn([
                'ibs_aliquota',
                'cbs_aliquota',
                'cst_ibs_cbs',
                'classificacao_ibs',
            ]);
        });

        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->dropColumn([
                'nfse_padrao',
                'ibs_ativo',
                'cbs_ativo',
                'is_ativo',
                'ibs_aliquota_padrao',
                'cbs_aliquota_padrao',
            ]);
        });
    }
};
