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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
            $table->string('cpf', 14)->nullable();
            $table->string('telefone', 20)->nullable();

            $table->enum('perfil', [
                'admin', 'dono', 'gerente', 'vendedor', 'caixa', 'financeiro', 'consulta',
            ])->default('vendedor');

            $table->decimal('comissao_percentual', 5, 2)->nullable();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->boolean('is_admin')->default(false);

            $table->softDeletes();

            $table->index(['empresa_id', 'perfil']);
            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropIndex(['empresa_id', 'perfil']);
            $table->dropIndex(['empresa_id', 'status']);
            $table->dropColumn([
                'empresa_id', 'cpf', 'telefone', 'perfil',
                'comissao_percentual', 'status', 'is_admin', 'deleted_at',
            ]);
        });
    }
};
