<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impressão do orçamento, por loja (22/10/2026).
 *
 * Até aqui o "Imprimir" do orçamento mandava a TELA para a impressora (menu, botões,
 * cards). A folha nova tem cabeçalho com logo e o lojista escolhe qual endereço sai:
 * o da loja (cadastro fiscal) ou um endereço comercial digitado aqui. Tudo nasce
 * vazio e em 'loja' — quem não configurar nada recebe o endereço da própria loja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->string('orcamento_endereco', 20)->default('loja')->after('os_mostrar_valores');
            $table->text('orcamento_endereco_comercial')->nullable()->after('orcamento_endereco');
            $table->string('orcamento_telefone', 30)->nullable()->after('orcamento_endereco_comercial');
            $table->string('orcamento_whatsapp', 30)->nullable()->after('orcamento_telefone');
            $table->string('orcamento_email', 150)->nullable()->after('orcamento_whatsapp');
            $table->string('orcamento_site', 150)->nullable()->after('orcamento_email');
            $table->text('orcamento_condicoes')->nullable()->after('orcamento_site');
            $table->text('orcamento_rodape')->nullable()->after('orcamento_condicoes');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn([
                'orcamento_endereco',
                'orcamento_endereco_comercial',
                'orcamento_telefone',
                'orcamento_whatsapp',
                'orcamento_email',
                'orcamento_site',
                'orcamento_condicoes',
                'orcamento_rodape',
            ]);
        });
    }
};
