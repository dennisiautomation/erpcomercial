<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Open Finance (17/11/2026) — extrato do banco direto no ERP (Tecnospeed / PlugBank).
 *
 * Liberação: o plano diz se tem e quantas contas vêm incluídas; a empresa pode ter contas
 * extras (venda avulsa) e pode ficar com a função escondida. Tudo nasce desligado: nenhuma
 * empresa muda no deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->boolean('open_finance_habilitado')->default(false)->after('api_habilitada');
            $table->unsignedInteger('open_finance_contas')->default(0)->after('open_finance_habilitado');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedInteger('open_finance_contas_extras')->default(0);
            $table->boolean('open_finance_ocultar')->default(false);
        });

        Schema::create('contas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->string('apelido', 80);
            $table->string('banco_codigo', 3);
            $table->string('banco_nome', 80)->nullable();
            $table->string('agencia', 10);
            $table->string('agencia_digito', 2)->nullable();
            $table->string('conta', 12);
            $table->string('conta_digito', 2)->nullable();
            $table->string('titular_documento', 14);   // CPF ou CNPJ de quem é dono da conta, só dígitos
            $table->string('titular_nome', 120)->nullable();
            // Tecnospeed
            $table->string('account_hash', 40)->nullable()->unique();
            $table->string('link_conexao', 500)->nullable();
            $table->string('status_banco', 40)->nullable();      // statusOpenfinance devolvido pela API
            $table->enum('situacao', ['aguardando', 'conectada', 'desconectada', 'erro'])->default('aguardando');
            $table->timestamp('conectada_em')->nullable();
            $table->timestamp('desconectada_em')->nullable();
            // Sincronização
            $table->string('protocolo_pendente', 60)->nullable();
            $table->date('protocolo_inicio')->nullable();
            $table->date('protocolo_fim')->nullable();
            $table->timestamp('ultima_sincronizacao_em')->nullable();
            $table->timestamp('proxima_sincronizacao_em')->nullable();
            $table->string('ultimo_erro', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'situacao']);
        });

        Schema::create('lancamentos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conta_bancaria_id')->constrained('contas_bancarias')->cascadeOnDelete();
            $table->string('transaction_id', 100);
            $table->date('data');
            $table->enum('tipo', ['credito', 'debito']);
            $table->decimal('valor', 14, 2);                       // sempre positivo
            $table->string('descricao', 255)->nullable();
            $table->string('operacao', 30)->nullable();            // PIX, TED, BOLETO, TARIFA...
            $table->string('contraparte_nome', 160)->nullable();
            $table->string('contraparte_documento', 14)->nullable();
            $table->string('linha_digitavel', 60)->nullable();
            $table->json('bruto')->nullable();
            $table->enum('status', ['pendente', 'sugerido', 'conciliado', 'ignorado', 'removido'])->default('pendente');
            // Sugestão do sistema (a pessoa só confirma)
            $table->foreignId('sugestao_conta_receber_id')->nullable()->constrained('contas_receber')->nullOnDelete();
            $table->foreignId('sugestao_conta_pagar_id')->nullable()->constrained('contas_pagar')->nullOnDelete();
            $table->unsignedTinyInteger('sugestao_confianca')->nullable();   // 0–100
            $table->string('sugestao_motivo', 255)->nullable();
            // Conciliado
            $table->foreignId('conta_receber_id')->nullable()->constrained('contas_receber')->nullOnDelete();
            $table->foreignId('conta_pagar_id')->nullable()->constrained('contas_pagar')->nullOnDelete();
            $table->timestamp('conciliado_em')->nullable();
            $table->foreignId('conciliado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sumiu_no_banco')->default(false);     // conciliado que o banco deixou de mandar: revisar
            $table->timestamps();

            $table->unique(['conta_bancaria_id', 'transaction_id']);
            $table->index(['empresa_id', 'status', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos_bancarios');
        Schema::dropIfExists('contas_bancarias');
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['open_finance_contas_extras', 'open_finance_ocultar']);
        });
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn(['open_finance_habilitado', 'open_finance_contas']);
        });
    }
};
