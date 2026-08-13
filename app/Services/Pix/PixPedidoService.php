<?php

namespace App\Services\Pix;

use App\Enums\StatusPedido;
use App\Models\Pedido;
use App\Models\PedidoCobranca;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra a cobrança PIX de um pedido do Agente IA.
 *
 * Regras de negócio (fase 1 do pagamento, decisão do Dennis 13/08/2026):
 * - Cobrança nasce junto com o pedido (ou sob demanda na 2ª via).
 * - Reuso: só reaproveita cobrança ATIVA com copia-e-cola e não vencida
 *   (lição do JL — reuso de cobrança vazia travava o link de pagamento).
 * - Pagamento confirmado => pedido rascunho→confirmado + observação.
 *   NÃO fatura, NÃO movimenta estoque, NÃO mexe no caixa — humano segue
 *   responsável pelo faturamento no ERP.
 * - Confirmação SEMPRE via consulta à API (webhook é só gatilho; nunca
 *   confiamos no payload recebido).
 */
class PixPedidoService
{
    /**
     * Cria (ou reaproveita) a cobrança PIX de um pedido.
     *
     * @return array{success: bool, cobranca?: PedidoCobranca, error?: string}
     */
    public function cobrancaParaPedido(Pedido $pedido): array
    {
        $sicredi = SicrediPixService::paraEmpresa($pedido->empresa_id);

        if (! $sicredi) {
            return ['success' => false, 'error' => 'PIX não configurado para esta empresa.'];
        }

        if ($pedido->status === StatusPedido::Cancelado) {
            return ['success' => false, 'error' => 'Pedido cancelado não recebe cobrança.'];
        }

        $existente = PedidoCobranca::where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)
            ->orderByDesc('id')
            ->first();

        if ($existente?->paga()) {
            return ['success' => true, 'cobranca' => $existente];
        }

        if ($existente?->reutilizavel()) {
            return ['success' => true, 'cobranca' => $existente];
        }

        $txid = $sicredi->gerarTxid($pedido->id);
        $descricao = "Pedido #{$pedido->numero} " . ($pedido->unidade?->nome ?? '');

        $devedor = null;
        $doc = preg_replace('/[^0-9A-Za-z]/', '', (string) $pedido->cliente?->cpf_cnpj);
        if ($doc !== '' && $pedido->cliente) {
            $campo = strlen($doc) === 11 ? 'cpf' : 'cnpj';
            $devedor = [$campo => $doc, 'nome' => mb_substr($pedido->cliente->nome_razao_social, 0, 200)];
        }

        $resultado = $sicredi->criarCobranca((float) $pedido->total, $txid, trim($descricao), $devedor);

        if (! $resultado['success']) {
            // Sicredi recusa devedor inconsistente — tenta de novo sem (lição JL)
            if ($devedor) {
                $resultado = $sicredi->criarCobranca((float) $pedido->total, $txid, trim($descricao));
            }

            if (! $resultado['success']) {
                PedidoCobranca::create([
                    'empresa_id' => $pedido->empresa_id,
                    'pedido_id' => $pedido->id,
                    'txid' => $txid,
                    'valor' => $pedido->total,
                    'status' => 'ERRO',
                    'payload' => ['erro' => $resultado['error'] ?? 'desconhecido'],
                ]);

                return ['success' => false, 'error' => $resultado['error'] ?? 'Falha ao criar cobrança.'];
            }
        }

        $gateway = \App\Models\EmpresaGateway::sicrediAtivoPara($pedido->empresa_id);

        $cobranca = PedidoCobranca::create([
            'empresa_id' => $pedido->empresa_id,
            'pedido_id' => $pedido->id,
            'txid' => $resultado['txid'],
            'valor' => $pedido->total,
            'chave' => $gateway?->chave_pix,
            'status' => $resultado['status'] ?? 'ATIVA',
            'copia_cola' => $resultado['copia_cola'] ?? null,
            'location' => $resultado['location'] ?? null,
            'expira_em' => now()->addSeconds($gateway?->expiracao_segundos ?: 86400),
        ]);

        return ['success' => true, 'cobranca' => $cobranca];
    }

    /**
     * Consulta a cobrança na API e, se paga, confirma o pedido.
     * Idempotente — chamada pelo webhook E pelo cron de sincronização.
     */
    public function sincronizarCobranca(PedidoCobranca $cobranca): void
    {
        if ($cobranca->paga() || $cobranca->status === 'ERRO') {
            return;
        }

        $sicredi = SicrediPixService::paraEmpresa($cobranca->empresa_id);

        if (! $sicredi) {
            return;
        }

        $consulta = $sicredi->consultarCobranca($cobranca->txid);

        if (! $consulta['success']) {
            return;
        }

        $cobranca->status = $consulta['status'] ?: $cobranca->status;

        if ($consulta['pago']) {
            $pagamento = $consulta['pix'][0] ?? [];
            $cobranca->e2eid = $pagamento['endToEndId'] ?? null;
            $cobranca->pago_em = isset($pagamento['horario'])
                ? \Illuminate\Support\Carbon::parse($pagamento['horario'])
                : now();
            $cobranca->payload = $consulta['data'] ?? null;
        }

        $cobranca->save();

        if ($consulta['pago']) {
            $this->confirmarPedido($cobranca);
        }
    }

    private function confirmarPedido(PedidoCobranca $cobranca): void
    {
        $pedido = Pedido::withoutGlobalScopes()
            ->where('empresa_id', $cobranca->empresa_id)
            ->whereKey($cobranca->pedido_id)
            ->first();

        if (! $pedido) {
            return;
        }

        $nota = sprintf(
            "\nPIX PAGO em %s — R$ %s (txid %s%s).",
            $cobranca->pago_em?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
            number_format((float) $cobranca->valor, 2, ',', '.'),
            $cobranca->txid,
            $cobranca->e2eid ? ", e2e {$cobranca->e2eid}" : ''
        );

        // Rascunho vira confirmado; estados mais avançados só ganham a anotação
        if ($pedido->status === StatusPedido::Rascunho) {
            $pedido->status = StatusPedido::Confirmado;
        }

        if (! str_contains((string) $pedido->observacoes_internas, $cobranca->txid)) {
            $pedido->observacoes_internas = trim(($pedido->observacoes_internas ?? '') . $nota);
        }

        $pedido->save();

        // Fase 3 (13/08): pagamento caiu → despacho automático Uber Direct
        // (job em fila; falha lá NUNCA desfaz a confirmação do pedido).
        \App\Jobs\DespacharEntregaUberJob::dispatch($pedido->id, (int) $cobranca->empresa_id);

        Log::channel('integracao')->info('Sicredi PIX: pedido confirmado por pagamento', [
            'empresa_id' => $cobranca->empresa_id,
            'pedido_id' => $pedido->id,
            'numero' => $pedido->numero,
            'txid' => $cobranca->txid,
            'e2eid' => $cobranca->e2eid,
        ]);
    }
}
