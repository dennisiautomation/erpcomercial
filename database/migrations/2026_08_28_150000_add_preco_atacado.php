<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preço de atacado.
 *
 * O atacado entra como 4ª modalidade de `produto_precos` (mesma mecânica dos
 * overrides de débito/crédito) e o cliente ganha a flag que diz qual tabela o
 * PDV assume. Nasce neutro: sem preço de atacado cadastrado e com todo cliente
 * em `varejo`, o sistema precifica exatamente como antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE produto_precos MODIFY COLUMN modalidade
             ENUM('dinheiro_pix','debito','credito','atacado') NOT NULL"
        );

        Schema::table('clientes', function (Blueprint $table) {
            $table->enum('tipo_preco', ['varejo', 'atacado'])
                ->default('varejo')
                ->after('limite_credito');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('tipo_preco');
        });

        DB::table('produto_precos')->where('modalidade', 'atacado')->delete();

        DB::statement(
            "ALTER TABLE produto_precos MODIFY COLUMN modalidade
             ENUM('dinheiro_pix','debito','credito') NOT NULL"
        );
    }
};
