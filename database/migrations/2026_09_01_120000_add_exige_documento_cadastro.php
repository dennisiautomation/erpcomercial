<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPF/CNPJ opcional no cadastro de clientes e fornecedores, por empresa.
 *
 * Nasce LIGADA (default true) — todas as empresas continuam exigindo o
 * documento exatamente como antes. Desligar é decisão por empresa, no admin.
 *
 * `clientes.cpf_cnpj` já era nullable desde 2026_08_05_100000 (feita para os
 * imports); só `fornecedores` ainda era NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('exige_documento_cadastro')
                ->default(true)
                ->after('politica_estoque_inter_unidade');
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('cpf_cnpj', 18)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('exige_documento_cadastro');
        });

        // Só volta a NOT NULL se não houver fornecedor sem documento — senão o
        // ALTER falharia no meio e deixaria a tabela num estado pior.
        if (! \DB::table('fornecedores')->whereNull('cpf_cnpj')->exists()) {
            Schema::table('fornecedores', function (Blueprint $table) {
                $table->string('cpf_cnpj', 18)->nullable(false)->change();
            });
        }
    }
};
