<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Folha de pagamento (10/11/2026, Fase 2). A folha NÃO paga nada: ela só LANÇA contas no
 * Contas a Pagar. Aqui ficam os valores que se repetem todo mês:
 *  - `funcionario_fichas`: salário e vale de cada funcionário (1 ficha por usuário);
 *  - `folha_itens`: guias (INSS, FGTS...) e benefícios (vale-transporte...) de valor digitado,
 *    da empresa (funcionario_id NULL) ou de um funcionário;
 *  - `contas_pagar.folha_ref`: de qual linha da folha a conta nasceu (`salario`, `vale`,
 *    `item:<id>`) — com a competência, é o que impede lançar o mesmo mês duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funcionario_fichas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('cargo', 100)->nullable();
            $table->date('admissao')->nullable();
            $table->date('demissao')->nullable();
            $table->decimal('salario', 12, 2)->default(0);
            $table->unsignedTinyInteger('dia_pagamento')->default(5);
            // true = salário de outubro vence em novembro (o usual); false = vence no próprio mês
            $table->boolean('paga_mes_seguinte')->default(true);
            $table->decimal('vale_valor', 12, 2)->default(0);
            $table->unsignedTinyInteger('vale_dia')->default(20);
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $table->boolean('na_folha')->default(true);
            $table->string('observacoes', 500)->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'na_folha']);
        });

        Schema::create('folha_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->enum('tipo', ['encargo', 'beneficio', 'outro'])->default('encargo');
            $table->string('nome', 100);                 // "Guia INSS", "Vale-transporte"
            $table->string('favorecido_nome', 150)->nullable(); // "INSS"; vazio + funcionário = paga ao funcionário
            $table->decimal('valor', 12, 2)->default(0);
            $table->unsignedTinyInteger('dia_vencimento')->default(20);
            $table->boolean('vence_mes_seguinte')->default(true);
            $table->foreignId('plano_conta_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['empresa_id', 'ativo']);
        });

        Schema::table('contas_pagar', function (Blueprint $table) {
            if (! Schema::hasColumn('contas_pagar', 'folha_ref')) {
                $table->string('folha_ref', 40)->nullable()->after('serie_id');
                $table->index(['empresa_id', 'competencia', 'folha_ref'], 'contas_pagar_folha_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            if (Schema::hasColumn('contas_pagar', 'folha_ref')) {
                $table->dropIndex('contas_pagar_folha_idx');
                $table->dropColumn('folha_ref');
            }
        });
        Schema::dropIfExists('folha_itens');
        Schema::dropIfExists('funcionario_fichas');
    }
};
