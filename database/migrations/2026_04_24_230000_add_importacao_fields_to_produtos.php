<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Declaração de Importação (DI) nos produtos.
 *
 * Quando a NF-e tem um item importado (origem 1, 2, 3, 6, 7, 8), a SEFAZ
 * exige os dados da DI no XML (grupo importacao). Preenchemos por produto
 * porque normalmente a DI é associada ao lote de importação do item.
 *
 * Campos padrão NT 2015/003:
 *   - di_numero, di_data, di_local_desembaraco, di_uf_desembaraco
 *   - di_data_desembaraco, di_via_transp, di_valor_afrmm
 *   - di_forma_importacao_intermediacao
 *   - adicao_numero, adicao_sequencial
 *
 * Campos são opcionais — só preenchidos para itens importados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('di_numero', 20)->nullable()->after('origem');
            $table->date('di_data')->nullable()->after('di_numero');
            $table->string('di_local_desembaraco', 100)->nullable()->after('di_data');
            $table->string('di_uf_desembaraco', 2)->nullable()->after('di_local_desembaraco');
            $table->date('di_data_desembaraco')->nullable()->after('di_uf_desembaraco');
            $table->unsignedTinyInteger('di_via_transp')->nullable()->after('di_data_desembaraco');
            $table->decimal('di_valor_afrmm', 12, 2)->nullable()->after('di_via_transp');
            $table->unsignedTinyInteger('di_forma_importacao')->nullable()->after('di_valor_afrmm');
            $table->string('di_adicao_numero', 10)->nullable()->after('di_forma_importacao');

            $table->index('di_numero');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropIndex(['di_numero']);
            $table->dropColumn([
                'di_numero',
                'di_data',
                'di_local_desembaraco',
                'di_uf_desembaraco',
                'di_data_desembaraco',
                'di_via_transp',
                'di_valor_afrmm',
                'di_forma_importacao',
                'di_adicao_numero',
            ]);
        });
    }
};
