<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O faturamento de pedidos grava vendas.tipo='pedido' (PedidoController) mas o
 * enum de produção só tinha pdv/balcao/online — com sql_mode STRICT o INSERT
 * estourava. Aproveita e adiciona 'importada' (vendas históricas de outro
 * sistema, sem estoque/caixa/fiscal).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE vendas MODIFY tipo ENUM('pdv','balcao','online','pedido','importada') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vendas MODIFY tipo ENUM('pdv','balcao','online') NOT NULL");
    }
};
