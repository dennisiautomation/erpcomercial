<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobrança da plataforma vinda do caixa.ia365 (16/11/2026).
 *
 * A cobrança (PIX/boleto do Inter, recorrência, WhatsApp, baixa pelo banco)
 * nasce e é baixada no caixa.ia365. O ERP só guarda um espelho da fatura para
 * mostrar a faixa, a tela "Faturas", mandar o e-mail e bloquear.
 *
 * Tudo aditivo: fatura lançada à mão no admin continua igual (origem 'erp').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plataforma_faturas', function (Blueprint $table) {
            $table->string('origem', 12)->default('erp')->after('empresa_id'); // erp | caixa
            $table->unsignedBigInteger('caixa_ref')->nullable()->unique()->after('origem'); // id da cobrança no caixa.ia365
            $table->string('link', 500)->nullable()->after('observacao'); // página da fatura (PIX, boleto, nota)
            $table->text('pix_copia_cola')->nullable()->after('link');
            $table->string('linha_digitavel', 60)->nullable()->after('pix_copia_cola');
            $table->boolean('tem_nota')->default(false)->after('linha_digitavel');
            $table->json('avisos')->nullable()->after('tem_nota'); // {emitida: "2026-11-16 10:00:00", ...} — 1 aviso por tipo
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedBigInteger('caixa_cliente_id')->nullable()->after('cobranca_suspensa_em');
            $table->string('caixa_cliente_nome', 150)->nullable()->after('caixa_cliente_id');
            $table->timestamp('caixa_vinculado_em')->nullable()->after('caixa_cliente_nome');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['caixa_cliente_id', 'caixa_cliente_nome', 'caixa_vinculado_em']);
        });
        Schema::table('plataforma_faturas', function (Blueprint $table) {
            $table->dropUnique(['caixa_ref']);
            $table->dropColumn(['origem', 'caixa_ref', 'link', 'pix_copia_cola', 'linha_digitavel', 'tem_nota', 'avisos']);
        });
    }
};
