<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Condições de pagamento e entrega por orçamento (22/10/2026).
 *
 * A tela de novo orçamento já vem com o texto padrão da loja (Configurações da Loja →
 * Impressão do Orçamento) e o vendedor ajusta para aquele cliente. NULL = a folha usa
 * o padrão da loja — orçamentos antigos ficam assim, sem migrar dado nenhum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            $table->text('condicoes')->nullable()->after('observacoes_externas');
        });
    }

    public function down(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            $table->dropColumn('condicoes');
        });
    }
};
