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
        Schema::create('conciliacoes_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->string('banco');
            $table->string('agencia')->nullable();
            $table->string('conta')->nullable();
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->decimal('saldo_inicial', 12, 2)->default(0);
            $table->decimal('saldo_final', 12, 2)->default(0);
            $table->integer('total_lancamentos')->default(0);
            $table->integer('conciliados')->default(0);
            $table->enum('status', ['pendente', 'em_andamento', 'concluida'])->default('pendente');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conciliacoes_bancarias');
    }
};
