<?php

namespace App\Services\EntradaNFe;

use App\Models\ContaPagar;
use App\Models\NFeEntrada;
use App\Models\NFeEntradaDevolucao;
use App\Models\NFeEntradaDevolucaoItem;
use App\Models\NFeEntradaItem;
use App\Models\Produto;
use App\Services\SaldoEstoque;
use Illuminate\Support\Facades\DB;

/**
 * Devolução de compra ao fornecedor (24/11/2026, chamado da DK: "deu entrada
 * numa nota, entrou estoque e contas a pagar; como devolve, que o fornecedor
 * vai enviar outra?").
 *
 * Desfaz, no todo ou em parte, o que EntradaNFeService::concluir lançou:
 *  - estoque: SAÍDA (tipo `saida`, não `devolucao` — no enum `devolucao` soma)
 *    do mesmo estoque que recebeu, na conversão usada na entrada;
 *  - contas a pagar da nota (contas_pagar.nfe_entrada_id): o valor devolvido é
 *    tirado das parcelas EM ABERTO, da última para a primeira; parcela zerada
 *    vira `cancelada`; o que já estava pago fica como crédito com o fornecedor
 *    (só anotado — o caixa e o banco não são tocados).
 *
 * O ERP NÃO emite nota de devolução (decisão do Dennis, 24/11): se alguém emitir
 * fora, o número/chave fica anotado em `documento`. A nota nova do fornecedor
 * entra pelo caminho normal da entrada de NF-e.
 */
class DevolucaoCompraService
{
    /** Quanto já voltou de cada item (na unidade da nota): [item_id => qtd]. */
    public function jaDevolvido(NFeEntrada $entrada): array
    {
        return NFeEntradaDevolucaoItem::query()
            ->join('nfe_entrada_devolucoes as d', 'd.id', '=', 'nfe_entrada_devolucao_itens.devolucao_id')
            ->where('d.nfe_entrada_id', $entrada->id)
            ->groupBy('nfe_entrada_item_id')
            ->selectRaw('nfe_entrada_item_id, SUM(quantidade_nota) as qtd, SUM(valor) as valor')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->nfe_entrada_item_id => ['qtd' => (float) $r->qtd, 'valor' => (float) $r->valor]])
            ->all();
    }

    /** Parcelas da nota que a devolução pode abater (pendente/vencida). */
    public function contasEmAberto(NFeEntrada $entrada)
    {
        return ContaPagar::withoutGlobalScopes()
            ->where('empresa_id', $entrada->empresa_id)
            ->where('nfe_entrada_id', $entrada->id)
            ->whereIn('status', ['pendente', 'vencida'])
            ->orderByDesc('vencimento')
            ->orderByDesc('parcela')
            ->get();
    }

    /**
     * @param  array<int,float|string>  $quantidades  [nfe_entrada_item_id => quantidade na unidade da nota]
     *
     * @throws \InvalidArgumentException com mensagem para o lojista
     */
    public function devolver(NFeEntrada $entrada, array $quantidades, string $motivo, ?string $documento, int $userId): NFeEntradaDevolucao
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 3) {
            throw new \InvalidArgumentException('Escreva o motivo da devolução (ex.: "mercadoria com defeito, fornecedor manda outra").');
        }

        return DB::transaction(function () use ($entrada, $quantidades, $motivo, $documento, $userId) {
            // Trava a entrada: duas abas não devolvem o mesmo item duas vezes
            $entrada = NFeEntrada::withoutGlobalScopes()->lockForUpdate()->findOrFail($entrada->id);
            if (! $entrada->concluida()) {
                throw new \InvalidArgumentException('Só dá para devolver uma entrada já lançada no estoque.');
            }

            $itens = $entrada->itens()->get()->keyBy('id');
            $ja = $this->jaDevolvido($entrada);

            $linhas = [];
            foreach ($quantidades as $itemId => $qtd) {
                $qtd = round(self::numero($qtd), 4);
                if ($qtd <= 0) {
                    continue;
                }
                /** @var NFeEntradaItem|null $item */
                $item = $itens->get((int) $itemId);
                if (! $item) {
                    throw new \InvalidArgumentException('Item não pertence a esta nota. Recarregue a tela.');
                }
                $qtdNota = (float) $item->quantidade;
                $jaQtd = $ja[$item->id]['qtd'] ?? 0.0;
                $resta = round($qtdNota - $jaQtd, 4);
                if ($qtd > $resta + 0.00005) {
                    throw new \InvalidArgumentException(
                        "Item {$item->numero_item} ({$item->descricao}): só resta devolver " . self::fmt($resta, 3) . " {$item->unidade_comercial}."
                    );
                }
                // Valor proporcional ao custo do item (frete/IPI/ST rateados, desconto abatido);
                // quem fecha o item leva o centavo que sobrou do arredondamento.
                $custoItem = (float) $item->custo_total;
                $valor = abs($qtd - $resta) < 0.00005
                    ? round($custoItem - ($ja[$item->id]['valor'] ?? 0), 2)
                    : round($qtdNota > 0 ? $custoItem * $qtd / $qtdNota : 0, 2);
                $linhas[] = compact('item', 'qtd', 'valor');
            }
            if ($linhas === []) {
                throw new \InvalidArgumentException('Informe a quantidade de pelo menos um item para devolver.');
            }

            $numero = (int) NFeEntradaDevolucao::withoutGlobalScopes()->where('nfe_entrada_id', $entrada->id)->max('numero') + 1;
            $devolucao = NFeEntradaDevolucao::create([
                'empresa_id'     => $entrada->empresa_id,
                'unidade_id'     => $entrada->unidade_id,
                'nfe_entrada_id' => $entrada->id,
                'numero'         => $numero,
                'motivo'         => mb_substr($motivo, 0, 500),
                'documento'      => ($documento = trim((string) $documento)) !== '' ? mb_substr($documento, 0, 60) : null,
                'valor_total'    => 0,
                'user_id'        => $userId,
            ]);

            $total = 0.0;
            foreach ($linhas as ['item' => $item, 'qtd' => $qtd, 'valor' => $valor]) {
                $qtdEstoque = 0.0;
                $movId = null;
                // Só sai do estoque o que entrou (item "não lançado" volta só no valor)
                if ($item->acao !== 'ignorar' && $item->produto_id && $item->estoque_movimentacao_id) {
                    $qtdEstoque = round($qtd * (float) $item->fator_conversao, 3);
                    Produto::withoutGlobalScopes()->whereKey($item->produto_id)->lockForUpdate()->first();
                    $saldo = SaldoEstoque::noEstoque((int) $entrada->estoque_id, (int) $item->produto_id);
                    if ($saldo + 0.0005 < $qtdEstoque) {
                        throw new \InvalidArgumentException(
                            "Item {$item->numero_item} ({$item->descricao}): o estoque tem " . self::fmt($saldo, 3)
                            . ' e a devolução tira ' . self::fmt($qtdEstoque, 3) . '. Parte já saiu (venda/transferência): devolva só o que está aqui.'
                        );
                    }
                    $mov = SaldoEstoque::registrar(
                        (int) $entrada->empresa_id, (int) $entrada->unidade_id, (int) $entrada->estoque_id,
                        (int) $item->produto_id, 'saida', -$qtdEstoque, [
                            'custo_unitario' => $item->custoUnitarioEntrada(),
                            'origem_tipo'    => NFeEntradaDevolucao::class,
                            'origem_id'      => $devolucao->id,
                            'user_id'        => $userId,
                            'observacoes'    => mb_substr("Devolução ao fornecedor — NF-e {$entrada->numero} — {$entrada->nome_emitente}", 0, 500),
                        ]
                    );
                    $movId = $mov->id;
                }

                $devolucao->itens()->create([
                    'nfe_entrada_item_id'     => $item->id,
                    'produto_id'              => $item->produto_id,
                    'quantidade_nota'         => $qtd,
                    'quantidade_estoque'      => $qtdEstoque,
                    'valor'                   => $valor,
                    'estoque_movimentacao_id' => $movId,
                ]);
                $total += $valor;
            }

            // Situação: tudo devolvido? Então o total fecha com o valor da nota.
            $jaDepois = $this->jaDevolvido($entrada);
            $tudo = $itens->every(fn ($i) => ($jaDepois[$i->id]['qtd'] ?? 0) + 0.00005 >= (float) $i->quantidade);
            if ($tudo) {
                $antes = (float) NFeEntradaDevolucao::withoutGlobalScopes()
                    ->where('nfe_entrada_id', $entrada->id)->where('id', '<>', $devolucao->id)->sum('valor_total');
                $total = max(0, (float) $entrada->valor_total - $antes);
            }
            $total = round($total, 2);

            [$abatido, $ajustes] = $this->abaterContas($entrada, $devolucao, $total);
            $temContas = ContaPagar::withoutGlobalScopes()->where('nfe_entrada_id', $entrada->id)->exists();

            $devolucao->update([
                'valor_total'        => $total,
                'valor_abatido'      => $abatido,
                'credito_fornecedor' => $temContas ? round($total - $abatido, 2) : 0,
                'contas_ajustadas'   => $ajustes ?: null,
            ]);
            $entrada->update(['devolucao_situacao' => $tudo ? 'total' : 'parcial']);

            return $devolucao->fresh('itens');
        });
    }

    /** Tira $valor das parcelas em aberto, da última para a primeira. → [abatido, ajustes] */
    private function abaterContas(NFeEntrada $entrada, NFeEntradaDevolucao $devolucao, float $valor): array
    {
        $restante = $valor;
        $ajustes = [];
        $contas = ContaPagar::withoutGlobalScopes()
            ->where('empresa_id', $entrada->empresa_id)
            ->where('nfe_entrada_id', $entrada->id)
            ->whereIn('status', ['pendente', 'vencida'])
            ->orderByDesc('vencimento')->orderByDesc('parcela')
            ->lockForUpdate()->get();

        foreach ($contas as $conta) {
            if ($restante < 0.005) {
                break;
            }
            $pago = round((float) $conta->valor_pago, 2);
            $aberto = round((float) $conta->valor - $pago, 2);
            if ($aberto < 0.005) {
                continue;
            }
            $abate = round(min($restante, $aberto), 2);
            $antes = ['valor' => (float) $conta->valor, 'status' => $conta->status];

            $nota = "Devolução ao fornecedor nº {$devolucao->numero}: −R$ " . self::fmt($abate);
            $dados = ['observacoes' => trim(($conta->observacoes ? $conta->observacoes . "\n" : '') . $nota)];
            if ($abate >= $aberto - 0.005) {
                // Nada mais a pagar nesta parcela
                $dados += $pago > 0 ? ['valor' => $pago, 'status' => 'paga'] : ['status' => 'cancelada'];
            } else {
                $dados['valor'] = round((float) $conta->valor - $abate, 2);
            }
            $conta->update($dados);

            $ajustes[] = [
                'id'            => $conta->id,
                'parcela'       => $conta->parcela,
                'abatido'       => $abate,
                'valor_antes'   => $antes['valor'],
                'valor_depois'  => (float) $conta->valor,
                'status_antes'  => $antes['status'],
                'status_depois' => $conta->status,
            ];
            $restante = round($restante - $abate, 2);
        }

        return [round($valor - $restante, 2), $ajustes];
    }

    private static function numero(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = preg_replace('/[^\d,.\-]/', '', (string) $v);
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }

    private static function fmt(float $v, int $casas = 2): string
    {
        return number_format($v, $casas, ',', '.');
    }
}
