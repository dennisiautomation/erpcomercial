<?php

namespace App\Services\Entrega;

use App\Models\Cliente;
use App\Models\EmpresaGateway;
use App\Models\Pedido;
use App\Models\Unidade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Uber Direct por EMPRESA — porte da integração validada no China Mix
 * (bling-catalogo-api/src/services/uberDirectService.ts), com tudo que lá era
 * .env/hardcode virando cadastro no gateway (aba Integração da empresa):
 *
 *   client_id / client_secret  → colunas cifradas do empresa_gateways
 *   customer_id                → config['customer_id']
 *   faixas de CEP atendidas    → config['ceps']  ex.: "64000-64099,65630-65639"
 *   janela seg-sex             → config['hora_inicio'] / config['hora_fim']   (ex.: 8 / 16.5)
 *   janela sábado              → config['hora_inicio_sab'] / config['hora_fim_sab']
 *   fuso                       → config['fuso'] (default America/Sao_Paulo)
 *
 * Pickup = endereço da UNIDADE do pedido (não da empresa). Peso do item vem de
 * produtos.peso_bruto (default 0,3 kg); dimensões usam default 16×10×10 cm
 * (o cadastro de produto do ERP não tem dimensões — igual ao default China Mix).
 */
class UberDirectService
{
    private const AUTH_URL = 'https://auth.uber.com/oauth/v2/token';
    private const API_BASE = 'https://api.uber.com/v1';

    public function __construct(private readonly EmpresaGateway $gateway)
    {
    }

    public static function ativoPara(int $empresaId): ?self
    {
        $gw = EmpresaGateway::ativoPara($empresaId, EmpresaGateway::PROVEDOR_UBER_DIRECT);

        return $gw ? new self($gw) : null;
    }

    // ----------------------------------------------------------------- token

    /** Token OAuth client_credentials (válido ~30 dias) cacheado por gateway. */
    private function token(): string
    {
        $key = "uber_direct_token_gw{$this->gateway->id}";

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $res = Http::asForm()->post(self::AUTH_URL, [
            'client_id' => $this->gateway->client_id,
            'client_secret' => $this->gateway->client_secret,
            'grant_type' => 'client_credentials',
            'scope' => 'eats.deliveries',
        ])->throw()->json();

        $token = (string) $res['access_token'];
        // margem de 1h sobre o expires_in
        Cache::put($key, $token, max(60, (int) ($res['expires_in'] ?? 2592000) - 3600));

        return $token;
    }

    /** Valida credenciais (botão "Testar conexão" do card). */
    public function testarConexao(): void
    {
        Cache::forget("uber_direct_token_gw{$this->gateway->id}");
        $this->token();
    }

    // ------------------------------------------------------------- elegibilidade

    /** Há faixas de CEP cadastradas? (05/09: decide se a Uber é 'local' por faixa ou por cidade) */
    public function temFaixas(): bool
    {
        return trim((string) ($this->gateway->config['ceps'] ?? '')) !== '';
    }

    /** CEP dentro das faixas atendidas? (vazio = qualquer CEP é aceito) */
    public function cepAtendido(?string $cep): bool
    {
        $digits = preg_replace('/\D/', '', (string) $cep);
        if (strlen($digits) < 5) {
            return false;
        }

        $faixas = trim((string) ($this->gateway->config['ceps'] ?? ''));
        if ($faixas === '') {
            return true;
        }

        $prefixo = (int) substr($digits, 0, 5);
        foreach (explode(',', $faixas) as $faixa) {
            $partes = array_map('trim', explode('-', $faixa));
            $ini = (int) substr(preg_replace('/\D/', '', $partes[0] ?? ''), 0, 5);
            $fim = (int) substr(preg_replace('/\D/', '', $partes[1] ?? ($partes[0] ?? '')), 0, 5);
            if ($ini > 0 && $prefixo >= $ini && $prefixo <= $fim) {
                return true;
            }
        }

        return false;
    }

    /** Dentro da janela de operação? (regra China Mix: loja fecha, Uber para antes) */
    public function dentroJanela(): bool
    {
        $cfg = $this->gateway->config ?? [];
        $agora = Carbon::now($cfg['fuso'] ?? 'America/Sao_Paulo');
        $hora = $agora->hour + $agora->minute / 60;

        if ($agora->isSunday()) {
            return false;
        }

        if ($agora->isSaturday()) {
            $ini = (float) ($cfg['hora_inicio_sab'] ?? 9);
            $fim = (float) ($cfg['hora_fim_sab'] ?? 12);
        } else {
            $ini = (float) ($cfg['hora_inicio'] ?? 8);
            $fim = (float) ($cfg['hora_fim'] ?? 16.5);
        }

        return $hora >= $ini && $hora < $fim;
    }

    // ------------------------------------------------------------------ endereços

    public static function enderecoUnidade(Unidade $u): string
    {
        $rua = trim(($u->logradouro ?? '') . ' ' . ($u->numero ?? ''));

        return trim(sprintf(
            '%s, %s, %s, %s, BR',
            $rua !== '' ? $rua : ($u->bairro ?? ''),
            $u->cidade,
            $u->uf,
            preg_replace('/\D/', '', (string) $u->cep)
        ), ', ');
    }

    public static function enderecoCliente(Cliente $c): ?string
    {
        if (blank($c->cidade) || blank($c->uf)) {
            return null;
        }
        $rua = trim(($c->logradouro ?? '') . ' ' . ($c->numero ?? ''));

        return trim(sprintf(
            '%s, %s, %s, %s, BR',
            $rua !== '' ? $rua : ($c->bairro ?? ''),
            $c->cidade,
            $c->uf,
            preg_replace('/\D/', '', (string) $c->cep)
        ), ', ');
    }

    // ---------------------------------------------------------------- operações

    /** @return array{id: string, fee: int, duration: int, expires: string} */
    public function cotar(Unidade $unidade, string $enderecoDropoff): array
    {
        $customerId = $this->gateway->config['customer_id'] ?? '';

        $res = Http::withToken($this->token())
            ->post(self::API_BASE . "/customers/{$customerId}/delivery_quotes", [
                'pickup_address' => self::enderecoUnidade($unidade),
                'dropoff_address' => $enderecoDropoff,
            ])->throw()->json();

        return [
            'id' => (string) $res['id'],
            'fee' => (int) ($res['fee'] ?? 0),           // centavos
            'duration' => (int) ($res['duration'] ?? 0), // minutos
            'expires' => (string) ($res['expires'] ?? ''),
        ];
    }

    /** Peso (kg) → size do manifesto Uber (igual China Mix). */
    private static function size(float $pesoKg): string
    {
        return match (true) {
            $pesoKg <= 0.3 => 'small',
            $pesoKg <= 1.0 => 'medium',
            $pesoKg <= 5.0 => 'large',
            default => 'xlarge',
        };
    }

    private static function e164(?string $tel): string
    {
        $limpo = preg_replace('/\D/', '', (string) $tel);

        return (str_starts_with($limpo, '55') && strlen($limpo) >= 12) ? "+{$limpo}" : "+55{$limpo}";
    }

    /**
     * @return array{delivery_id: string, status: string, tracking_url: string, fee: int, courier: ?array}
     */
    public function criarEntrega(Pedido $pedido, string $quoteId, string $enderecoDropoff): array
    {
        $customerId = $this->gateway->config['customer_id'] ?? '';
        $pedido->loadMissing(['itens.produto', 'cliente', 'unidade']);

        $manifest = $pedido->itens->map(function ($item) {
            $peso = (float) ($item->produto->peso_bruto ?? 0) ?: 0.3;
            $qty = max(1, (int) round((float) $item->quantidade));

            return [
                'name' => $item->descricao,
                'quantity' => $qty,
                'size' => self::size($peso * $qty),
                'price' => (int) round(((float) $item->preco_unitario) * 100),
                'weight' => (int) round($peso * 1000), // gramas
                'dimensions' => ['length' => 16, 'height' => 10, 'depth' => 10],
                'must_be_upright' => false,
            ];
        })->values()->all();

        $res = Http::withToken($this->token())
            ->post(self::API_BASE . "/customers/{$customerId}/deliveries", [
                'quote_id' => $quoteId,
                'pickup_name' => $pedido->unidade->nome,
                'pickup_address' => self::enderecoUnidade($pedido->unidade),
                'pickup_phone_number' => self::e164($pedido->unidade->telefone),
                'dropoff_name' => $pedido->cliente->nome_razao_social,
                'dropoff_address' => $enderecoDropoff,
                'dropoff_phone_number' => self::e164($pedido->cliente->whatsapp ?: $pedido->cliente->telefone),
                'dropoff_notes' => "Pedido #{$pedido->numero}",
                'manifest_items' => $manifest,
            ])->throw()->json();

        return [
            'delivery_id' => (string) $res['id'],
            'status' => (string) ($res['status'] ?? ''),
            'tracking_url' => (string) ($res['tracking_url'] ?? ''),
            'fee' => (int) ($res['fee'] ?? 0),
            'courier' => $res['courier'] ?? null,
        ];
    }

    public function consultar(string $deliveryId): array
    {
        $customerId = $this->gateway->config['customer_id'] ?? '';

        return Http::withToken($this->token())
            ->get(self::API_BASE . "/customers/{$customerId}/deliveries/{$deliveryId}")
            ->throw()->json();
    }
}
