<?php

namespace App\Services\Pagamento;

use App\Enums\StatusPedido;
use App\Jobs\DespacharEntregaUberJob;
use App\Models\AgenteIaConfig;
use App\Models\EmpresaGateway;
use App\Models\Pedido;
use App\Models\PedidoLinkCartao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cartão do Vendedor IA — ponto único (25/09/2026).
 *
 * A empresa pode ter Asaas E InfinitePay ligados, mas o agente usa UM: o
 * escolhido em AgenteIaConfig::provedor_cartao. Se o escolhido não estiver
 * utilizável, vale o outro que estiver (Asaas primeiro — era o único até
 * aqui, então empresa antiga continua igual). Nenhum ligado = sem cartão.
 *
 * Todo link gerado fica em pedido_links_cartao: a 2ª via reaproveita o link
 * ainda válido do mesmo provedor e do mesmo valor, a checagem periódica da
 * InfinitePay consulta os abertos, e o pedido mostra parcelas/comprovante.
 */
class CartaoPedidoService
{
    /** Link reaproveitado na 2ª via enquanto for mais novo que isto (horas). */
    private const VALIDADE_REUSO_HORAS = 20;

    /** Checagem periódica olha links abertos até esta idade (horas). */
    private const JANELA_SINCRONIZAR_HORAS = 72;

    public static function provedorEmUso(int $empresaId): ?string
    {
        $ativos = EmpresaGateway::where('empresa_id', $empresaId)
            ->whereIn('provedor', EmpresaGateway::PROVEDORES_CARTAO)
            ->get()
            ->filter(fn (EmpresaGateway $g) => $g->capacidadeAtiva())
            ->pluck('provedor')
            ->all();

        if ($ativos === []) {
            return null;
        }

        $escolhido = AgenteIaConfig::where('empresa_id', $empresaId)->value('provedor_cartao');
        if ($escolhido && in_array($escolhido, $ativos, true)) {
            return $escolhido;
        }

        foreach (EmpresaGateway::PROVEDORES_CARTAO as $provedor) {
            if (in_array($provedor, $ativos, true)) {
                return $provedor;
            }
        }

        return null;
    }

    /**
     * Grava a escolha quando ainda não há uma, para ligar um SEGUNDO cartão
     * nunca trocar o provedor em uso sem ninguém pedir. Chamado pelos cards
     * Asaas/InfinitePay: `$antes` é o provedor em uso ANTES de salvar (se já
     * havia um, ele fica; se não havia, fica o que acabou de ligar).
     */
    public static function fixarEscolhaSeVazia(int $empresaId, ?string $antes): void
    {
        $config = AgenteIaConfig::where('empresa_id', $empresaId)->first();
        if (! $config || filled($config->provedor_cartao)) {
            return;
        }

        $escolha = $antes ?? self::provedorEmUso($empresaId);
        if ($escolha) {
            $config->update(['provedor_cartao' => $escolha]);
        }
    }

    public static function rotulo(?string $provedor): string
    {
        return match ($provedor) {
            EmpresaGateway::PROVEDOR_ASAAS => 'Asaas',
            EmpresaGateway::PROVEDOR_INFINITEPAY => 'InfinitePay',
            default => '—',
        };
    }

    /** Já existe link de cartão pago para o pedido? */
    public static function pedidoPagoNoLink(Pedido $pedido): ?PedidoLinkCartao
    {
        return PedidoLinkCartao::where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)
            ->where('status', PedidoLinkCartao::STATUS_PAGO)
            ->latest('id')
            ->first();
    }

    /**
     * Link do pedido no provedor em uso: reaproveita o aberto (mesmo provedor,
     * mesmo valor, recente) ou cria outro.
     *
     * @return array{success: bool, link: ?PedidoLinkCartao, pago: bool, error: ?string}
     */
    public function linkParaPedido(Pedido $pedido): array
    {
        if ($pago = self::pedidoPagoNoLink($pedido)) {
            return ['success' => true, 'link' => $pago, 'pago' => true, 'error' => null];
        }

        $provedor = self::provedorEmUso((int) $pedido->empresa_id);
        if (! $provedor) {
            return ['success' => false, 'link' => null, 'pago' => false, 'error' => 'Cartão não está configurado para esta empresa.'];
        }

        $aberto = PedidoLinkCartao::where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)
            ->where('provedor', $provedor)
            ->where('status', PedidoLinkCartao::STATUS_ATIVO)
            ->where('valor', round((float) $pedido->total, 2))
            ->where('created_at', '>=', now()->subHours(self::VALIDADE_REUSO_HORAS))
            ->latest('id')
            ->first();
        if ($aberto) {
            return ['success' => true, 'link' => $aberto, 'pago' => false, 'error' => null];
        }

        try {
            $link = $provedor === EmpresaGateway::PROVEDOR_INFINITEPAY
                ? $this->criarInfinitePay($pedido)
                : $this->criarAsaas($pedido);

            return ['success' => true, 'link' => $link, 'pago' => false, 'error' => null];
        } catch (\Throwable $e) {
            Log::channel('integracao')->error('Cartão: falha ao gerar link', [
                'empresa_id' => $pedido->empresa_id,
                'pedido_id' => $pedido->id,
                'provedor' => $provedor,
                'erro' => $e->getMessage(),
            ]);
            EmpresaGateway::where('empresa_id', $pedido->empresa_id)->where('provedor', $provedor)->first()
                ?->forceFill(['ultima_falha' => mb_substr('Link do pedido #' . $pedido->numero . ': ' . $e->getMessage(), 0, 500)])
                ->saveQuietly();

            return ['success' => false, 'link' => null, 'pago' => false, 'error' => 'Não foi possível gerar o link de cartão agora.'];
        }
    }

    private function criarInfinitePay(Pedido $pedido): PedidoLinkCartao
    {
        $servico = InfinitePayService::ativoPara((int) $pedido->empresa_id)
            ?? throw new \RuntimeException('InfinitePay não está ativa.');

        // Rastreável e único: erp{empresa}p{pedido}t{instante}{4 aleatórios} — o
        // webhook e a consulta chegam com ele e é por ele que achamos o pedido.
        // (Sem o sufixo, dois links do mesmo pedido no mesmo segundo colidiam
        // no unique — pego no QA: link novo logo após mudar o total.)
        $orderNsu = sprintf('erp%dp%dt%s%s', $pedido->empresa_id, $pedido->id, now()->format('ymdHis'), Str::lower(Str::random(4)));
        $res = $servico->criarLink($pedido, $orderNsu);

        return PedidoLinkCartao::create([
            'empresa_id' => $pedido->empresa_id,
            'pedido_id' => $pedido->id,
            'provedor' => EmpresaGateway::PROVEDOR_INFINITEPAY,
            'referencia' => $orderNsu,
            'link' => $res['link'],
            'slug' => $res['slug'],
            'valor' => round((float) $pedido->total, 2),
            'status' => PedidoLinkCartao::STATUS_ATIVO,
            'payload' => $res['payload'],
        ]);
    }

    private function criarAsaas(Pedido $pedido): PedidoLinkCartao
    {
        $asaas = AsaasService::ativoPara((int) $pedido->empresa_id)
            ?? throw new \RuntimeException('Asaas não está ativo.');

        $res = $asaas->linkCartaoParaPedido($pedido->fresh(['cliente']));
        if (blank($res['link'])) {
            throw new \RuntimeException('O Asaas não devolveu o link de pagamento.');
        }

        return PedidoLinkCartao::create([
            'empresa_id' => $pedido->empresa_id,
            'pedido_id' => $pedido->id,
            'provedor' => EmpresaGateway::PROVEDOR_ASAAS,
            'referencia' => $res['id'] !== '' ? $res['id'] : 'asaas-p' . $pedido->id . '-' . now()->format('ymdHis'),
            'link' => $res['link'],
            'valor' => round((float) $pedido->total, 2),
            'status' => PedidoLinkCartao::STATUS_ATIVO,
        ]);
    }

    /**
     * Pagamento aprovado na InfinitePay (webhook ou checagem periódica) — só
     * depois do payment_check. Confirma o pedido como o PIX e o Asaas fazem.
     *
     * @param  array{paid: bool, amount: ?int, paid_amount: ?int, installments: ?int, capture_method: ?string, raw: array}  $consulta
     * @param  array{transaction_nsu?: ?string, slug?: ?string, receipt_url?: ?string}  $extras
     */
    public function confirmarInfinitePay(PedidoLinkCartao $link, array $consulta, array $extras, string $origem): bool
    {
        $valorCentavos = (int) round((float) $link->valor * 100);
        $pagoCentavos = (int) ($consulta['paid_amount'] ?? $consulta['amount'] ?? 0);

        if (! $consulta['paid']) {
            return false;
        }
        if ($pagoCentavos < $valorCentavos) {
            Log::channel('integracao')->warning('InfinitePay: pago MENOS que o pedido — não confirmado', [
                'empresa_id' => $link->empresa_id, 'pedido_id' => $link->pedido_id,
                'order_nsu' => $link->referencia, 'valor' => $valorCentavos, 'pago' => $pagoCentavos,
            ]);

            return false;
        }

        $confirmou = DB::transaction(function () use ($link, $consulta, $extras, $pagoCentavos) {
            $link = PedidoLinkCartao::whereKey($link->id)->lockForUpdate()->first();
            if (! $link || $link->pago()) {
                return false; // webhook e checagem chegaram juntos: o primeiro vence
            }

            $link->fill([
                'status' => PedidoLinkCartao::STATUS_PAGO,
                'transaction_nsu' => $extras['transaction_nsu'] ?? $link->transaction_nsu,
                'slug' => $extras['slug'] ?? $link->slug,
                'receipt_url' => $extras['receipt_url'] ?? $link->receipt_url,
                'capture_method' => $consulta['capture_method'],
                'parcelas' => $consulta['installments'],
                'valor_pago' => round($pagoCentavos / 100, 2),
                'pago_em' => now(),
                'payload' => array_merge($link->payload ?? [], ['payment_check' => $consulta['raw']]),
            ])->save();

            $ehPix = $consulta['capture_method'] === 'pix';
            $valorPago = $pagoCentavos / 100;
            $juros = round($valorPago - (float) $link->valor, 2);
            $detalhes = [];
            if (! $ehPix && ($consulta['installments'] ?? 1) > 1) {
                $detalhes[] = $consulta['installments'] . 'x';
            }
            if ($juros > 0) {
                $detalhes[] = 'cliente pagou R$ ' . number_format($valorPago, 2, ',', '.')
                    . ' com R$ ' . number_format($juros, 2, ',', '.') . ' de juros/taxa';
            }
            $detalhes[] = 'NSU ' . ($link->transaction_nsu ?: $link->referencia);

            $this->anotarPagamento(
                (int) $link->empresa_id,
                (int) $link->pedido_id,
                sprintf(
                    "\n%s PAGO via InfinitePay em %s — R$ %s (%s).",
                    $ehPix ? 'PIX' : 'CARTÃO',
                    now()->format('d/m/Y H:i'),
                    number_format((float) $link->valor, 2, ',', '.'),
                    implode('; ', $detalhes)
                ),
                $link->referencia,
                $ehPix ? 'pix' : 'cartao',
            );

            return true;
        });

        if ($confirmou) {
            Log::channel('integracao')->info('InfinitePay: pedido confirmado por pagamento', [
                'empresa_id' => $link->empresa_id, 'pedido_id' => $link->pedido_id,
                'order_nsu' => $link->referencia, 'origem' => $origem,
                'capture_method' => $consulta['capture_method'], 'parcelas' => $consulta['installments'],
            ]);
            $this->despachar((int) $link->pedido_id, (int) $link->empresa_id);
        }

        return $confirmou;
    }

    /** Asaas confirmou pelo webhook: marca o link guardado (quando houver). */
    public function marcarAsaasPago(int $empresaId, string $paymentId, float $valor): void
    {
        if ($paymentId === '') {
            return;
        }

        PedidoLinkCartao::where('empresa_id', $empresaId)
            ->where('provedor', EmpresaGateway::PROVEDOR_ASAAS)
            ->where('referencia', $paymentId)
            ->where('status', '!=', PedidoLinkCartao::STATUS_PAGO)
            ->update([
                'status' => PedidoLinkCartao::STATUS_PAGO,
                'capture_method' => 'credit_card',
                'valor_pago' => $valor,
                'pago_em' => now(),
                'updated_at' => now(),
            ]);
    }

    /** Pedido cancelado: links abertos deixam de valer para o ERP. */
    public static function cancelarLinks(Pedido $pedido): void
    {
        PedidoLinkCartao::where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)
            ->where('status', PedidoLinkCartao::STATUS_ATIVO)
            ->update(['status' => PedidoLinkCartao::STATUS_CANCELADO, 'updated_at' => now()]);
    }

    /**
     * Rede de segurança do webhook da InfinitePay: consulta os links abertos
     * recentes (e os cancelados, para avisar pagamento em pedido cancelado).
     *
     * @return array{consultados: int, confirmados: int, falhas: int}
     */
    public function sincronizarInfinitePay(): array
    {
        $out = ['consultados' => 0, 'confirmados' => 0, 'falhas' => 0];

        $links = PedidoLinkCartao::where('provedor', EmpresaGateway::PROVEDOR_INFINITEPAY)
            ->whereIn('status', [PedidoLinkCartao::STATUS_ATIVO, PedidoLinkCartao::STATUS_CANCELADO])
            ->where('created_at', '>=', now()->subHours(self::JANELA_SINCRONIZAR_HORAS))
            ->orderBy('id')
            ->get();

        foreach ($links as $link) {
            $servico = InfinitePayService::ativoPara((int) $link->empresa_id);
            if (! $servico) {
                continue;
            }
            $out['consultados']++;
            try {
                $consulta = $servico->consultarPagamento($link->referencia, $link->transaction_nsu, $link->slug);
                if ($this->confirmarInfinitePay($link, $consulta, [], 'checagem')) {
                    $out['confirmados']++;
                }
            } catch (\Throwable $e) {
                $out['falhas']++;
                Log::channel('integracao')->warning('InfinitePay: checagem falhou', [
                    'empresa_id' => $link->empresa_id, 'order_nsu' => $link->referencia, 'erro' => $e->getMessage(),
                ]);
            }
        }

        return $out;
    }

    /**
     * Anotação + rascunho→confirmado (dentro da transação de quem chamou).
     * Pedido CANCELADO não volta: fica a anotação para o humano estornar.
     */
    private function anotarPagamento(int $empresaId, int $pedidoId, string $nota, string $marca, string $condicao): void
    {
        $pedido = Pedido::withoutGlobalScopes()->where('empresa_id', $empresaId)->whereKey($pedidoId)->lockForUpdate()->first();
        if (! $pedido) {
            return;
        }

        if ($pedido->status === StatusPedido::Cancelado) {
            $nota .= ' ⚠️ Pedido estava CANCELADO — estornar.';
            Log::channel('integracao')->warning('Cartão: pagamento em pedido CANCELADO (estornar)', [
                'empresa_id' => $empresaId, 'pedido_id' => $pedidoId, 'referencia' => $marca,
            ]);
        } elseif ($pedido->status === StatusPedido::Rascunho) {
            $pedido->status = StatusPedido::Confirmado;
        }
        if (! $pedido->condicao_pagamento) {
            $pedido->condicao_pagamento = $condicao;
        }
        if (! str_contains((string) $pedido->observacoes_internas, $marca)) {
            $pedido->observacoes_internas = trim(($pedido->observacoes_internas ?? '') . $nota);
        }
        $pedido->save();
    }

    private function despachar(int $pedidoId, int $empresaId): void
    {
        $status = Pedido::withoutGlobalScopes()->whereKey($pedidoId)->value('status');
        if ($status === StatusPedido::Cancelado || $status === StatusPedido::Cancelado->value) {
            return;
        }

        // Mesmo gatilho do PIX/Asaas: o job ignora retirada, Melhor Envio e
        // empresa sem Uber; falha lá nunca desfaz a confirmação.
        DespacharEntregaUberJob::dispatch($pedidoId, $empresaId);
    }
}
