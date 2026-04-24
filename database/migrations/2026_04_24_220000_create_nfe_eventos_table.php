<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eventos avançados de NF-e além de CC-e (que já tem tabela própria).
 *
 * Cobre:
 *   - 110150 — Ator Interessado (marketplace, transportadora, seguradora…)
 *   - 110192 — Insucesso de Entrega (NT 2021.002)
 *   - 110140 — EPEC (Evento Prévio de Emissão em Contingência) — futuro
 *   - outros eventos NT 2023.001 — futuro
 *
 * Cada NFe pode ter múltiplos eventos do mesmo tipo. A `sequencia` controla
 * ordem para eventos que aceitam correções incrementais (Ator Interessado,
 * por exemplo, aceita substituições).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_eventos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->cascadeOnDelete();

            // Qual evento — usamos nomes pt-BR em vez dos códigos SEFAZ
            $table->string('tipo', 40);            // 'ator_interessado' | 'insucesso_entrega' | 'epec' | …
            $table->unsignedSmallInteger('sequencia')->default(1);

            // Conteúdo do evento (variável por tipo → JSON)
            // Ator: {cnpj_ator, razao_social_ator, tipo_ator, autorizado}
            // Insucesso: {data_tentativa, motivo, localizacao:{latitude,longitude}, hash_tentativa}
            $table->json('dados');

            // Status do envio à SEFAZ
            $table->string('status', 30)->default('pendente');    // pendente | autorizado | rejeitado | cancelado
            $table->string('focus_ref', 100)->nullable();
            $table->string('protocolo', 50)->nullable();
            $table->text('mensagem_retorno')->nullable();

            // Artefatos
            $table->text('xml_url')->nullable();

            // Usuário que disparou o evento
            $table->foreignId('criado_por')->nullable()->constrained('users');

            $table->timestamps();

            $table->index(['nota_fiscal_id', 'tipo']);
            $table->index(['empresa_id', 'tipo', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_eventos');
    }
};
