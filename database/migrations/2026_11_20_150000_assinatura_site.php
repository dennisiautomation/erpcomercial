<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura feita pela própria empresa, dentro do sistema (20/11/2026).
 *
 * - `empresas.assinatura_forma`: como ela escolheu pagar (pix | boleto | cartao).
 * - `empresas.assinatura_solicitada_em`: clicou "Assinar agora" (a 1ª mensalidade nasceu no caixa.ia365).
 * - `empresas.assinatura_ativada_em`: a 1ª mensalidade foi paga — deixa de ser teste.
 * - `plataforma_faturas.cartao_*`: link de cartão da InfinitePay gerado para a fatura e o que voltou dele.
 * - `plataforma_faturas.caixa_baixa_pendente`: paga no cartão aqui, falta o caixa.ia365 dar a baixa lá.
 *
 * Tudo aditivo e nulo: nenhuma empresa nem fatura existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'assinatura_forma')) {
                $table->string('assinatura_forma', 10)->nullable()->after('origem_cadastro');
                $table->timestamp('assinatura_solicitada_em')->nullable()->after('assinatura_forma');
                $table->timestamp('assinatura_ativada_em')->nullable()->after('assinatura_solicitada_em');
            }
        });

        Schema::table('plataforma_faturas', function (Blueprint $table) {
            if (! Schema::hasColumn('plataforma_faturas', 'cartao_nsu')) {
                $table->string('cartao_link', 500)->nullable();
                $table->string('cartao_nsu', 60)->nullable()->index();
                $table->string('cartao_slug', 120)->nullable();
                $table->string('cartao_transaction_nsu', 120)->nullable();
                $table->timestamp('cartao_gerado_em')->nullable();
                $table->timestamp('cartao_pago_em')->nullable();
                $table->boolean('caixa_baixa_pendente')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('plataforma_faturas', function (Blueprint $table) {
            if (Schema::hasColumn('plataforma_faturas', 'cartao_nsu')) {
                $table->dropColumn(['cartao_link', 'cartao_nsu', 'cartao_slug', 'cartao_transaction_nsu', 'cartao_gerado_em', 'cartao_pago_em', 'caixa_baixa_pendente']);
            }
        });
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'assinatura_forma')) {
                $table->dropColumn(['assinatura_forma', 'assinatura_solicitada_em', 'assinatura_ativada_em']);
            }
        });
    }
};
