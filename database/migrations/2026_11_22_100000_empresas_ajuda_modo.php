<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assistente de ajuda por empresa (22/11/2026): null = segue a chave geral
 * (Admin → Integrações); 'ligado' / 'desligado' = exceção desta empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'ajuda_modo')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->string('ajuda_modo', 10)->nullable()->after('open_finance_ocultar');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresas', 'ajuda_modo')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->dropColumn('ajuda_modo');
            });
        }
    }
};
