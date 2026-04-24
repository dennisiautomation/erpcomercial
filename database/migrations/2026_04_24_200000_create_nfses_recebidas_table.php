<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NFS-es em que a empresa é tomadora (serviços contratados).
 *
 * Espelho do nfes_recebidas mas para serviços: a Focus expõe o endpoint
 * /v2/nfses_tomadas (ou /v2/nfses_recebidas em algumas variações) para listar
 * todas as NFS-es emitidas CONTRA o CNPJ da empresa. É útil para
 * conferência fiscal, crédito de ISS retido e pagamento de prestadores.
 *
 * A chave aqui é `codigo_verificacao` + `cnpj_prestador` (NFS-e não tem
 * chave de 44 dígitos como a NF-e; cada prefeitura/portal nacional tem
 * código próprio). Usamos um composto para evitar duplicatas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfses_recebidas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');

            // Identificação da NFS-e
            $table->string('codigo_verificacao', 60)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('serie', 10)->nullable();

            // Prestador
            $table->string('cnpj_prestador', 14);
            $table->string('nome_prestador', 255);
            $table->string('municipio_prestador', 100)->nullable();

            // Serviço
            $table->text('discriminacao')->nullable();
            $table->string('item_lista_servico', 10)->nullable();
            $table->string('codigo_servico', 30)->nullable();
            $table->string('padrao', 20)->default('municipal'); // municipal | nacional

            // Valores
            $table->decimal('valor_servicos', 12, 2)->default(0);
            $table->decimal('valor_iss', 12, 2)->default(0);
            $table->decimal('aliquota_iss', 5, 2)->nullable();
            $table->boolean('iss_retido')->default(false);

            // Datas
            $table->date('data_emissao')->nullable();
            $table->date('data_competencia')->nullable();

            // Status + links
            $table->string('status', 30)->default('autorizada');
            $table->text('xml_url')->nullable();
            $table->text('pdf_url')->nullable();

            $table->timestamp('sincronizada_em')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Uma NFS-e (cod. verificação + prestador) é única globalmente
            $table->unique(['cnpj_prestador', 'codigo_verificacao'], 'nfses_rec_prestador_codigo_unique');
            $table->index(['empresa_id', 'data_emissao']);
            $table->index(['unidade_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfses_recebidas');
    }
};
