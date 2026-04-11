<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->text('descricao')->nullable();
            $table->decimal('preco_mensal', 10, 2)->default(0);
            $table->decimal('preco_anual', 10, 2)->default(0);
            $table->integer('max_unidades')->default(1);
            $table->integer('max_usuarios')->default(3);
            $table->integer('max_produtos')->default(100);
            $table->integer('max_notas_mes')->default(50);
            $table->boolean('pdv_habilitado')->default(true);
            $table->boolean('fiscal_habilitado')->default(false);
            $table->boolean('multilojas_habilitado')->default(false);
            $table->boolean('os_habilitado')->default(false);
            $table->boolean('contratos_habilitado')->default(false);
            $table->boolean('conciliacao_habilitada')->default(false);
            $table->boolean('dre_habilitado')->default(false);
            $table->boolean('boletos_habilitado')->default(false);
            $table->boolean('api_habilitada')->default(false);
            $table->integer('dias_trial')->default(14);
            $table->boolean('ativo')->default(true);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos');
    }
};
