<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            if (! Schema::hasColumn('contas_receber', 'plano_conta_id')) {
                $table->foreignId('plano_conta_id')->nullable()->after('forma_pagamento')->constrained('plano_contas')->nullOnDelete();
            }
            if (! Schema::hasColumn('contas_receber', 'centro_custo_id')) {
                $table->foreignId('centro_custo_id')->nullable()->after('observacoes')->constrained('centros_custo')->nullOnDelete();
            }
        });

        Schema::table('contas_pagar', function (Blueprint $table) {
            if (! Schema::hasColumn('contas_pagar', 'plano_conta_id')) {
                $table->foreignId('plano_conta_id')->nullable()->after('forma_pagamento')->constrained('plano_contas')->nullOnDelete();
            }
            if (! Schema::hasColumn('contas_pagar', 'centro_custo_id')) {
                $table->foreignId('centro_custo_id')->nullable()->after('observacoes')->constrained('centros_custo')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        foreach (['contas_receber', 'contas_pagar'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'centro_custo_id')) {
                    $table->dropConstrainedForeignId('centro_custo_id');
                }
                if (Schema::hasColumn($t, 'plano_conta_id')) {
                    $table->dropConstrainedForeignId('plano_conta_id');
                }
            });
        }
    }
};
