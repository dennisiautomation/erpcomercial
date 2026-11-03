<?php
// Importação STILO VINTE (empresa 3) ← Gestão Click, 03/11/2026, pedido do Dennis (confirmado com o cliente).
// Cadastro é da EMPRESA (preço PIX = preco_venda, cartão = débito/crédito fixos, NCM, custo, código de barras GC);
// estoque é POR LOJA (estoque de venda da loja, origem importacao_saldo_inicial — mesmo caminho do import de saldo).
// DRY=1 → faz tudo dentro da transação e desfaz no fim.
use Illuminate\Support\Facades\DB;
use App\Models\Produto;
use App\Services\SaldoEstoque;
use App\Services\FiscalAutoConfig;

$E = 3;
$DRY = getenv('DRY') !== '0';
$plano = json_decode(file_get_contents('/tmp/plano-importacao.json'), true);
$cont = ['atualizados' => 0, 'novos' => 0, 'movs' => 0, 'pecas' => 0, 'ncm_mantido' => 0];
$porLoja = [];

$precos = function (int $pid, float $cartao) use ($E) {
    foreach (['debito', 'credito'] as $mod) {
        $q = DB::table('produto_precos')->where('produto_id', $pid)->where('modalidade', $mod);
        $q->exists()
            ? $q->update(['valor' => $cartao, 'updated_at' => now()])
            : DB::table('produto_precos')->insert(['empresa_id' => $E, 'produto_id' => $pid, 'modalidade' => $mod,
                'valor' => $cartao, 'created_at' => now(), 'updated_at' => now()]);
    }
    // formato clássico: o cadastrado é o PIX — sem preço PIX próprio
    DB::table('produto_precos')->where('produto_id', $pid)->where('modalidade', 'dinheiro_pix')->delete();
};

try {
    DB::transaction(function () use ($E, $DRY, $plano, &$cont, &$porLoja, $precos) {
        if (DB::table('estoque_movimentacoes')->where('empresa_id', $E)->exists()) {
            throw new RuntimeException('STILO já tem movimentação de estoque — parar e conferir antes de importar.');
        }
        $empresa = App\Models\Empresa::find($E);
        $regime = $empresa->regime_tributario instanceof App\Enums\RegimeTributario ? $empresa->regime_tributario->value : $empresa->regime_tributario;
        $fiscal = FiscalAutoConfig::defaults($regime);
        $donoId = DB::table('users')->where('empresa_id', $E)->where('perfil', 'dono')->where('status', 'ativo')->orderBy('id')->value('id');
        if (! $donoId) throw new RuntimeException('STILO sem dono ativo');
        $ids = [];

        foreach ($plano['atualizar'] as $i) {
            $p = Produto::withoutGlobalScopes()->where('empresa_id', $E)->whereNull('deleted_at')->findOrFail($i['erp_id']);
            $dados = ['descricao' => $i['nome'], 'codigo_barras' => $i['codigo_barras'], 'preco_venda' => $i['pix'], 'preco_custo' => $i['custo']];
            if ($i['ncm']) { $dados['ncm'] = $i['ncm']; } else { $cont['ncm_mantido']++; }
            $p->update($dados);
            $precos($p->id, $i['cartao']);
            $ids[$i['gc_id']] = $p->id;
            $cont['atualizados']++;
        }

        $ultimo = (int) Produto::withoutGlobalScopes()->where('empresa_id', $E)->max('codigo_interno');
        foreach ($plano['novos'] as $i) {
            $codigo = str_pad((string) ++$ultimo, 6, '0', STR_PAD_LEFT);
            $p = Produto::withoutGlobalScopes()->create([
                'empresa_id' => $E, 'codigo_interno' => $codigo, 'sku' => 'SKU-' . $codigo, 'codigo_barras' => $i['codigo_barras'],
                'descricao' => $i['nome'], 'preco_venda' => $i['pix'], 'preco_custo' => $i['custo'], 'markup' => 0,
                'ncm' => $i['ncm'], 'unidade_medida' => 'UN', 'status' => 'ativo',
                'cst_csosn' => $fiscal['cst_csosn'], 'cfop' => $fiscal['cfop_venda_interna'], 'origem' => $fiscal['origem'],
                'icms_aliquota' => $fiscal['icms_aliquota'], 'pis_aliquota' => $fiscal['pis_aliquota'], 'cofins_aliquota' => $fiscal['cofins_aliquota'],
            ]);
            $precos($p->id, $i['cartao']);
            $ids[$i['gc_id']] = $p->id;
            $cont['novos']++;
        }

        foreach (array_merge($plano['atualizar'], $plano['novos']) as $i) {
            foreach ($i['estoque'] as $unidade => $qtd) {
                $estoqueId = SaldoEstoque::estoqueDeVendaId((int) $unidade);
                if (! $estoqueId) throw new RuntimeException("loja {$unidade} sem estoque de venda");
                SaldoEstoque::registrar($E, (int) $unidade, $estoqueId, $ids[$i['gc_id']], 'entrada', (float) $qtd, [
                    'user_id' => $donoId, 'custo_unitario' => $i['custo'], 'origem_tipo' => 'importacao_saldo_inicial',
                    'observacoes' => 'Saldo inicial importado do Gestão Click (03/11/2026)',
                ]);
                $cont['movs']++; $cont['pecas'] += $qtd; $porLoja[$unidade] = ($porLoja[$unidade] ?? 0) + $qtd;
            }
        }

        $dupCb = DB::table('produtos')->where('empresa_id', $E)->whereNull('deleted_at')->whereNotNull('codigo_barras')->where('codigo_barras', '<>', '')
            ->select('codigo_barras')->groupBy('codigo_barras')->havingRaw('count(*) > 1')->count();
        $dupCi = DB::table('produtos')->where('empresa_id', $E)->select('codigo_interno')->groupBy('codigo_interno')->havingRaw('count(*) > 1')->count();
        echo "##conferencia cod_barras_duplicado={$dupCb} cod_interno_duplicado={$dupCi}\n";
        if ($dupCb || $dupCi) throw new RuntimeException('código duplicado');
        echo '##contagem ', json_encode($cont), ' por_loja ', json_encode($porLoja), "\n";
        if ($DRY) throw new RuntimeException('DRY-RUN (desfeito de propósito)');
    });
    echo "##COMMIT\n";
} catch (Throwable $e) {
    echo '##ROLLBACK ', $e->getMessage(), "\n";
}
