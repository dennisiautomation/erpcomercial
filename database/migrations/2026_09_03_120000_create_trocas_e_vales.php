<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trocas e vales (03/09/2026).
 *
 * As tabelas `devolucoes`/`devolucao_itens` existiam desde abril sem nenhum
 * código por cima. Aqui elas ganham o que a TROCA precisa (tipo, destino da
 * sobra, vale gerado, quem aprovou) e nascem `vales` + `vale_usos`: o crédito
 * na loja que o cliente leva quando devolve mais do que compra.
 *
 * Tudo nasce no estado de hoje: prazo 30 dias, sobra vira vale, vale vale 90
 * dias, fora da política pede o gerente. Nenhuma loja muda de comportamento
 * até alguém registrar a primeira troca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->unsignedSmallInteger('troca_prazo_dias')->default(30)->after('padrao_impressao');
            $table->enum('troca_sobra', ['vale', 'dinheiro'])->default('vale')->after('troca_prazo_dias');
            $table->unsignedSmallInteger('troca_vale_validade_dias')->default(90)->after('troca_sobra');
            $table->boolean('troca_senha_gerente')->default(true)->after('troca_vale_validade_dias');
        });

        Schema::create('vales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('devolucao_id')->nullable()->constrained('devolucoes')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('codigo', 20)->unique();
            $table->decimal('valor', 12, 2);
            $table->decimal('saldo', 12, 2);
            $table->date('validade')->nullable();
            $table->enum('status', ['ativo', 'utilizado', 'expirado', 'cancelado'])->default('ativo');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index(['cliente_id', 'status']);
        });

        Schema::create('vale_usos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vale_id')->constrained('vales')->cascadeOnDelete();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            // venda = abatido numa venda; dinheiro = sobra devolvida pelo caixa
            $table->enum('tipo', ['venda', 'dinheiro'])->default('venda');
            $table->decimal('valor', 12, 2);
            $table->timestamps();
        });

        Schema::table('devolucoes', function (Blueprint $table) {
            $table->enum('tipo', ['troca', 'devolucao'])->default('devolucao')->after('venda_id');
            $table->foreignId('venda_nova_id')->nullable()->after('tipo')->constrained('vendas')->nullOnDelete();
            $table->foreignId('vale_id')->nullable()->after('venda_nova_id')->constrained('vales')->nullOnDelete();
            $table->foreignId('caixa_id')->nullable()->after('vale_id')->constrained('caixas')->nullOnDelete();
            $table->enum('forma_sobra', ['vale', 'dinheiro', 'parcelas', 'nenhuma'])->nullable()->after('valor_estornado');
            $table->decimal('valor_sobra', 12, 2)->default(0)->after('forma_sobra');
            $table->decimal('valor_abatido_parcelas', 12, 2)->default(0)->after('valor_sobra');
            $table->boolean('fora_politica')->default(false)->after('valor_abatido_parcelas');
            $table->string('motivo_fora_politica')->nullable()->after('fora_politica');
            $table->foreignId('aprovado_por')->nullable()->after('motivo_fora_politica')->constrained('users')->nullOnDelete();
            $table->text('observacoes')->nullable()->after('status');
        });

        Schema::table('devolucao_itens', function (Blueprint $table) {
            $table->foreignId('estoque_id')->nullable()->after('produto_id')->constrained('estoques')->nullOnDelete();
            $table->boolean('retorna_estoque')->default(true)->after('estoque_id');
            $table->string('condicao', 30)->nullable()->after('retorna_estoque');
        });

        // Saída do caixa por devolução em dinheiro (sinal -1, entra no fechamento).
        // ENUM exige ALTER (armadilha 36).
        DB::statement("ALTER TABLE movimentacoes_caixa MODIFY COLUMN tipo ENUM('venda','sangria','suprimento','abertura','fechamento','devolucao') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE movimentacoes_caixa MODIFY COLUMN tipo ENUM('venda','sangria','suprimento','abertura','fechamento') NOT NULL");

        Schema::table('devolucao_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estoque_id');
            $table->dropColumn(['retorna_estoque', 'condicao']);
        });

        Schema::table('devolucoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venda_nova_id');
            $table->dropConstrainedForeignId('vale_id');
            $table->dropConstrainedForeignId('caixa_id');
            $table->dropConstrainedForeignId('aprovado_por');
            $table->dropColumn(['tipo', 'forma_sobra', 'valor_sobra', 'valor_abatido_parcelas', 'fora_politica', 'motivo_fora_politica', 'observacoes']);
        });

        Schema::dropIfExists('vale_usos');
        Schema::dropIfExists('vales');

        Schema::table('configuracoes_loja', function (Blueprint $table) {
            $table->dropColumn(['troca_prazo_dias', 'troca_sobra', 'troca_vale_validade_dias', 'troca_senha_gerente']);
        });
    }
};
