<?php
/**
 * Reconstrução do saldo de estoque da MISS MERLINDA (empresa 4) — 04/09/2026.
 *
 * A regra é a que o Dennis pediu: percorrer TODO o histórico de cada
 * produto/loja e chegar na quantidade real.
 *
 *   - `ajuste` com valor INTEIRO  → digitação de gente (o inventário que a
 *                                   equipe está fazendo na tela). Vale.
 *   - `ajuste` em milésimos       → ruído da roda do mouse (armadilha 66).
 *                                   Ignorado: o saldo limpo não muda.
 *   - `saida` (venda)             → desconta sempre.
 *   - `entrada`                   → soma sempre (a empresa não tem nenhuma,
 *                                   mas o código não depende disso).
 *
 * Não apaga nada: cada correção entra como uma movimentação de `ajuste` nova,
 * com a observação abaixo, e o histórico anterior fica intacto.
 *
 * Use DRY_RUN=1 para só listar o que faria.
 *
 *   docker exec -e DRY_RUN=1 erp-com-app php /tmp/reconstruir.php   # simula
 *   docker exec erp-com-app php /tmp/reconstruir.php                # aplica
 */

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Produto;
use App\Services\SaldoEstoque;
use Illuminate\Support\Facades\DB;

const EMPRESA = 4;
const OBS = 'Reconstrucao do saldo pelo historico (ajustes + vendas) 04/09/2026';

// Rodando por linha de comando não há usuário logado, e `user_id` é NOT NULL:
// o rastro fica no admin da plataforma (IA365), que foi quem fez a correção.
const USUARIO = 1;

$simular = (bool) getenv('DRY_RUN');

$movs = DB::table('estoque_movimentacoes')
    ->where('empresa_id', EMPRESA)
    ->orderBy('estoque_id')->orderBy('produto_id')->orderBy('id')
    ->get(['unidade_id', 'estoque_id', 'produto_id', 'tipo', 'quantidade', 'quantidade_posterior']);

/** Reconstrói o saldo de cada par (estoque, produto). */
$alvo = [];
foreach ($movs as $m) {
    $chave = $m->estoque_id . ':' . $m->produto_id;

    if (! isset($alvo[$chave])) {
        $alvo[$chave] = ['unidade' => $m->unidade_id, 'estoque' => $m->estoque_id,
                         'produto' => $m->produto_id, 'saldo' => 0.0];
    }

    $posterior = (float) $m->quantidade_posterior;

    if ($m->tipo === 'ajuste') {
        // Só a digitação humana (valor inteiro) conta. Milésimo é a roda do mouse.
        if (abs($posterior - round($posterior)) < 0.0001) {
            $alvo[$chave]['saldo'] = round($posterior);
        }
    } elseif ($m->tipo === 'saida') {
        $alvo[$chave]['saldo'] -= (float) $m->quantidade;
    } elseif ($m->tipo === 'entrada') {
        $alvo[$chave]['saldo'] += (float) $m->quantidade;
    }
}

$aplicar = [];
foreach ($alvo as $linha) {
    $atual = SaldoEstoque::noEstoque($linha['estoque'], $linha['produto']);

    if (abs($linha['saldo'] - $atual) < 0.0001) {
        continue;
    }

    $linha['atual'] = $atual;
    $aplicar[] = $linha;
}

printf("linhas a corrigir: %d%s", count($aplicar), PHP_EOL);

$paraZero = 0; $paraPositivo = 0; $paraNegativo = 0;
foreach ($aplicar as $l) {
    if ($l['saldo'] > 0)      { $paraPositivo++; }
    elseif ($l['saldo'] == 0) { $paraZero++; }
    else                      { $paraNegativo++; }
}
printf("  para saldo positivo: %d | para zero: %d | negativos (vendido sem estoque): %d%s",
    $paraPositivo, $paraZero, $paraNegativo, PHP_EOL);

if ($simular) {
    echo PHP_EOL . "SIMULACAO — nada foi gravado. As linhas que ganham saldo:" . PHP_EOL;
    foreach ($aplicar as $l) {
        if ($l['saldo'] <= 0) continue;
        $p = Produto::withoutGlobalScopes()->find($l['produto']);
        printf("  loja %d · %s %s · %s -> %s%s", $l['unidade'], $p->codigo_interno ?? '?',
            mb_substr($p->descricao ?? '?', 0, 28), $l['atual'], $l['saldo'], PHP_EOL);
    }
    return;
}

$feitos = 0; $semProduto = 0;

DB::transaction(function () use ($aplicar, &$feitos, &$semProduto) {
    foreach ($aplicar as $l) {
        // Relê o saldo dentro da transação: se alguém mexeu no meio, respeita.
        $anterior = SaldoEstoque::noEstoque($l['estoque'], $l['produto']);
        if (abs($anterior - $l['atual']) > 0.0001) {
            continue;
        }

        $produto = Produto::withoutGlobalScopes()->find($l['produto']);
        if (! $produto) { $semProduto++; continue; }

        SaldoEstoque::registrar(
            EMPRESA, $l['unidade'], $l['estoque'], $l['produto'], 'ajuste',
            $l['saldo'] - $anterior,
            [
                'custo_unitario' => $produto->preco_custo ?? 0,
                'observacoes'    => OBS,
                'user_id'        => USUARIO,
            ]
        );

        $feitos++;
    }
});

printf("APLICADO: %d movimentacoes de ajuste gravadas | sem produto: %d%s", $feitos, $semProduto, PHP_EOL);
