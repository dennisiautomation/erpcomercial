<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chave por usuário: vendedor/caixa que cadastra e edita produtos sem virar
 * gerente (04/11/2026, pedido da DONA DOURO para a Mirella e o Manoel).
 * Nasce desligada; dono/gerente ligam no cadastro do funcionário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('pode_editar_produtos')->default(false)->after('pode_ver_financeiro');
        });

        // DONA DOURO (empresa 5): Mirella Oliveira e Manoel Junior — pedido do Dennis 04/11.
        DB::table('users')
            ->where('empresa_id', 5)
            ->whereIn('email', ['myyhsiilvaa10@gmail.com', 'alencarjunior842@gmail.com'])
            ->update(['pode_editar_produtos' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pode_editar_produtos');
        });
    }
};
