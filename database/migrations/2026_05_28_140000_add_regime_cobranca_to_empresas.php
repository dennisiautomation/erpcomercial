<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que admin (IA365) marque uma empresa-cliente como cortesia,
 * parceiro estratégico ou pós-pago — bypassando trial/assinatura paga.
 *
 *  - regime_cobranca = padrao    : comportamento atual (trial → pago)
 *  - regime_cobranca = cortesia  : nunca expira, mas respeita limites do plano
 *  - regime_cobranca = parceiro  : nunca expira + libera todas as features
 *  - regime_cobranca = pos_pago  : nunca bloqueia (faturamento manual fora)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->enum('regime_cobranca', ['padrao', 'cortesia', 'parceiro', 'pos_pago'])
                  ->default('padrao')
                  ->after('tipo_cobranca');
            $table->string('cortesia_motivo', 255)->nullable()->after('regime_cobranca');
            $table->date('cortesia_concedida_em')->nullable()->after('cortesia_motivo');
            $table->date('cortesia_revisar_em')->nullable()->after('cortesia_concedida_em');
            $table->foreignId('cortesia_concedida_por')->nullable()
                  ->after('cortesia_revisar_em')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->index('regime_cobranca');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['cortesia_concedida_por']);
            $table->dropIndex(['regime_cobranca']);
            $table->dropColumn([
                'regime_cobranca',
                'cortesia_motivo',
                'cortesia_concedida_em',
                'cortesia_revisar_em',
                'cortesia_concedida_por',
            ]);
        });
    }
};
