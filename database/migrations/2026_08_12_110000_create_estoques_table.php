<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vários estoques dentro da MESMA loja (salão, depósito, avaria, vitrine...).
 *
 * Toda unidade existente ganha um estoque "Principal" (is_padrao + permite_venda),
 * para onde a migration seguinte joga todo o histórico de movimentação. Quem não
 * criar um segundo estoque não vê diferença em tela nenhuma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoques', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();

            $table->string('nome', 80);
            $table->string('codigo', 20)->nullable();

            // O estoque que o PDV/venda baixa. Exatamente 1 por unidade.
            $table->boolean('permite_venda')->default(false);
            // O que aparece pré-selecionado nos formulários.
            $table->boolean('is_padrao')->default(false);

            // Masculino: "o estoque". Ver armadilhas 5 e 42 antes de escrever o literal.
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');

            $table->text('observacoes')->nullable();

            $table->timestamps();

            $table->unique(['unidade_id', 'nome']);
            $table->index(['empresa_id', 'status']);
        });

        // Um "Principal" por unidade já existente — inclusive as soft-deletadas,
        // senão o histórico delas fica sem estoque_id e a migration seguinte falha.
        $unidades = DB::table('unidades')->select('id', 'empresa_id')->get();

        foreach ($unidades as $unidade) {
            DB::table('estoques')->insert([
                'empresa_id'    => $unidade->empresa_id,
                'unidade_id'    => $unidade->id,
                'nome'          => 'Principal',
                'codigo'        => 'PRINCIPAL',
                'permite_venda' => true,
                'is_padrao'     => true,
                'status'        => 'ativo',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estoques');
    }
};
