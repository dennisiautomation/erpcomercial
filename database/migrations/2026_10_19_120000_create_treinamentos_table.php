<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Treinamento (19/10/2026): vídeos de como usar o ERP, cadastrados pelo
     * admin IA365 e iguais para todas as empresas — por isso sem empresa_id,
     * como `planos`. O arquivo fica no disco local (storage/app/private/treinamentos).
     * Os vídeos só aparecem na aba Treinamento, filtrados pelo perfil do usuário.
     *
     * `treinamento_user` registra quem acompanhou: uma linha por usuário e
     * vídeo, com a data em que chegou ao fim ou clicou em "Marcar como assistido".
     */
    public function up(): void
    {
        Schema::create('treinamentos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('arquivo')->nullable();
            $table->unsignedBigInteger('tamanho')->default(0);
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            // Perfis que veem o vídeo (financeiro, vendedor, caixa, consulta).
            // Dono e gerente veem todos, por isso nunca entram aqui.
            $table->json('perfis')->nullable();
            $table->timestamps();
        });

        Schema::create('treinamento_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treinamento_id')->constrained('treinamentos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('concluido_em');
            $table->unique(['treinamento_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treinamento_user');
        Schema::dropIfExists('treinamentos');
    }
};
