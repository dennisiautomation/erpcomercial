<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Agente de ajuda (21/11/2026): conversas do botão "Ajuda" e o chamado que
| cada uma abriu no app.ia365. Só aditivo.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ajuda_conversas')) {
            Schema::create('ajuda_conversas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('titulo', 160)->nullable();
                $table->string('chamado_protocolo', 80)->nullable();
                $table->timestamp('chamado_aberto_em')->nullable();
                $table->unsignedSmallInteger('avaliacao')->nullable(); // 1 = ajudou, 0 = não ajudou
                $table->timestamps();
                $table->index(['empresa_id', 'user_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('ajuda_mensagens')) {
            Schema::create('ajuda_mensagens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversa_id')->constrained('ajuda_conversas')->cascadeOnDelete();
                $table->unsignedBigInteger('empresa_id')->nullable()->index();
                $table->string('papel', 12); // user | assistant | tool
                $table->mediumText('conteudo')->nullable();
                $table->json('ferramentas')->nullable(); // chamadas de ferramenta da IA (assistant) ou nome da ferramenta (tool)
                $table->string('tool_call_id', 80)->nullable();
                $table->string('pagina', 255)->nullable();
                $table->unsignedInteger('tokens')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ajuda_mensagens');
        Schema::dropIfExists('ajuda_conversas');
    }
};
