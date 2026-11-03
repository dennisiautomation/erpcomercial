<?php
// Validação ✅/❌ da importação STILO ← Gestão Click (03/11/2026): cada produto × preço × NCM × código de barras × estoque por loja.
use Illuminate\Support\Facades\DB;
use App\Models\Produto;
use App\Models\ConfiguracaoLoja;
use App\Services\SaldoEstoque;
use App\Services\TabelaPrecoService;

$E = 3;
$plano = json_decode(file_get_contents('/tmp/plano-importacao.json'), true);
$itens = array_merge($plano['atualizar'], $plano['novos']);
$lojas = [9, 10, 13, 14, 15, 16];
$fora = [17, 18];
$svc = app(TabelaPrecoService::class);
$cfg = [];
foreach (array_merge($lojas, $fora) as $u) $cfg[$u] = ConfiguracaoLoja::daUnidade($E, $u);
$f = ['produto achado pelo código de barras' => 0, 'preço PIX = GC' => 0, 'preço cartão = GC' => 0, 'NCM = GC' => 0,
      'estoque das 6 lojas = GC' => 0, 'Outlet/Barroso = 0' => 0];
$ex = [];
foreach ($itens as $i) {
    $p = Produto::withoutGlobalScopes()->with('precos')->where('empresa_id', $E)->whereNull('deleted_at')->where('codigo_barras', $i['codigo_barras'])->get();
    if ($p->count() !== 1) { $f['produto achado pelo código de barras']++; $ex[] = "cb {$i['codigo_barras']}"; continue; }
    $p = $p->first();
    foreach (array_merge($lojas, $fora) as $u) {
        $pr = $svc->precosDoProduto($p, $cfg[$u]);
        if (abs($pr['dinheiro_pix'] - $i['pix']) > 0.004) { $f['preço PIX = GC']++; $ex[] = "pix {$p->descricao} loja $u {$pr['dinheiro_pix']}≠{$i['pix']}"; }
        if (abs($pr['credito'] - $i['cartao']) > 0.004 || abs($pr['debito'] - $i['cartao']) > 0.004) { $f['preço cartão = GC']++; $ex[] = "cartão {$p->descricao} loja $u"; }
    }
    if ($i['ncm'] && $p->ncm !== $i['ncm']) $f['NCM = GC']++;
    foreach ($lojas as $u) {
        $esperado = (float) ($i['estoque'][(string) $u] ?? 0);
        if (abs(SaldoEstoque::naUnidade($u, $p->id) - $esperado) > 0.0001) { $f['estoque das 6 lojas = GC']++; $ex[] = "estoque {$p->descricao} loja $u"; }
    }
    foreach ($fora as $u) if (SaldoEstoque::naUnidade($u, $p->id) != 0.0) $f['Outlet/Barroso = 0']++;
}
$ok = 0; $ko = 0;
foreach ($f as $nome => $erros) { echo ($erros ? '❌' : '✅'), " $nome (" . count($itens) . " produtos, $erros erro(s))\n"; $erros ? $ko++ : $ok++; }
$t = function ($nome, $c) use (&$ok, &$ko) { echo ($c ? '✅' : '❌'), " $nome\n"; $c ? $ok++ : $ko++; };
$casados = collect($plano['atualizar'])->pluck('erp_id');
$soErp = DB::table('produtos')->where('empresa_id', $E)->whereNull('deleted_at')->whereNotIn('id', $casados)->whereNotIn('codigo_barras', collect($itens)->pluck('codigo_barras'))->pluck('id');
$t("produtos só do ERP ({$soErp->count()}) com estoque 0", DB::table('estoque_movimentacoes')->whereIn('produto_id', $soErp)->count() === 0);
$t('total de peças nas 6 lojas = 10.686', (int) DB::table('estoque_movimentacoes')->where('empresa_id', $E)->sum('quantidade') === 10686);
$t('produtos ativos da STILO = 516 + 132', DB::table('produtos')->where('empresa_id', $E)->whereNull('deleted_at')->count() === 648);
$t('nenhum código de barras repetido', DB::table('produtos')->where('empresa_id', $E)->whereNull('deleted_at')->where('codigo_barras', '<>', '')->select('codigo_barras')->groupBy('codigo_barras')->havingRaw('count(*)>1')->count() === 0);
$t('outras empresas sem movimentação nova', DB::table('estoque_movimentacoes')->where('empresa_id', '<>', $E)->where('origem_tipo', 'importacao_saldo_inicial')->where('created_at', '>=', now()->subHour())->count() === 0);
echo "TOTAL $ok ✅ / $ko ❌\n";
foreach (array_slice($ex, 0, 10) as $x) echo "  - $x\n";
