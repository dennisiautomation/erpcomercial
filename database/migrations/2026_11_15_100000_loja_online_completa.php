<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loja online — 4ª rodada (15/11/2026): o que faltava para vender de verdade.
 *
 * - produtos.grade_id / grade_opcoes: GRADE (tamanho, cor). Cada variação é um
 *   PRODUTO comum do ERP (código, estoque e preço próprios); a grade só diz que
 *   eles são "o mesmo produto" na vitrine. grade_id = id do produto principal
 *   (o principal aponta para si mesmo).
 * - pedidos.loja_*: conta do cliente, envio com rastreio e devolução do dinheiro.
 * - loja_contas: login do comprador (por empresa).
 * - loja_avise_me, loja_avaliacoes, loja_carrinhos, loja_visitas,
 *   loja_produto_vistas: avise-me, avaliações, carrinho abandonado e os números
 *   do painel de resultado.
 *
 * Tudo aditivo e nullable/default: o código antigo não lê nada daqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->unsignedBigInteger('grade_id')->nullable()->after('loja_online_destaque');
            $table->json('grade_opcoes')->nullable()->after('grade_id');
            $table->index(['empresa_id', 'grade_id']);
        });

        Schema::create('loja_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('nome', 120);
            $table->string('email', 190);
            $table->string('telefone', 20)->nullable();
            $table->string('senha', 255);
            $table->json('favoritos')->nullable();
            $table->string('redefinir_token', 64)->nullable();
            $table->timestamp('redefinir_expira')->nullable();
            $table->timestamp('ultimo_acesso')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'email']);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->unsignedBigInteger('loja_conta_id')->nullable()->after('loja_cupom');
            $table->string('loja_transportadora', 60)->nullable()->after('loja_conta_id');
            $table->string('loja_rastreio_codigo', 60)->nullable()->after('loja_transportadora');
            $table->string('loja_rastreio_url', 255)->nullable()->after('loja_rastreio_codigo');
            $table->timestamp('loja_enviado_em')->nullable()->after('loja_rastreio_url');
            $table->timestamp('loja_estornado_em')->nullable()->after('loja_enviado_em');
            $table->string('loja_estorno_obs', 255)->nullable()->after('loja_estornado_em');
            $table->index('loja_conta_id');
        });

        Schema::create('loja_avise_me', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('email', 190);
            $table->timestamp('avisado_em')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'produto_id', 'email']);
        });

        Schema::create('loja_avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->unsignedBigInteger('pedido_id')->nullable();
            $table->string('nome', 80);
            $table->unsignedTinyInteger('nota');
            $table->text('texto')->nullable();
            $table->string('status', 12)->default('pendente'); // pendente | aprovada | oculta
            $table->text('resposta')->nullable();
            $table->timestamps();
            $table->unique(['pedido_id', 'produto_id']);
            $table->index(['empresa_id', 'produto_id', 'status']);
        });

        Schema::create('loja_carrinhos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->string('nome', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->json('itens');
            $table->decimal('total', 12, 2)->default(0);
            $table->unsignedBigInteger('pedido_id')->nullable();
            $table->timestamp('lembrete_em')->nullable();
            $table->timestamp('recuperado_em')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'updated_at']);
        });

        Schema::create('loja_visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->date('dia');
            $table->unsignedInteger('visitas')->default(0);
            $table->unsignedInteger('carrinhos')->default(0);
            $table->unsignedInteger('fechamentos')->default(0);
            $table->unique(['empresa_id', 'dia']);
        });

        Schema::create('loja_produto_vistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->date('dia');
            $table->unsignedInteger('vistas')->default(0);
            $table->unique(['empresa_id', 'produto_id', 'dia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loja_produto_vistas');
        Schema::dropIfExists('loja_visitas');
        Schema::dropIfExists('loja_carrinhos');
        Schema::dropIfExists('loja_avaliacoes');
        Schema::dropIfExists('loja_avise_me');

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['loja_conta_id']);
            $table->dropColumn(['loja_conta_id', 'loja_transportadora', 'loja_rastreio_codigo', 'loja_rastreio_url', 'loja_enviado_em', 'loja_estornado_em', 'loja_estorno_obs']);
        });

        Schema::dropIfExists('loja_contas');

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'grade_id']);
            $table->dropColumn(['grade_id', 'grade_opcoes']);
        });
    }
};
