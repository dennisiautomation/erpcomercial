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
        Schema::create('extratos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conciliacao_id')->constrained('conciliacoes_bancarias')->cascadeOnDelete();
            $table->date('data');
            $table->string('descricao');
            $table->decimal('valor', 12, 2);
            $table->enum('tipo', ['credito', 'debito']);
            $table->string('documento')->nullable();
            $table->foreignId('conta_receber_id')->nullable()->constrained('contas_receber')->nullOnDelete();
            $table->foreignId('conta_pagar_id')->nullable()->constrained('contas_pagar')->nullOnDelete();
            $table->boolean('conciliado')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extratos_bancarios');
    }
};
