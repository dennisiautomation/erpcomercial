<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compra do cartão que o banco ainda não efetivou (22/11/2026): o Itaú manda a
 * fatura aberta com status PENDING. Antes era descartada; agora fica visível
 * como 'no_banco' (fora da conciliação) até virar POSTED.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE lancamentos_bancarios MODIFY status ENUM('pendente','sugerido','conciliado','ignorado','removido','no_banco') NOT NULL DEFAULT 'pendente'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::table('lancamentos_bancarios')->where('status', 'no_banco')->update(['status' => 'removido']);
        DB::statement("ALTER TABLE lancamentos_bancarios MODIFY status ENUM('pendente','sugerido','conciliado','ignorado','removido') NOT NULL DEFAULT 'pendente'");
    }
};
