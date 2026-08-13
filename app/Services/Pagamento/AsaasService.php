<?php

namespace App\Services\Pagamento;

use App\Models\EmpresaGateway;
use App\Models\Pedido;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asaas por EMPRESA (Fase 2, 13/08/2026) — cartão de crédito via LINK de
 * pagamento (invoiceUrl), mesmo desenho validado no China Mix/JL. A api_key
 * vive CIFRADA em empresa_gateways.client_secret (provedor 'asaas');
 * config: sandbox (bool), webhook_token (valida o asaas-access-token do
 * webhook). ⚠️ Chave Asaas começa com '$' — aqui vai pro BANCO cifrada, sem
 * a armadilha de interpolação do compose (bug histórico do .env).
 */
class AsaasService
{
    public function __construct(private readonly EmpresaGateway $gateway)
    {
    }

    public static function ativoPara(int $empresaId): ?self
    {
        $gw = EmpresaGateway::ativoPara($empresaId, EmpresaGateway::PROVEDOR_ASAAS);

        return ($gw && filled($gw->client_secret)) ? new self($gw) : null;
    }

    public function webhookToken(): string
    {
        return (string) ($this->gateway->config['webhook_token'] ?? '');
    }

    private function baseUrl(): string
    {
        return ! empty($this->gateway->config['sandbox'])
            ? 'https://api-sandbox.asaas.com/v3'
            : 'https://api.asaas.com/v3';
    }

    private function http()
    {
        return Http::withHeaders(['access_token' => $this->gateway->client_secret])
            ->acceptJson()
            ->baseUrl($this->baseUrl());
    }

    /** Botão "Testar conexão" do card. */
    public function testarConexao(): void
    {
        $this->http()->get('/myAccount/status')->throw();
    }

    /** Acha/cria o customer Asaas pelo CPF/CNPJ ou telefone do cliente do pedido. */
    private function customerId(Pedido $pedido): string
    {
        $cliente = $pedido->cliente;
        $doc = preg_replace('/\D/', '', (string) $cliente->cpf_cnpj);

        if ($doc !== '') {
            $achado = $this->http()->get('/customers', ['cpfCnpj' => $doc])->throw()->json();
            if (! empty($achado['data'][0]['id'])) {
                return $achado['data'][0]['id'];
            }
        }

        $novo = $this->http()->post('/customers', array_filter([
            'name' => $cliente->nome_razao_social,
            'cpfCnpj' => $doc ?: null,
            'mobilePhone' => preg_replace('/\D/', '', (string) ($cliente->whatsapp ?: $cliente->telefone)) ?: null,
            'externalReference' => "cliente:{$cliente->id}",
        ]))->throw()->json();

        return (string) $novo['id'];
    }

    /**
     * Link de pagamento no cartão para o pedido (invoiceUrl).
     *
     * @return array{id: string, link: string}
     */
    public function linkCartaoParaPedido(Pedido $pedido): array
    {
        $pedido->loadMissing('cliente');

        $res = $this->http()->post('/payments', [
            'customer' => $this->customerId($pedido),
            'billingType' => 'CREDIT_CARD',
            'value' => (float) $pedido->total,
            'dueDate' => now()->addDays(3)->toDateString(),
            'description' => "Pedido #{$pedido->numero}",
            'externalReference' => "pedido:{$pedido->id}",
        ])->throw()->json();

        Log::channel('integracao')->info('Asaas: link de cartão criado', [
            'pedido_id' => $pedido->id,
            'payment_id' => $res['id'] ?? null,
        ]);

        return [
            'id' => (string) ($res['id'] ?? ''),
            'link' => (string) ($res['invoiceUrl'] ?? ''),
        ];
    }
}
