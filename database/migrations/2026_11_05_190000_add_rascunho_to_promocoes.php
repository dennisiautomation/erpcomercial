<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rascunho de promoção (05/11/2026, pedido do Dennis): salvar no meio do assistente e continuar
 * depois — inclusive por outro atendente. O rascunho guarda o estado do assistente em JSON
 * (`rascunho`) e NÃO vale para preço: só a promoção `publicada` entra no PDV.
 * Datas passam a aceitar vazio (o rascunho pode não ter período ainda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promocoes', function (Blueprint $table) {
            $table->string('situacao', 20)->default('publicada')->after('nome');
            $table->json('rascunho')->nullable()->after('situacao');
            $table->foreignId('atualizada_por')->nullable()->after('criada_por')->constrained('users')->nullOnDelete();
            $table->date('data_inicio')->nullable()->change();
            $table->date('data_fim')->nullable()->change();
            $table->index(['empresa_id', 'situacao']);
        });
    }

    public function down(): void
    {
        Schema::table('promocoes', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'situacao']);
            $table->dropConstrainedForeignId('atualizada_por');
            $table->dropColumn(['situacao', 'rascunho']);
        });
    }
};
