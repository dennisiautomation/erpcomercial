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
        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');

            $table->enum('tipo', ['nfe', 'nfce', 'nfse']);
            $table->integer('numero')->nullable();
            $table->string('serie')->nullable();
            $table->string('chave_acesso')->nullable()->unique();
            $table->string('natureza_operacao')->nullable();

            $table->foreignId('venda_id')->nullable()->constrained('vendas');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');

            $table->decimal('valor_total', 12, 2);

            $table->enum('status', ['pendente', 'autorizada', 'cancelada', 'rejeitada', 'inutilizada', 'contingencia']);

            // Focus NFe integration
            $table->string('focus_ref')->nullable();
            $table->string('focus_status')->nullable();
            $table->text('focus_mensagem')->nullable();

            // URLs
            $table->text('xml_url')->nullable();
            $table->text('danfe_url')->nullable();
            $table->text('pdf_url')->nullable();

            // Cancelamento
            $table->text('cancelamento_motivo')->nullable();
            $table->string('cancelamento_protocolo')->nullable();

            $table->enum('ambiente', ['homologacao', 'producao']);

            $table->timestamp('emitida_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'tipo', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notas_fiscais');
    }
};
