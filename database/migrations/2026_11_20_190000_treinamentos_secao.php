<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Treinamento separado por seção (20/11/2026): os vídeos do sistema continuam em 'erp' e o curso
 * da loja online entra em 'loja_online', na mesma tela, em bloco próprio. Aditivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('treinamentos', 'secao')) {
            Schema::table('treinamentos', function (Blueprint $table) {
                $table->string('secao', 30)->default('erp')->after('titulo')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('treinamentos', 'secao')) {
            Schema::table('treinamentos', function (Blueprint $table) {
                $table->dropColumn('secao');
            });
        }
    }
};
