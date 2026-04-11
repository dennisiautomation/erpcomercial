<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');

            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('vendedor_id')->nullable()->constrained('users');
            $table->foreignId('tecnico_id')->nullable()->constrained('users');

            $table->unsignedInteger('numero'); // per empresa
            $table->string('equipamento')->nullable();
            $table->text('defeito_relatado');
            $table->text('laudo_tecnico')->nullable();

            $table->enum('status', ['aberta', 'em_andamento', 'aguardando_peca', 'concluida', 'entregue', 'cancelada']);

            $table->decimal('valor_produtos', 12, 2)->default(0);
            $table->decimal('valor_servicos', 12, 2)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordens_servico');
    }
};
