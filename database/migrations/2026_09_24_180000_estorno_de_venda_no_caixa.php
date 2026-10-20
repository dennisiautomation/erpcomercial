<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelar venda passa a estornar o caixa (24/09/2026).
 *
 * Até aqui o cancelamento devolvia estoque e cancelava contas a receber, mas
 * a entrada da venda ficava no caixa — o "esperado" (dinheiro, PIX, cartão)
 * seguia somando venda cancelada (caso MISS MERLINDA, Matriz, caixa 210).
 *
 * - movimentacoes_caixa.venda_id: a ligação era só o texto "Venda #N" da
 *   descrição (e o número não é único na empresa). Backfill pelo par
 *   caixa_id + descrição, que em 24/09 casava 100% das entradas de venda.
 * - tipo 'estorno': saída com a mesma forma e valor da entrada estornada.
 * - vale_usos.tipo 'estorno': saldo do vale devolvido no cancelamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes_caixa', function (Blueprint $table) {
            $table->foreignId('venda_id')->nullable()->after('caixa_id')
                ->constrained('vendas')->nullOnDelete();
        });

        DB::statement("ALTER TABLE movimentacoes_caixa MODIFY COLUMN tipo ENUM('venda','sangria','suprimento','abertura','fechamento','devolucao','estorno') NOT NULL");
        DB::statement("ALTER TABLE vale_usos MODIFY COLUMN tipo ENUM('venda','dinheiro','estorno') NOT NULL DEFAULT 'venda'");

        DB::statement("
            UPDATE movimentacoes_caixa m
            JOIN vendas v ON v.caixa_id = m.caixa_id
                         AND m.descricao = CONCAT('Venda #', v.numero)
            SET m.venda_id = v.id
            WHERE m.tipo = 'venda' AND m.venda_id IS NULL
        ");
    }

    public function down(): void
    {
        // Apagar estorno já lançado reinflaria o caixa em silêncio: o rollback
        // só roda enquanto nenhum cancelamento passou pelo código novo.
        if (DB::table('movimentacoes_caixa')->where('tipo', 'estorno')->exists()
            || DB::table('vale_usos')->where('tipo', 'estorno')->exists()) {
            throw new RuntimeException('Há estornos lançados — rollback exige decisão manual.');
        }

        DB::statement("ALTER TABLE vale_usos MODIFY COLUMN tipo ENUM('venda','dinheiro') NOT NULL DEFAULT 'venda'");
        DB::statement("ALTER TABLE movimentacoes_caixa MODIFY COLUMN tipo ENUM('venda','sangria','suprimento','abertura','fechamento','devolucao') NOT NULL");

        Schema::table('movimentacoes_caixa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venda_id');
        });
    }
};
