<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo'); // alerta_estoque, conta_vencida, trial_expirando, etc
            $table->string('titulo');
            $table->text('mensagem')->nullable();
            $table->string('url')->nullable(); // link para a ação
            $table->string('icone')->default('bell'); // bootstrap icon name
            $table->string('cor')->default('primary'); // primary, warning, danger, success
            $table->boolean('lida')->default(false);
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'lida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
