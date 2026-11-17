<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Open Finance, 4ª entrega (17/11/2026): saldo de cada conta conectada (o banco atualiza a cada 6 h). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_bancarias', function (Blueprint $table) {
            $table->decimal('saldo', 14, 2)->nullable();
            $table->decimal('saldo_bloqueado', 14, 2)->nullable();
            $table->decimal('saldo_aplicado', 14, 2)->nullable();      // aplicação automática (o banco informa à parte)
            $table->timestamp('saldo_atualizado_em')->nullable();      // hora que o BANCO informou
            $table->timestamp('saldo_proxima_em')->nullable();         // antes disso o banco devolve o mesmo valor
        });
    }

    public function down(): void
    {
        Schema::table('contas_bancarias', function (Blueprint $table) {
            $table->dropColumn(['saldo', 'saldo_bloqueado', 'saldo_aplicado', 'saldo_atualizado_em', 'saldo_proxima_em']);
        });
    }
};
