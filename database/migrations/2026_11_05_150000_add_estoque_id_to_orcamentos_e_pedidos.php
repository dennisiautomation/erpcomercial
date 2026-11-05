<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Sai de" no orçamento e no pedido (05/11/2026, chamado DK Med): o vendedor escolhe
 * de qual estoque da loja o produto sai (DK: Teresina/Principal ou São Luís). Null =
 * estoque de venda da loja, como sempre foi.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['orcamentos', 'pedidos'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->foreignId('estoque_id')->nullable()->after('unidade_id')
                    ->constrained('estoques')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['orcamentos', 'pedidos'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropConstrainedForeignId('estoque_id');
            });
        }
    }
};
