<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fornecedor do produto — OPCIONAL (02/09/2026).
 *
 * Até aqui não havia vínculo nenhum entre produto e fornecedor: quem comprava
 * de quem só existia na cabeça do lojista. A coluna nasce nullable e todos os
 * produtos que já existem ficam sem fornecedor — nenhum comportamento muda
 * para quem não usa o campo.
 *
 * `nullOnDelete` de propósito: excluir um fornecedor não pode apagar nem
 * travar o produto, que continua vendável. O produto só perde a referência.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignId('fornecedor_id')
                ->nullable()
                ->after('categoria_id')
                ->constrained('fornecedores')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['fornecedor_id']);
            $table->dropColumn('fornecedor_id');
        });
    }
};
