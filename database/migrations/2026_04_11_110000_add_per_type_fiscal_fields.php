<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->boolean('emite_nfe')->default(false)->after('tipo_cupom_pdv');
            $table->boolean('emite_nfce')->default(false)->after('emite_nfe');
            $table->boolean('emite_nfse')->default(false)->after('emite_nfce');

            $table->string('serie_nfse')->nullable()->after('emite_nfse');
            $table->string('nfse_item_lista_servico', 10)->nullable()->after('serie_nfse');
            $table->string('nfse_codigo_tributacao', 20)->nullable()->after('nfse_item_lista_servico');
            $table->string('nfse_regime_especial', 50)->nullable()->after('nfse_codigo_tributacao');
            $table->boolean('nfse_incentivador_cultural')->default(false)->after('nfse_regime_especial');
        });

        \DB::table('configuracoes_fiscais')->where('emissao_fiscal_ativa', true)->update([
            'emite_nfe' => true,
            'emite_nfce' => \DB::raw("tipo_cupom_pdv = 'fiscal'"),
        ]);
    }

    public function down(): void
    {
        Schema::table('configuracoes_fiscais', function (Blueprint $table) {
            $table->dropColumn([
                'emite_nfe', 'emite_nfce', 'emite_nfse',
                'serie_nfse', 'nfse_item_lista_servico', 'nfse_codigo_tributacao',
                'nfse_regime_especial', 'nfse_incentivador_cultural',
            ]);
        });
    }
};
