<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobrança direta (sem gateway): o cliente paga direto à IA365 e o admin
 * controla contrato mensal/anual, valor, geração de fatura e bloqueio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // null = sem cobrança direta (segue trial/assinatura/regime como antes)
            $table->string('cobranca_periodicidade', 10)->nullable()->after('regime_cobranca'); // mensal|anual
            $table->decimal('cobranca_valor', 10, 2)->nullable()->after('cobranca_periodicidade');
            $table->unsignedTinyInteger('cobranca_dia_vencimento')->nullable()->after('cobranca_valor'); // mensal: 1-28
            $table->date('cobranca_proxima_renovacao')->nullable()->after('cobranca_dia_vencimento');    // anual
            $table->string('cobranca_geracao', 15)->default('automatica')->after('cobranca_proxima_renovacao'); // automatica|manual
            $table->boolean('cobranca_bloqueio_automatico')->default(false)->after('cobranca_geracao');
            $table->unsignedSmallInteger('cobranca_tolerancia_dias')->default(5)->after('cobranca_bloqueio_automatico');
            $table->timestamp('cobranca_suspensa_em')->nullable()->after('cobranca_tolerancia_dias');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'cobranca_periodicidade',
                'cobranca_valor',
                'cobranca_dia_vencimento',
                'cobranca_proxima_renovacao',
                'cobranca_geracao',
                'cobranca_bloqueio_automatico',
                'cobranca_tolerancia_dias',
                'cobranca_suspensa_em',
            ]);
        });
    }
};
