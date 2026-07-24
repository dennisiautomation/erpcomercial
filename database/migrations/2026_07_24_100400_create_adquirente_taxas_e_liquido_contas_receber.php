<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Taxas e prazos de recebimento das máquinas de cartão
        Schema::create('adquirente_taxas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');                                     // ex.: Stone, Cielo, PagSeguro
            $table->enum('forma', ['cartao_debito', 'cartao_credito']);
            $table->unsignedTinyInteger('parcelas_de')->default(1);
            $table->unsignedTinyInteger('parcelas_ate')->default(1);
            $table->decimal('taxa_percentual', 5, 2)->default(0);
            $table->unsignedSmallInteger('prazo_dias')->default(1);     // D+N (1ª parcela)
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Previsão de recebimento líquido no contas a receber
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->foreignId('adquirente_taxa_id')->nullable()->after('forma_pagamento')
                ->constrained('adquirente_taxas')->nullOnDelete();
            $table->decimal('taxa_percentual', 5, 2)->nullable()->after('adquirente_taxa_id');
            $table->decimal('valor_liquido', 10, 2)->nullable()->after('taxa_percentual');
        });
    }

    public function down(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adquirente_taxa_id');
            $table->dropColumn(['taxa_percentual', 'valor_liquido']);
        });
        Schema::dropIfExists('adquirente_taxas');
    }
};
