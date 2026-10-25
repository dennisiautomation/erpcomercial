<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preço cadastrado = preço do cartão + formas de pagamento no orçamento (25/10/2026, DK).
 *
 * configuracoes_loja:
 *  - preco_base: 'avista' (como sempre: o preço do produto é o de Dinheiro/PIX e o
 *    cartão leva acréscimo) ou 'cartao' (o preço do produto é o do cartão, sem juros
 *    até as parcelas da loja, e Dinheiro/PIX ganha desconto_avista_percentual).
 *  - desconto_avista_percentual: o desconto de Dinheiro/PIX na regra 'cartao'; também
 *    é o desconto à vista que o orçamento novo já traz preenchido.
 *  - orcamento_parcelas: parcelas que o orçamento novo já traz (NULL = max_parcelas).
 *
 * orcamentos:
 *  - frete_valor: taxa de entrega, soma no total.
 *  - parcelas / desconto_avista_percentual: o bloco "Formas de pagamento" da folha.
 *    parcelas NULL = orçamento antigo, a folha sai como antes (sem o bloco).
 *
 * Loja que não mexer em nada continua exatamente igual: defaults 'avista' e 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->string('preco_base', 10)->default('avista')->after('regra_preco_split');
            $table->decimal('desconto_avista_percentual', 5, 2)->default(0)->after('preco_base');
            $table->unsignedTinyInteger('orcamento_parcelas')->nullable()->after('orcamento_condicoes');
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            $table->decimal('frete_valor', 10, 2)->default(0)->after('desconto_valor');
            $table->unsignedTinyInteger('parcelas')->nullable()->after('frete_valor');
            $table->decimal('desconto_avista_percentual', 5, 2)->nullable()->after('parcelas');
        });
    }

    public function down(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            $table->dropColumn(['frete_valor', 'parcelas', 'desconto_avista_percentual']);
        });

        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn(['preco_base', 'desconto_avista_percentual', 'orcamento_parcelas']);
        });
    }
};
