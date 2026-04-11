<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('plano_id')->nullable()->after('plano')->constrained('planos')->nullOnDelete();
            $table->date('trial_inicio')->nullable()->after('plano_id');
            $table->date('trial_fim')->nullable()->after('trial_inicio');
            $table->date('assinatura_inicio')->nullable()->after('trial_fim');
            $table->date('assinatura_fim')->nullable()->after('assinatura_inicio');
            $table->enum('tipo_cobranca', ['mensal', 'anual'])->default('mensal')->after('assinatura_fim');
            $table->boolean('em_trial')->default(false)->after('tipo_cobranca');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['plano_id']);
            $table->dropColumn([
                'plano_id',
                'trial_inicio',
                'trial_fim',
                'assinatura_inicio',
                'assinatura_fim',
                'tipo_cobranca',
                'em_trial',
            ]);
        });
    }
};
