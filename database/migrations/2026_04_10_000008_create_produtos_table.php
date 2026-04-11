<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('codigo_interno');
            $table->string('codigo_barras', 20)->nullable();
            $table->string('sku', 50)->nullable();
            $table->string('descricao');
            $table->text('descricao_detalhada')->nullable();

            $table->enum('unidade_medida', ['UN', 'KG', 'CX', 'PCT', 'LT', 'MT', 'M2', 'M3', 'PAR', 'JG']);

            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('ncm', 10)->nullable();
            $table->string('cest', 9)->nullable();
            $table->tinyInteger('origem')->default(0);

            // Preços
            $table->decimal('preco_custo', 12, 2)->nullable();
            $table->decimal('markup', 8, 2)->nullable();
            $table->decimal('preco_venda', 12, 2);

            $table->integer('estoque_minimo')->nullable();
            $table->string('foto')->nullable();

            // Peso
            $table->decimal('peso_bruto', 8, 3)->nullable();
            $table->decimal('peso_liquido', 8, 3)->nullable();

            // Fiscal
            $table->string('cfop', 10)->nullable();
            $table->string('cst_csosn', 10)->nullable();
            $table->decimal('icms_aliquota', 5, 2)->nullable();
            $table->decimal('pis_aliquota', 5, 2)->nullable();
            $table->decimal('cofins_aliquota', 5, 2)->nullable();
            $table->decimal('ipi_aliquota', 5, 2)->nullable();

            $table->enum('status', ['ativo', 'inativo']);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'codigo_barras']);
            $table->index(['empresa_id', 'sku']);
            $table->index(['empresa_id', 'categoria_id']);
            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
