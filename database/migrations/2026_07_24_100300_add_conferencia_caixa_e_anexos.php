<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            // Conferência por forma de pagamento no fechamento:
            // { forma: { esperado, contado, diferenca } }
            $table->json('conferencia')->nullable()->after('valor_esperado');
        });

        Schema::create('caixa_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('caixa_id')->constrained('caixas')->cascadeOnDelete();
            $table->enum('tipo', ['maquina', 'credito', 'debito']);
            $table->string('arquivo');
            $table->string('nome_original');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_anexos');
        Schema::table('caixas', function (Blueprint $table) {
            $table->dropColumn('conferencia');
        });
    }
};
