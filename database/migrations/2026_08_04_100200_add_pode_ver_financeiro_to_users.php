<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admins da plataforma IA365 podem (ou não) ver valores financeiros —
 * faturas, preços, receita. Flag irrelevante para usuários de empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('pode_ver_financeiro')->default(false)->after('is_admin');
        });

        // Admins existentes continuam vendo tudo (não mudar comportamento no deploy)
        DB::table('users')->where('is_admin', true)->update(['pode_ver_financeiro' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pode_ver_financeiro');
        });
    }
};
