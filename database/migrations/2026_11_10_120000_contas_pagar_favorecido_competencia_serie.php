<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Contas a pagar para despesa de pessoal (10/11/2026):
 *  - favorecido pode ser funcionário (`funcionario_id`) ou um nome livre (`favorecido_nome`:
 *    INSS, FGTS, Receita, operadora do vale-transporte) — o fornecedor já era nullable no banco;
 *  - `competencia` (1º dia do mês a que a despesa pertence);
 *  - `serie_id` amarra as contas geradas juntas (recorrência/parcelas) para "ajustar esta e as próximas";
 *  - `recorrencia_tipo` passa a aceitar `bimestral` (a tela oferecia e o enum recusava → 500).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            if (! Schema::hasColumn('contas_pagar', 'funcionario_id')) {
                $table->foreignId('funcionario_id')->nullable()->after('fornecedor_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('contas_pagar', 'favorecido_nome')) {
                $table->string('favorecido_nome', 150)->nullable()->after('funcionario_id');
            }
            if (! Schema::hasColumn('contas_pagar', 'competencia')) {
                $table->date('competencia')->nullable()->after('vencimento');
            }
            if (! Schema::hasColumn('contas_pagar', 'serie_id')) {
                $table->uuid('serie_id')->nullable()->after('total_parcelas')->index();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contas_pagar MODIFY recorrencia_tipo "
                . "ENUM('mensal','bimestral','trimestral','semestral','anual') NULL");
        }

        // Contas antigas geradas juntas (mesmo fornecedor + descrição + total de parcelas) ganham
        // a mesma série, para o "ajustar as próximas" valer também para o que já estava lançado.
        $grupos = DB::table('contas_pagar')
            ->select('empresa_id', 'fornecedor_id', 'descricao', 'total_parcelas')
            ->whereNull('serie_id')
            ->whereNull('deleted_at')
            ->where('total_parcelas', '>', 1)
            ->groupBy('empresa_id', 'fornecedor_id', 'descricao', 'total_parcelas')
            ->get();

        foreach ($grupos as $g) {
            DB::table('contas_pagar')
                ->where('empresa_id', $g->empresa_id)
                ->where('descricao', $g->descricao)
                ->where('total_parcelas', $g->total_parcelas)
                ->when($g->fornecedor_id === null,
                    fn ($q) => $q->whereNull('fornecedor_id'),
                    fn ($q) => $q->where('fornecedor_id', $g->fornecedor_id))
                ->whereNull('serie_id')
                ->update(['serie_id' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            if (Schema::hasColumn('contas_pagar', 'funcionario_id')) {
                $table->dropConstrainedForeignId('funcionario_id');
            }
            foreach (['favorecido_nome', 'competencia', 'serie_id'] as $col) {
                if (Schema::hasColumn('contas_pagar', $col)) {
                    if ($col === 'serie_id') {
                        $table->dropIndex(['serie_id']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
        // O enum fica com `bimestral`: voltar quebraria linhas que já usam o valor.
    }
};
