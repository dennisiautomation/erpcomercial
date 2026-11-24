<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Estorno da devolução de compra ao fornecedor (24/11/2026): a devolução fica no histórico, marcada. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfe_entrada_devolucoes', function (Blueprint $table) {
            $table->timestamp('estornada_em')->nullable()->after('contas_ajustadas');
            $table->foreignId('estornada_por')->nullable()->after('estornada_em')->constrained('users')->nullOnDelete();
            $table->string('estorno_motivo', 500)->nullable()->after('estornada_por');
        });
    }

    public function down(): void
    {
        Schema::table('nfe_entrada_devolucoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estornada_por');
            $table->dropColumn(['estornada_em', 'estorno_motivo']);
        });
    }
};
