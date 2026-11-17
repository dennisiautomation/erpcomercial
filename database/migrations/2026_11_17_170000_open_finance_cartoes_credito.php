<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Open Finance, 3ª entrega (17/11/2026): cartões de crédito da conta conectada. As compras
 * entram uma a uma como despesa; o pagamento da fatura, na conta, não vira despesa de novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartoes_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conta_bancaria_id')->constrained('contas_bancarias')->cascadeOnDelete();
            $table->string('final', 4);
            $table->string('adicionais', 120)->nullable();      // finais dos cartões adicionais, separados por vírgula
            $table->string('apelido', 80)->nullable();
            $table->boolean('importar')->default(false);        // a empresa escolhe de quais cartões quer as compras
            $table->decimal('limite', 14, 2)->nullable();
            $table->decimal('limite_disponivel', 14, 2)->nullable();
            $table->string('protocolo_pendente', 60)->nullable();
            $table->date('protocolo_inicio')->nullable();
            $table->date('protocolo_fim')->nullable();
            $table->timestamp('ultima_sincronizacao_em')->nullable();
            $table->timestamp('proxima_sincronizacao_em')->nullable();
            $table->string('ultimo_erro', 500)->nullable();
            $table->timestamps();

            $table->unique(['conta_bancaria_id', 'final']);
        });

        Schema::table('lancamentos_bancarios', function (Blueprint $table) {
            // Compra (ou crédito) que veio do extrato de um cartão
            $table->foreignId('cartao_credito_id')->nullable()->after('conta_bancaria_id')->constrained('cartoes_credito')->nullOnDelete();
            $table->unsignedSmallInteger('parcela_numero')->nullable()->after('linha_digitavel');
            $table->unsignedSmallInteger('parcela_total')->nullable()->after('parcela_numero');
            // Saída da CONTA que é o pagamento da fatura deste cartão: sugerida / confirmada
            $table->foreignId('sugestao_fatura_cartao_id')->nullable()->after('sugestao_conta_pagar_id')->constrained('cartoes_credito')->nullOnDelete();
            $table->foreignId('fatura_cartao_id')->nullable()->after('conta_pagar_id')->constrained('cartoes_credito')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos_bancarios', function (Blueprint $table) {
            foreach (['cartao_credito_id', 'sugestao_fatura_cartao_id', 'fatura_cartao_id'] as $c) {
                $table->dropConstrainedForeignId($c);
            }
            $table->dropColumn(['parcela_numero', 'parcela_total']);
        });
        Schema::dropIfExists('cartoes_credito');
    }
};
