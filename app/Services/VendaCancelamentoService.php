<?php

namespace App\Services;

use App\Enums\StatusCaixa;
use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoCaixa;
use App\Enums\TipoMovimentacaoEstoque;
use App\Models\Caixa;
use App\Models\Devolucao;
use App\Models\EstoqueMovimentacao;
use App\Models\MovimentacaoCaixa;
use App\Models\Vale;
use App\Models\ValeUso;
use App\Models\Venda;
use App\Scopes\UnidadeScope;
use Illuminate\Support\Facades\DB;

/**
 * Cancelamento de venda (extraído do VendaController em 03/11/2026 para o
 * cancelamento de TROCA reaproveitar: trocar com peça nova gera uma venda
 * nova, e cancelar a troca precisa cancelar essa venda também).
 *
 * Devolve estoque, estorna o caixa, devolve o saldo do vale e cancela as
 * contas a receber. Documento fiscal vivo e troca registrada bloqueiam.
 */
class VendaCancelamentoService
{
    /**
     * @param bool $liberaTroca true só quando quem chama é o cancelamento da
     *                          própria troca (ele já tratou estoque/vale dela).
     * @throws VendaNaoCancelavelException
     */
    public function cancelar(Venda $venda, int $userId, bool $liberaTroca = false): void
    {
        if ($venda->status === StatusVenda::Cancelada) {
            throw new VendaNaoCancelavelException('Esta venda ja esta cancelada.');
        }

        $this->checarNotaFiscal($venda);

        // Troca/devolução já mexeu em estoque, vale e caixa desta venda (ou a
        // venda é a nova de uma troca): cancelar por cima devolveria em dobro.
        if (! $liberaTroca) {
            $temTroca = Devolucao::withoutGlobalScope(UnidadeScope::class)
                ->where(fn ($q) => $q->where('venda_id', $venda->id)->orWhere('venda_nova_id', $venda->id))
                ->where('status', '!=', 'cancelada')
                ->exists();
            if ($temTroca) {
                throw new VendaNaoCancelavelException('Esta venda tem troca/devolução registrada — não pode ser cancelada por inteiro. Use a troca (F6 no PDV ou "Trocar / Devolver").');
            }
        }

        // O que a venda lançou no caixa volta pelo estorno. Caixa da venda
        // ainda aberto: sai dele. Já fechado: o dinheiro sai da gaveta de
        // hoje, então vai para o caixa aberto de quem cancela, na mesma loja.
        $entradas = MovimentacaoCaixa::withoutGlobalScope(UnidadeScope::class)
            ->where('venda_id', $venda->id)
            ->where('tipo', TipoMovimentacaoCaixa::Venda)
            ->get();

        $caixaEstorno = null;
        if ($entradas->isNotEmpty()) {
            $caixaVenda = Caixa::withoutGlobalScope(UnidadeScope::class)->find($venda->caixa_id);

            $caixaEstorno = ($caixaVenda && $caixaVenda->status === StatusCaixa::Aberto)
                ? $caixaVenda
                : Caixa::withoutGlobalScope(UnidadeScope::class)
                    ->where('unidade_id', $venda->unidade_id)
                    ->where('user_id', $userId)
                    ->where('status', StatusCaixa::Aberto)
                    ->first();

            if (! $caixaEstorno) {
                throw new VendaNaoCancelavelException('O caixa desta venda já foi fechado. Abra o seu caixa nesta loja para cancelar: o valor da venda sai dele.');
            }
        }

        DB::transaction(function () use ($venda, $entradas, $caixaEstorno, $userId) {
            // Dois cliques / duas abas: só o primeiro cancela e estorna.
            $travada = Venda::withoutGlobalScope(UnidadeScope::class)->lockForUpdate()->find($venda->id);
            if ($travada->status === StatusVenda::Cancelada) {
                return;
            }

            foreach ($venda->itens as $item) {
                if ($item->produto_id) {
                    // Volta para o estoque de onde a peça SAIU nesta venda (o PDV pode ter
                    // baixado de outro que não o de venda — 22/10/2026); sem registro, o de
                    // venda da MESMA loja da venda — não a da sessão, senão a peça reaparece
                    // na loja errada.
                    $estoqueDeOrigem = EstoqueMovimentacao::withoutGlobalScopes()
                        ->where('origem_tipo', Venda::class)
                        ->where('origem_id', $venda->id)
                        ->where('produto_id', $item->produto_id)
                        ->where('tipo', TipoMovimentacaoEstoque::Saida->value)
                        ->where('unidade_id', $venda->unidade_id)
                        ->latest('id')
                        ->value('estoque_id');

                    SaldoEstoque::registrar(
                        $venda->empresa_id,
                        $venda->unidade_id,
                        $estoqueDeOrigem ?: SaldoEstoque::estoqueDeVendaId($venda->unidade_id),
                        (int) $item->produto_id,
                        TipoMovimentacaoEstoque::Devolucao->value,
                        (float) $item->quantidade,
                        [
                            'custo_unitario' => $item->preco_unitario,
                            'origem_tipo'    => Venda::class,
                            'origem_id'      => $venda->id,
                            'observacoes'    => "Cancelamento Venda #{$venda->numero}",
                        ]
                    );
                }
            }

            // Estorna o caixa: uma saída por entrada, mesma forma e valor.
            foreach ($entradas as $entrada) {
                MovimentacaoCaixa::create([
                    'empresa_id'      => $venda->empresa_id,
                    'unidade_id'      => $caixaEstorno->unidade_id,
                    'caixa_id'        => $caixaEstorno->id,
                    'venda_id'        => $venda->id,
                    'tipo'            => TipoMovimentacaoCaixa::Estorno,
                    'valor'           => $entrada->valor,
                    'forma_pagamento' => $entrada->forma_pagamento,
                    'descricao'       => "Cancelamento Venda #{$venda->numero}",
                    'user_id'         => $userId,
                ]);
            }

            // Vale abatido na venda volta ao saldo do cliente.
            $usosVale = ValeUso::where('venda_id', $venda->id)->where('tipo', 'venda')->get();
            foreach ($usosVale as $uso) {
                $vale = Vale::lockForUpdate()->find($uso->vale_id);
                $vale?->estornar((float) $uso->valor, $venda->id, $userId);
            }

            $venda->contasReceber()->update(['status' => 'cancelada']);
            $venda->update(['status' => StatusVenda::Cancelada]);
        });
    }

    /**
     * Cancelar a venda NÃO cancela o documento na SEFAZ — com cupom autorizado
     * vivo, o cancelamento precisa começar pela nota (prazo curto na NFC-e).
     */
    public function checarNotaFiscal(Venda $venda): void
    {
        $notaViva = $venda->notasFiscais()
            ->whereIn('status', ['autorizada', 'pendente', 'contingencia'])
            ->latest()
            ->first();

        if ($notaViva) {
            throw new VendaNaoCancelavelException(
                'Esta venda tem um documento fiscal ' . $notaViva->status->label() .
                '. Cancele o cupom/nota aqui primeiro (botão Cancelar) — depois cancele a venda. ' .
                'Atenção: NFC-e tem prazo curto de cancelamento na SEFAZ (em geral 30 minutos).',
                $notaViva
            );
        }
    }
}
