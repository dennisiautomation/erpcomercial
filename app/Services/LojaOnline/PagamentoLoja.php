<?php

namespace App\Services\LojaOnline;

use App\Enums\StatusPedido;
use App\Jobs\DespacharEntregaUberJob;
use App\Models\EmpresaGateway;
use App\Models\LojaOnline;
use App\Models\Pedido;
use App\Models\PedidoCobranca;
use App\Models\PedidoLinkCartao;
use App\Services\Pagamento\AsaasService;
use App\Services\Pagamento\CartaoPedidoService;
use App\Services\Pagamento\InfinitePayService;
use App\Services\Pix\PixPedidoService;
use App\Services\Pix\SicrediPixService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pagamento da loja online — só ORQUESTRA o que a empresa já tem ligado na aba
 * Integração (14/11/2026). Nenhuma credencial nova, nenhum gateway novo:
 *
 *   PIX na tela  → Sicredi (copia-e-cola + QR desenhado no navegador); sem
 *                  Sicredi, Asaas (QR pronto); sem os dois, o link da InfinitePay
 *                  (que aceita PIX).
 *   Cartão       → link do provedor em uso (Asaas ou InfinitePay), o mesmo do agente.
 *   A combinar   → pedido fica em rascunho para a equipe cobrar.
 *
 * A confirmação é a de sempre (webhooks + rotinas de 15 min). A tela do pedido
 * ainda pergunta de tempos em tempos (conferir) para o comprador não esperar.
 */
class PagamentoLoja
{
    /** @return array{pix: ?string, cartao: ?string, combinar: bool} provedor de cada meio (null = indisponível) */
    public static function meios(LojaOnline $loja): array
    {
        $empresa = (int) $loja->empresa_id;
        $cartao = CartaoPedidoService::provedorEmUso($empresa);

        $pix = null;
        if ($loja->opcao('pix')) {
            if (SicrediPixService::paraEmpresa($empresa)) {
                $pix = EmpresaGateway::PROVEDOR_SICREDI_PIX;
            } elseif (AsaasService::ativoPara($empresa)) {
                $pix = EmpresaGateway::PROVEDOR_ASAAS;
            } elseif (InfinitePayService::ativoPara($empresa)) {
                $pix = EmpresaGateway::PROVEDOR_INFINITEPAY;
            }
        }

        $meios = [
            'pix' => $pix,
            'cartao' => $loja->opcao('cartao') ? $cartao : null,
            'combinar' => (bool) $loja->opcao('combinar'),
        ];

        // Loja sem nenhum meio ligado não pode ficar sem saída: combinar sempre vale.
        if (! $meios['pix'] && ! $meios['cartao']) {
            $meios['combinar'] = true;
        }

        return $meios;
    }

    /** Boleto disponível? Só pelo Asaas, e só se a loja ligou. */
    public static function temBoleto(LojaOnline $loja): bool
    {
        return (bool) $loja->opcao('boleto') && AsaasService::ativoPara((int) $loja->empresa_id) !== null;
    }

    /**
     * Boleto do pedido (Asaas). Reaproveita o boleto já emitido para o mesmo valor.
     *
     * @return array{ok: bool, tipo?: string, link?: string, linha?: string, vence_em?: ?string, erro?: string}
     */
    public function boleto(LojaOnline $loja, Pedido $pedido): array
    {
        if (! self::temBoleto($loja)) {
            return ['ok' => false, 'erro' => 'Boleto não está disponível nesta loja.'];
        }
        if (blank(preg_replace('/\D/', '', (string) $pedido->cliente?->cpf_cnpj))) {
            return ['ok' => false, 'erro' => 'Para pagar com boleto é preciso informar o CPF ou CNPJ no pedido. Fale com a loja.'];
        }
        try {
            $existente = PedidoCobranca::where('empresa_id', $pedido->empresa_id)->where('pedido_id', $pedido->id)
                ->where('provedor', EmpresaGateway::PROVEDOR_ASAAS)->where('valor', round((float) $pedido->total, 2))
                ->where('status', 'ATIVA')->whereNull('pago_em')->orderByDesc('id')->get()
                ->first(fn (PedidoCobranca $c) => ($c->payload['tipo'] ?? '') === 'boleto' && $c->expira_em && $c->expira_em->isFuture());
            if (! $existente) {
                $res = AsaasService::ativoPara((int) $pedido->empresa_id)->boletoParaPedido($pedido->fresh(['cliente']));
                $existente = PedidoCobranca::create([
                    'empresa_id' => $pedido->empresa_id, 'pedido_id' => $pedido->id, 'provedor' => EmpresaGateway::PROVEDOR_ASAAS,
                    'txid' => $res['id'], 'valor' => $pedido->total, 'status' => 'ATIVA', 'copia_cola' => $res['linha'],
                    'expira_em' => \Illuminate\Support\Carbon::parse($res['vence_em'])->endOfDay(),
                    'payload' => ['tipo' => 'boleto', 'link' => $res['link']],
                ]);
            }

            return ['ok' => true, 'tipo' => 'boleto', 'link' => $existente->payload['link'] ?? '', 'linha' => (string) $existente->copia_cola,
                'vence_em' => $existente->expira_em?->format('d/m/Y')];
        } catch (\Throwable $e) {
            Log::channel('integracao')->error('Loja online: falha ao gerar boleto', ['pedido_id' => $pedido->id, 'erro' => $e->getMessage()]);

            return ['ok' => false, 'erro' => 'Não foi possível gerar o boleto agora.'];
        }
    }

    /**
     * Devolve o dinheiro do pedido. Automático só quando o pagamento foi pelo
     * Asaas (PIX, boleto ou cartão); nos outros provedores a devolução é feita no
     * banco/provedor e aqui fica só o REGISTRO de que foi devolvido.
     *
     * @return array{ok: bool, automatico: bool, mensagem: string}
     */
    public function estornar(Pedido $pedido, string $motivo, bool $jaDevolvi = false): array
    {
        if ($pedido->loja_estornado_em) {
            return ['ok' => false, 'automatico' => false, 'mensagem' => 'Este pedido já está marcado como devolvido.'];
        }
        $cobranca = PedidoCobranca::where('empresa_id', $pedido->empresa_id)->where('pedido_id', $pedido->id)->whereNotNull('pago_em')->orderByDesc('id')->first();
        $link = PedidoLinkCartao::where('empresa_id', $pedido->empresa_id)->where('pedido_id', $pedido->id)->where('status', PedidoLinkCartao::STATUS_PAGO)->orderByDesc('id')->first();
        if (! $cobranca && ! $link) {
            return ['ok' => false, 'automatico' => false, 'mensagem' => 'Não há pagamento confirmado neste pedido para devolver.'];
        }

        $paymentId = null;
        if ($cobranca && $cobranca->provedor === EmpresaGateway::PROVEDOR_ASAAS) {
            $paymentId = (string) $cobranca->txid;
        } elseif ($link && ($link->provedor ?? '') === EmpresaGateway::PROVEDOR_ASAAS && filled($link->referencia ?? null)) {
            $paymentId = (string) $link->referencia;
        }

        $automatico = false;
        if ($paymentId && ! $jaDevolvi) {
            $asaas = AsaasService::ativoPara((int) $pedido->empresa_id);
            if (! $asaas) {
                return ['ok' => false, 'automatico' => false, 'mensagem' => 'O Asaas não está mais ligado nesta empresa. Devolva pelo painel do Asaas e marque "já devolvi".'];
            }
            try {
                $asaas->estornar($paymentId, (float) $pedido->total, 'Pedido #' . $pedido->numero . ' — ' . $motivo);
                $automatico = true;
            } catch (\Throwable $e) {
                Log::channel('integracao')->error('Loja online: estorno recusado', ['pedido_id' => $pedido->id, 'erro' => $e->getMessage()]);

                return ['ok' => false, 'automatico' => false, 'mensagem' => 'O Asaas recusou a devolução. Confira no painel do Asaas; se devolver por lá, marque "já devolvi".'];
            }
        } elseif (! $jaDevolvi) {
            return ['ok' => false, 'automatico' => false, 'mensagem' => 'Este pagamento não foi pelo Asaas: a devolução é feita no seu banco ou provedor. Depois de devolver, marque "já devolvi".'];
        }

        $quem = auth()->user()?->name ?? 'sistema';
        $pedido->forceFill([
            'loja_estornado_em' => now(),
            'loja_estorno_obs' => mb_substr(($automatico ? 'Devolvido pelo Asaas' : 'Devolução registrada à mão') . ' por ' . $quem . ': ' . $motivo, 0, 255),
            'observacoes_internas' => trim(($pedido->observacoes_internas ?? '') . "\nDINHEIRO DEVOLVIDO em " . now()->format('d/m/Y H:i') . ' — R$ ' . number_format((float) $pedido->total, 2, ',', '.')
                . ($automatico ? ' (automático, Asaas)' : ' (registrado à mão)') . " por {$quem}. Motivo: {$motivo}"),
        ])->save();

        return ['ok' => true, 'automatico' => $automatico, 'mensagem' => $automatico ? 'Devolução pedida ao Asaas. O dinheiro volta para o cliente pelo mesmo meio do pagamento.' : 'Devolução registrada.'];
    }

    /**
     * PIX do pedido.
     *
     * @return array{ok: bool, tipo?: string, copia_cola?: string, qr_base64?: ?string, expira_em?: ?string, link?: string, erro?: string}
     */
    public function pix(LojaOnline $loja, Pedido $pedido): array
    {
        $provedor = self::meios($loja)['pix'];

        try {
            if ($provedor === EmpresaGateway::PROVEDOR_SICREDI_PIX) {
                $r = app(PixPedidoService::class)->cobrancaParaPedido($pedido->fresh(['cliente', 'unidade']));
                if (! $r['success'] || blank($r['cobranca']->copia_cola)) {
                    return ['ok' => false, 'erro' => 'Não foi possível gerar o PIX agora.'];
                }

                return ['ok' => true, 'tipo' => 'qr', 'copia_cola' => $r['cobranca']->copia_cola, 'qr_base64' => null,
                    'expira_em' => $r['cobranca']->expira_em?->toIso8601String()];
            }

            if ($provedor === EmpresaGateway::PROVEDOR_ASAAS) {
                $cobranca = $this->cobrancaAsaas($pedido);

                return ['ok' => true, 'tipo' => 'qr', 'copia_cola' => $cobranca->copia_cola,
                    'qr_base64' => $cobranca->payload['qr_base64'] ?? null, 'expira_em' => $cobranca->expira_em?->toIso8601String()];
            }

            if ($provedor === EmpresaGateway::PROVEDOR_INFINITEPAY) {
                return $this->cartao($pedido);
            }
        } catch (\Throwable $e) {
            Log::channel('integracao')->error('Loja online: falha ao gerar PIX', ['pedido_id' => $pedido->id, 'erro' => $e->getMessage()]);
        }

        return ['ok' => false, 'erro' => 'Não foi possível gerar o PIX agora.'];
    }

    /** @return array{ok: bool, tipo?: string, link?: string, erro?: string} */
    public function cartao(Pedido $pedido): array
    {
        $r = app(CartaoPedidoService::class)->linkParaPedido($pedido->fresh(['cliente', 'itens']));
        if (! $r['success'] || ! $r['link'] || blank($r['link']->link)) {
            return ['ok' => false, 'erro' => $r['error'] ?? 'Não foi possível gerar o link de pagamento agora.'];
        }

        return ['ok' => true, 'tipo' => 'link', 'link' => $r['link']->link];
    }

    private function cobrancaAsaas(Pedido $pedido): PedidoCobranca
    {
        $existente = PedidoCobranca::where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)
            ->where('provedor', EmpresaGateway::PROVEDOR_ASAAS)
            ->where('valor', round((float) $pedido->total, 2))
            ->orderByDesc('id')
            ->get()->first(fn (PedidoCobranca $c) => ($c->payload['tipo'] ?? 'pix') !== 'boleto');
        if ($existente && ($existente->paga() || $existente->reutilizavel())) {
            return $existente;
        }

        $asaas = AsaasService::ativoPara((int) $pedido->empresa_id) ?? throw new \RuntimeException('Asaas não está ativo.');
        $res = $asaas->pixParaPedido($pedido->fresh(['cliente']));
        if (blank($res['copia_cola'])) {
            throw new \RuntimeException('O Asaas não devolveu o código PIX.');
        }

        return PedidoCobranca::create([
            'empresa_id' => $pedido->empresa_id,
            'pedido_id' => $pedido->id,
            'provedor' => EmpresaGateway::PROVEDOR_ASAAS,
            'txid' => $res['id'],
            'valor' => $pedido->total,
            'status' => 'ATIVA',
            'copia_cola' => $res['copia_cola'],
            'expira_em' => now()->addDay(),
            'payload' => ['qr_base64' => $res['qr_base64']],
        ]);
    }

    /**
     * Situação para a tela do comprador.
     *
     * @return array{situacao: string, rotulo: string, pago: bool}
     *   situacao: aguardando | pago | confirmado | faturado | entregue | cancelado
     */
    public static function situacao(Pedido $pedido): array
    {
        $status = $pedido->status instanceof StatusPedido ? $pedido->status : StatusPedido::from((string) $pedido->status);

        $pago = PedidoCobranca::where('empresa_id', $pedido->empresa_id)->where('pedido_id', $pedido->id)->whereNotNull('pago_em')->exists()
            || PedidoLinkCartao::where('empresa_id', $pedido->empresa_id)->where('pedido_id', $pedido->id)
                ->where('status', PedidoLinkCartao::STATUS_PAGO)->exists();

        return match (true) {
            $status === StatusPedido::Cancelado => ['situacao' => 'cancelado', 'rotulo' => 'Pedido cancelado', 'pago' => $pago],
            // 15/11/2026: a loja informou o envio (com ou sem código de rastreio) e o ERP ainda não marcou entregue
            $status !== StatusPedido::Entregue && $pedido->loja_enviado_em !== null => ['situacao' => 'enviado', 'rotulo' => 'Pedido enviado', 'pago' => $pago],
            $status === StatusPedido::Entregue => ['situacao' => 'entregue', 'rotulo' => 'Pedido entregue', 'pago' => true],
            $status === StatusPedido::Faturado => ['situacao' => 'faturado', 'rotulo' => 'Pedido separado e faturado', 'pago' => true],
            $pago => ['situacao' => 'pago', 'rotulo' => 'Pagamento confirmado', 'pago' => true],
            $status === StatusPedido::Confirmado => ['situacao' => 'confirmado', 'rotulo' => 'Pedido confirmado pela loja', 'pago' => false],
            default => ['situacao' => 'aguardando', 'rotulo' => 'Aguardando pagamento', 'pago' => false],
        };
    }

    /**
     * Pergunta ao provedor se o pagamento caiu (rede de segurança do webhook,
     * disparada pela tela do pedido). No máximo uma consulta a cada 8 s por pedido.
     */
    public function conferir(Pedido $pedido): void
    {
        if (! Cache::add('loja_online_conferir_' . $pedido->id, 1, 8)) {
            return;
        }

        try {
            $cobrancas = PedidoCobranca::where('empresa_id', $pedido->empresa_id)->where('pedido_id', $pedido->id)
                ->where('status', 'ATIVA')->whereNull('pago_em')->orderByDesc('id')->limit(2)->get();

            foreach ($cobrancas as $cobranca) {
                if ($cobranca->provedor === EmpresaGateway::PROVEDOR_ASAAS) {
                    $this->conferirAsaas($pedido, $cobranca);
                } else {
                    app(PixPedidoService::class)->sincronizarCobranca($cobranca);
                }
            }

            $link = PedidoLinkCartao::where('empresa_id', $pedido->empresa_id)->where('pedido_id', $pedido->id)
                ->where('provedor', EmpresaGateway::PROVEDOR_INFINITEPAY)
                ->where('status', PedidoLinkCartao::STATUS_ATIVO)->latest('id')->first();
            if ($link && ($servico = InfinitePayService::ativoPara((int) $pedido->empresa_id))) {
                $consulta = $servico->consultarPagamento($link->referencia, $link->transaction_nsu, $link->slug);
                app(CartaoPedidoService::class)->confirmarInfinitePay($link, $consulta, [], 'loja-online');
            }
        } catch (\Throwable $e) {
            Log::channel('integracao')->warning('Loja online: conferência de pagamento falhou', ['pedido_id' => $pedido->id, 'erro' => $e->getMessage()]);
        }
    }

    private function conferirAsaas(Pedido $pedido, PedidoCobranca $cobranca): void
    {
        $asaas = AsaasService::ativoPara((int) $pedido->empresa_id);
        if (! $asaas || ! $asaas->pagamentoRecebido($cobranca->txid)) {
            return;
        }

        $marcou = PedidoCobranca::whereKey($cobranca->id)->whereNull('pago_em')
            ->update(['status' => 'CONCLUIDA', 'pago_em' => now(), 'updated_at' => now()]);
        if (! $marcou) {
            return; // o webhook chegou primeiro
        }

        $pedido = Pedido::withoutGlobalScopes()->where('empresa_id', $cobranca->empresa_id)->whereKey($cobranca->pedido_id)->first();
        if (! $pedido) {
            return;
        }
        if ($pedido->status === StatusPedido::Rascunho) {
            $pedido->status = StatusPedido::Confirmado;
        }
        $ehBoleto = ($cobranca->payload['tipo'] ?? '') === 'boleto';
        if (! $pedido->condicao_pagamento) {
            $pedido->condicao_pagamento = $ehBoleto ? 'boleto' : 'pix';
        }
        if (! str_contains((string) $pedido->observacoes_internas, $cobranca->txid)) {
            $pedido->observacoes_internas = trim(($pedido->observacoes_internas ?? '') . sprintf(
                "\n" . ($ehBoleto ? 'BOLETO' : 'PIX') . " PAGO via Asaas em %s — R$ %s (payment %s).",
                now()->format('d/m/Y H:i'), number_format((float) $cobranca->valor, 2, ',', '.'), $cobranca->txid
            ));
        }
        $pedido->save();

        if ($pedido->status !== StatusPedido::Cancelado) {
            DespacharEntregaUberJob::dispatch($pedido->id, (int) $pedido->empresa_id);
        }
    }
}
