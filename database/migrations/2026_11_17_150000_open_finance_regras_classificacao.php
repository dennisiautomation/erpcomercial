<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Open Finance, 2ª entrega (17/11/2026): lançamento do banco sem conta cadastrada vira despesa
 * já classificada, e o sistema lembra a classificação de cada favorecido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regras_conciliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->enum('chave_tipo', ['documento', 'descricao']);   // CPF/CNPJ de quem recebeu, ou o texto do banco
            $table->string('chave', 120);
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->string('favorecido_nome', 160)->nullable();
            $table->string('descricao_padrao', 255)->nullable();
            $table->foreignId('plano_conta_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $table->unsignedInteger('usos')->default(1);
            $table->timestamp('ultima_vez_em')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'chave_tipo', 'chave']);
        });

        Schema::table('lancamentos_bancarios', function (Blueprint $table) {
            // A conta foi criada a partir deste lançamento: desfazer apaga a conta em vez de reabrir.
            $table->boolean('titulo_criado')->default(false)->after('sumiu_no_banco');
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos_bancarios', function (Blueprint $table) {
            $table->dropColumn('titulo_criado');
        });
        Schema::dropIfExists('regras_conciliacao');
    }
};
