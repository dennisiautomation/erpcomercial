<?php

namespace App\Services;

use App\Models\EstoqueMovimentacao;
use App\Models\Produto;
use App\Models\TransferenciaEstoque;
use App\Models\TransferenciaEstoqueItem;
use App\Models\Unidade;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas e operações de estoque cross-unidade.
 *
 * Trabalha com 3 políticas (Empresa::politica_estoque_inter_unidade):
 *  - silos          : ignora outras unidades
 *  - ver_apenas     : exibe saldo de outras unidades (não vende)
 *  - ver_e_vender   : pode vender remotamente — gera TransferenciaEstoque
 *                     automática origem→destino na finalização
 *
 * Não decide a política aqui — quem decide é o caller (PdvController etc).
 * Este serviço fornece a primitivas.
 */
class EstoqueMultiUnidadeService
{
    /**
     * Saldo atual do produto em cada unidade ativa da empresa.
     *
     * @return array<int, array{unidade_id:int, nome:string, saldo:float}>
     *         ordenado: unidade atual primeiro (se passada), depois saldo desc
     */
    public function saldoPorUnidade(int $empresaId, int $produtoId, ?int $unidadeAtual = null): array
    {
        $unidades = Unidade::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('status', 'ativa')
            ->get(['id', 'nome', 'cnpj']);

        if ($unidades->isEmpty()) {
            return [];
        }

        // Última movimentação por unidade nos dá saldo atual
        $saldos = DB::table('estoque_movimentacoes as e')
            ->select('e.unidade_id', 'e.quantidade_posterior')
            ->joinSub(
                DB::table('estoque_movimentacoes')
                    ->select('unidade_id', DB::raw('MAX(id) as ultimo_id'))
                    ->where('empresa_id', $empresaId)
                    ->where('produto_id', $produtoId)
                    ->groupBy('unidade_id'),
                'u',
                fn ($j) => $j->on('e.id', '=', 'u.ultimo_id')
            )
            ->pluck('e.quantidade_posterior', 'e.unidade_id')
            ->all();

        $resultado = $unidades->map(fn ($u) => [
            'unidade_id' => $u->id,
            'nome' => $u->nome,
            'cnpj' => $u->cnpj,
            'saldo' => (float) ($saldos[$u->id] ?? 0),
            'eh_atual' => $unidadeAtual && $u->id === $unidadeAtual,
        ])->toArray();

        // Atual primeiro, depois maior saldo
        usort($resultado, function ($a, $b) {
            if ($a['eh_atual'] !== $b['eh_atual']) {
                return $b['eh_atual'] <=> $a['eh_atual'];
            }
            return $b['saldo'] <=> $a['saldo'];
        });

        return $resultado;
    }

    /**
     * Lista outras unidades (≠ atual) que têm estoque positivo do produto.
     * Atalho útil quando o PDV detecta estoque local zerado.
     *
     * @return array<int, array{unidade_id:int, nome:string, saldo:float}>
     */
    public function outrasUnidadesComEstoque(int $empresaId, int $produtoId, int $unidadeAtual): array
    {
        $todos = $this->saldoPorUnidade($empresaId, $produtoId, $unidadeAtual);
        return array_values(array_filter(
            $todos,
            fn ($u) => $u['unidade_id'] !== $unidadeAtual && $u['saldo'] > 0
        ));
    }

    /**
     * Registra venda remota: baixa estoque da unidade origem e cria
     * TransferenciaEstoque automática (origem → destino) com status
     * 'concluida_venda_remota' para auditoria.
     *
     * NÃO cria movimentação na unidade destino — o produto saiu fisicamente
     * para o consumidor a partir da origem (ou foi entregue depois).
     *
     * Roda dentro de transaction. Retorna a TransferenciaEstoque criada.
     */
    public function registrarVendaRemota(
        Venda $venda,
        VendaItem $item,
        int $unidadeOrigemId,
        int $userId,
    ): TransferenciaEstoque {
        return DB::transaction(function () use ($venda, $item, $unidadeOrigemId, $userId) {
            $empresaId = $venda->empresa_id;
            $produtoId = $item->produto_id;
            $quantidade = (float) $item->quantidade;

            // Saldo atual na origem — lockForUpdate evita race entre vendas
            // remotas concorrentes do mesmo produto na mesma unidade.
            $estoqueAnterior = (float) (EstoqueMovimentacao::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->where('unidade_id', $unidadeOrigemId)
                ->where('produto_id', $produtoId)
                ->lockForUpdate()
                ->latest('id')
                ->value('quantidade_posterior') ?? 0);

            if ($estoqueAnterior < $quantidade) {
                throw new \RuntimeException(
                    "Estoque insuficiente na unidade origem (#{$unidadeOrigemId}): "
                    . "saldo={$estoqueAnterior}, solicitado={$quantidade}"
                );
            }

            // Baixa estoque da origem — nomes de coluna corretos da tabela:
            // origem_tipo/origem_id (rastreabilidade) + observacoes (motivo).
            EstoqueMovimentacao::create([
                'empresa_id' => $empresaId,
                'unidade_id' => $unidadeOrigemId,
                'produto_id' => $produtoId,
                'tipo' => 'saida',
                'quantidade' => $quantidade,
                'quantidade_anterior' => $estoqueAnterior,
                'quantidade_posterior' => $estoqueAnterior - $quantidade,
                'custo_unitario' => $item->preco_unitario,
                'origem_tipo' => Venda::class,
                'origem_id' => $venda->id,
                'user_id' => $userId,
                'observacoes' => "Venda remota \u{2014} item #{$item->id} da venda #{$venda->id} "
                    . "(destino: unidade #{$venda->unidade_id})",
            ]);

            // Persiste origem no item
            $item->unidade_origem_id = $unidadeOrigemId;
            $item->save();

            // Status enum aceito pela tabela: 'concluida' (a observação
            // identifica que foi venda remota — separação completa exigiria
            // migration para estender o enum).
            $transferencia = TransferenciaEstoque::create([
                'empresa_id' => $empresaId,
                'unidade_origem_id' => $unidadeOrigemId,
                'unidade_destino_id' => $venda->unidade_id,
                'user_solicitante_id' => $userId,
                'user_aprovador_id' => $userId,
                'status' => 'concluida',
                'observacoes' => "[Venda remota] venda #{$venda->id} (item #{$item->id})",
            ]);

            TransferenciaEstoqueItem::create([
                'transferencia_estoque_id' => $transferencia->id,
                'produto_id' => $produtoId,
                'quantidade' => $quantidade,
            ]);

            return $transferencia;
        });
    }
}
