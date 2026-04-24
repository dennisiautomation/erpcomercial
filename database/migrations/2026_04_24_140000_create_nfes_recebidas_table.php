<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfes_recebidas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');

            $table->string('chave_acesso', 44);
            $table->string('cnpj_emitente', 14);
            $table->string('nome_emitente', 255);
            $table->string('numero', 20)->nullable();
            $table->string('serie', 10)->nullable();

            $table->decimal('valor_total', 12, 2);
            $table->date('data_emissao')->nullable();

            // Última manifestação do destinatário (pode mudar ao longo do tempo)
            $table->string('tipo_ultima_manifestacao', 20)->nullable();
            $table->string('protocolo_manifestacao', 50)->nullable();
            $table->timestamp('manifestada_em')->nullable();
            $table->foreignId('manifestada_por')->nullable()->constrained('users');

            // Conteúdo baixado
            $table->text('xml_url')->nullable();
            $table->text('danfe_url')->nullable();

            // Metadados da sincronização
            $table->timestamp('sincronizada_em')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Uma mesma NF-e pode aparecer para mais de uma unidade? Não —
            // a chave de acesso é o documento único. Unique global evita
            // duplicatas mesmo se o job rodar concorrente.
            $table->unique('chave_acesso');
            $table->index(['empresa_id', 'tipo_ultima_manifestacao']);
            $table->index(['unidade_id', 'data_emissao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfes_recebidas');
    }
};
