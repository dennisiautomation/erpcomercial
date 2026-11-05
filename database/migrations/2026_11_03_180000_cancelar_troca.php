<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelar troca/devolução (03/11/2026). `status` já tinha 'cancelada' (a base
 * de 2026-04) e as telas/relatórios já ignoram as canceladas; faltava quem e por quê,
 * e o detalhe do que foi abatido em cada parcela (para o cancelamento desfazer
 * conta a conta — antes só o total ficava gravado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devolucoes', function (Blueprint $table) {
            $table->foreignId('cancelado_por')->nullable()->after('observacoes')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelado_em')->nullable()->after('cancelado_por');
            $table->text('motivo_cancelamento')->nullable()->after('cancelado_em');
            $table->json('abatimentos')->nullable()->after('motivo_cancelamento');
        });
    }

    public function down(): void
    {
        Schema::table('devolucoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelado_por');
            $table->dropColumn(['cancelado_em', 'motivo_cancelamento', 'abatimentos']);
        });
    }
};
