<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| OS → venda (21/11/2026): a OS guarda a venda que gerou, para não ser convertida
| duas vezes (antes nada impedia: venda, estoque e caixa em dobro). Só aditivo.
| As OS já convertidas são marcadas pela observação que a conversão sempre grava
| na venda: "Gerada a partir da OS #<numero>".
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ordens_servico', 'venda_id')) {
            Schema::table('ordens_servico', function (Blueprint $table) {
                $table->unsignedBigInteger('venda_id')->nullable()->after('status')->index();
            });
        }

        DB::table('vendas')
            ->where('observacoes', 'like', 'Gerada a partir da OS #%')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->select(['id', 'empresa_id', 'observacoes'])
            ->chunk(500, function ($vendas) {
                foreach ($vendas as $v) {
                    if (! preg_match('/^Gerada a partir da OS #(\d+)/', (string) $v->observacoes, $m)) {
                        continue;
                    }
                    DB::table('ordens_servico')
                        ->where('empresa_id', $v->empresa_id)
                        ->where('numero', $m[1])
                        ->whereNull('venda_id')
                        ->update(['venda_id' => $v->id]); // a 1ª venda (menor id) fica
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ordens_servico', 'venda_id')) {
            Schema::table('ordens_servico', function (Blueprint $table) {
                $table->dropIndex(['venda_id']);
                $table->dropColumn('venda_id');
            });
        }
    }
};
