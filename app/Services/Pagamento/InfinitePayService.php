<?php

namespace App\Services\Pagamento;

use App\Models\EmpresaGateway;
use App\Models\Pedido;
use App\Models\PedidoItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * InfinitePay por EMPRESA (25/09/2026) — checkout por LINK (o cliente paga no
 * cartão ou no PIX na página da InfinitePay).
 *
 * Não existe chave de API: a conta é a InfiniteTag da empresa (`handle`, sem
 * o '$'), guardada em empresa_gateways.config.handle. Por isso o webhook NÃO
 * prova nada sozinho — todo aviso é reconfirmado em `payment_check` antes de
 * mexer no pedido (mesmo princípio do webhook Sicredi: o aviso é dica).
 *
 * Endpoints (documentação da InfinitePay, 25/09/2026):
 *   POST {base}/links          → cria o link  (itens em CENTAVOS)
 *   POST {base}/payment_check  → {success, paid, amount, paid_amount, installments, capture_method}
 * A resposta do /links traz o endereço em `url` (algumas versões: `payment_url`).
 */
class InfinitePayService
{
    public function __construct(private readonly EmpresaGateway $gateway)
    {
    }

    public static function ativoPara(int $empresaId): ?self
    {
        $gw = EmpresaGateway::ativoPara($empresaId, EmpresaGateway::PROVEDOR_INFINITEPAY);

        return ($gw && $gw->capacidadeAtiva()) ? new self($gw) : null;
    }

    /** InfiniteTag como a API espera: sem '$', sem espaços. */
    public static function normalizarHandle(?string $handle): string
    {
        return ltrim(preg_replace('/\s+/', '', (string) $handle), '$');
    }

    public function handle(): string
    {
        return self::normalizarHandle($this->gateway->config['handle'] ?? '');
    }

    public function gateway(): EmpresaGateway
    {
        return $this->gateway;
    }

    /**
     * Endereço que a InfinitePay chama quando o pagamento é aprovado. O token
     * da empresa vai no caminho: aviso sem ele nem chega a consultar nada.
     */
    public function webhookUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/api/integracao/v1/webhooks/infinitepay/'
            . ($this->gateway->config['webhook_token'] ?? '');
    }

    private function http()
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->baseUrl(rtrim((string) config('services.infinitepay.base_url'), '/'));
    }

    /**
     * Cria o link de checkout do pedido.
     *
     * Os itens vão discriminados (produtos + entrega) quando a soma em centavos
     * bate com o total do pedido; se não bater (desconto dado no ERP, quantidade
     * fracionada), vai UMA linha "Pedido #N" com o total — o que o cliente paga
     * é sempre o total do pedido.
     *
     * @return array{link: string, slug: ?string, payload: array}
     */
    public function criarLink(Pedido $pedido, string $orderNsu): array
    {
        $pedido->loadMissing(['cliente', 'itens']);

        $corpo = array_filter([
            'handle' => $this->handle(),
            'order_nsu' => $orderNsu,
            'items' => $this->itens($pedido),
            'webhook_url' => filled($this->gateway->config['webhook_token'] ?? null) ? $this->webhookUrl() : null,
            'customer' => $this->cliente($pedido),
        ], fn ($v) => $v !== null && $v !== []);
        // ⚠️ O texto da documentação escreve "itens", mas o payload que a própria
        // documentação gera (e os plugins em produção) usa "items" — é o que vale.

        $res = $this->http()->post('/links', $corpo)->throw()->json();

        $link = (string) ($res['url'] ?? $res['payment_url'] ?? $res['link'] ?? '');
        if ($link === '') {
            throw new \RuntimeException('A InfinitePay não devolveu o link de pagamento.');
        }

        Log::channel('integracao')->info('InfinitePay: link criado', [
            'empresa_id' => $pedido->empresa_id,
            'pedido_id' => $pedido->id,
            'order_nsu' => $orderNsu,
        ]);

        return [
            'link' => $link,
            // Só o que a API devolver; o slug chega de verdade no webhook
            // (invoice_slug) — adivinhar pelo link poderia estragar a consulta.
            'slug' => isset($res['slug']) ? (string) $res['slug'] : (isset($res['invoice_slug']) ? (string) $res['invoice_slug'] : null),
            'payload' => $res,
        ];
    }

    /**
     * Consulta o pagamento. `transaction_nsu`/`slug` são opcionais: vêm do
     * webhook ou do retorno; a checagem periódica manda o que tiver.
     *
     * @return array{paid: bool, amount: ?int, paid_amount: ?int, installments: ?int, capture_method: ?string, raw: array}
     */
    public function consultarPagamento(string $orderNsu, ?string $transactionNsu = null, ?string $slug = null): array
    {
        $res = $this->http()->post('/payment_check', array_filter([
            'handle' => $this->handle(),
            'order_nsu' => $orderNsu,
            'transaction_nsu' => $transactionNsu,
            'slug' => $slug,
        ], fn ($v) => filled($v)))->throw()->json() ?? [];

        return [
            'paid' => ($res['paid'] ?? false) === true,
            'amount' => isset($res['amount']) ? (int) $res['amount'] : null,
            'paid_amount' => isset($res['paid_amount']) ? (int) $res['paid_amount'] : null,
            'installments' => isset($res['installments']) ? (int) $res['installments'] : null,
            'capture_method' => isset($res['capture_method']) ? (string) $res['capture_method'] : null,
            'raw' => $res,
        ];
    }

    /**
     * Botão "Testar" do card: gera um link de R$ 1,00 SEM webhook. Criar link
     * não cobra ninguém — prova que a InfiniteTag existe e a API responde.
     */
    public function testar(): string
    {
        $res = $this->http()->post('/links', [
            'handle' => $this->handle(),
            'order_nsu' => 'teste-' . $this->gateway->empresa_id . '-' . now()->format('YmdHis'),
            'items' => [['quantity' => 1, 'price' => 100, 'description' => 'Teste de conexão IA365 (não pagar)']],
        ])->throw()->json();

        $link = (string) ($res['url'] ?? $res['payment_url'] ?? $res['link'] ?? '');
        if ($link === '') {
            throw new \RuntimeException('A InfinitePay respondeu, mas sem link — confira a InfiniteTag.');
        }

        return $link;
    }

    /** @return array<int, array{quantity: int, price: int, description: string}> */
    private function itens(Pedido $pedido): array
    {
        $totalCentavos = (int) round((float) $pedido->total * 100);

        $linhas = [];
        $soma = 0;
        $discriminavel = true;
        foreach ($pedido->itens as $item) {
            /** @var PedidoItem $item */
            $qtd = (float) $item->quantidade;
            if ($qtd <= 0 || floor($qtd) != $qtd) {
                $discriminavel = false;
                break;
            }
            $preco = (int) round((float) $item->preco_unitario * 100);
            $linhas[] = [
                'quantity' => (int) $qtd,
                'price' => $preco,
                'description' => mb_substr((string) $item->descricao, 0, 100),
            ];
            $soma += (int) $qtd * $preco;
        }

        $frete = (int) round((float) ($pedido->frete_valor ?? 0) * 100);
        if ($discriminavel && $frete > 0) {
            $linhas[] = ['quantity' => 1, 'price' => $frete, 'description' => 'Entrega'];
            $soma += $frete;
        }

        if ($discriminavel && $linhas !== [] && $soma === $totalCentavos) {
            return $linhas;
        }

        return [[
            'quantity' => 1,
            'price' => $totalCentavos,
            'description' => mb_substr("Pedido #{$pedido->numero}", 0, 100),
        ]];
    }

    /** @return array<string, string>|null */
    private function cliente(Pedido $pedido): ?array
    {
        $cliente = $pedido->cliente;
        if (! $cliente) {
            return null;
        }

        $fone = preg_replace('/\D/', '', (string) ($cliente->whatsapp ?: $cliente->telefone));
        if (strlen($fone) === 10 || strlen($fone) === 11) {
            $fone = '55' . $fone;
        }

        $dados = array_filter([
            'name' => mb_substr((string) $cliente->nome_razao_social, 0, 100),
            'email' => filter_var($cliente->email, FILTER_VALIDATE_EMAIL) ? $cliente->email : null,
            'phone_number' => strlen($fone) >= 12 ? '+' . $fone : null,
        ], fn ($v) => filled($v));

        return $dados ?: null;
    }
}
