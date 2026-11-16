<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loja online — 5ª rodada (16/11/2026): rastreio completo e domínio próprio simples.
 *
 * - loja_sessoes: UMA linha por visitante por dia (a sessão do navegador), com de
 *   onde veio, o aparelho e até onde chegou no funil. Não guarda IP nem nome: é
 *   só para somar. Dá origem das visitas, funil por etapa, celular × computador
 *   e "quantos estão na loja agora".
 * - loja_buscas: o que as pessoas procuram, por dia, e quantas vezes não achou nada.
 * - loja_produto_vistas.carrinhos: quantas vezes o produto foi posto no carrinho
 *   (para achar os mais abandonados).
 * - lojas_online.dominio_*: situação do domínio próprio conferida pelo lojista.
 *
 * Tudo aditivo: o código antigo não lê nada daqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loja_sessoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->date('dia');
            $table->string('origem', 20)->default('direto');
            $table->boolean('pago')->default(false);
            $table->string('campanha', 80)->nullable();
            $table->string('aparelho', 12)->default('computador');
            $table->boolean('viu_produto')->default(false);
            $table->boolean('carrinho')->default(false);
            $table->boolean('fechamento')->default(false);
            $table->unsignedBigInteger('pedido_id')->nullable();
            $table->timestamp('ultima_em')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['empresa_id', 'dia']);
            $table->index(['empresa_id', 'ultima_em']);
            $table->index('pedido_id');
        });

        Schema::create('loja_buscas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->date('dia');
            $table->string('termo', 60);
            $table->unsignedInteger('vezes')->default(0);
            $table->unsignedInteger('sem_resultado')->default(0);
            $table->unique(['empresa_id', 'dia', 'termo']);
        });

        Schema::table('loja_produto_vistas', function (Blueprint $table) {
            $table->unsignedInteger('carrinhos')->default(0)->after('vistas');
        });

        Schema::table('lojas_online', function (Blueprint $table) {
            $table->string('dominio_status', 16)->nullable()->after('dominio'); // aguardando | dns_ok | ativo
            $table->string('dominio_obs', 255)->nullable()->after('dominio_status');
            $table->timestamp('dominio_conferido_em')->nullable()->after('dominio_obs');
        });
    }

    public function down(): void
    {
        Schema::table('lojas_online', function (Blueprint $table) {
            $table->dropColumn(['dominio_status', 'dominio_obs', 'dominio_conferido_em']);
        });
        Schema::table('loja_produto_vistas', function (Blueprint $table) {
            $table->dropColumn('carrinhos');
        });
        Schema::dropIfExists('loja_buscas');
        Schema::dropIfExists('loja_sessoes');
    }
};
