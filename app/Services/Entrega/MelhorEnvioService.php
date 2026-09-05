<?php

namespace App\Services\Entrega;

use App\Models\EmpresaGateway;
use App\Models\PlataformaConfiguracao;
use App\Models\Produto;
use App\Models\Unidade;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Melhor Envio — frete para OUTRA cidade no Agente IA (05/09/2026).
 *
 * Dois níveis de credencial, de propósito:
 *   - o APLICATIVO "IA365" (client_id/secret) é da plataforma e vive em
 *     plataforma_configuracoes (tela /admin/integracoes) — um só para todos;
 *   - cada EMPRESA autoriza a própria conta do Melhor Envio via OAuth2
 *     (botão "Conectar" no card da aba Integração) e fica com access_token
 *     (30 dias) + refresh_token cifrados em empresa_gateways.
 * A URL de callback é UMA para todos (é do app); o `state` cifrado diz de
 * qual empresa é a autorização que voltou.
 *
 * Contrato da API (docs.melhorenvio.com.br):
 *   POST /oauth/token                    troca do code / refresh
 *   GET  /api/v2/me                      conta autorizada
 *   POST /api/v2/me/shipment/calculate   cotação por produtos (cm/kg)
 * Cabeçalho User-Agent "Nome (e-mail)" é OBRIGATÓRIO em toda chamada.
 */
class MelhorEnvioService
{
    public const URL_PRODUCAO = 'https://melhorenvio.com.br';
    public const URL_SANDBOX = 'https://sandbox.melhorenvio.com.br';

    /** Escopos pedidos na autorização (editável em /admin/integracoes). */
    public const SCOPES_PADRAO = 'shipping-calculate shipping-checkout shipping-generate shipping-print shipping-tracking shipping-cancel cart-read cart-write users-read';

    /** Pacote padrão quando o produto não tem medida (mínimos dos Correios + folga). */
    public const PACOTE_PADRAO = ['altura' => 4, 'largura' => 12, 'comprimento' => 17, 'peso' => 0.3];

    /** Mínimos aceitos pelas transportadoras (cm / kg). */
    private const MIN = ['altura' => 2, 'largura' => 11, 'comprimento' => 16, 'peso' => 0.01];

    /** Renova o access_token quando faltam menos que isto para vencer. */
    private const RENOVAR_ANTES_DE_DIAS = 3;

    public function __construct(private readonly EmpresaGateway $gateway)
    {
    }

    // ------------------------------------------------------- app da plataforma

    /** @return array{client_id: ?string, client_secret: ?string, email: ?string, ambiente: string, scopes: string, configurado: bool} */
    public static function app(): array
    {
        $clientId = PlataformaConfiguracao::get('melhor_envio_client_id');
        $secret = PlataformaConfiguracao::get('melhor_envio_client_secret');
        $email = PlataformaConfiguracao::get('melhor_envio_email_suporte', 'dcanteli@ia365.com.br');

        return [
            'client_id' => $clientId,
            'client_secret' => $secret,
            'email' => $email,
            'ambiente' => PlataformaConfiguracao::get('melhor_envio_ambiente', 'producao'),
            'scopes' => PlataformaConfiguracao::get('melhor_envio_scopes', self::SCOPES_PADRAO),
            'configurado' => filled($clientId) && filled($secret),
        ];
    }

    public static function appConfigurado(): bool
    {
        return self::app()['configurado'];
    }

    public static function baseUrl(): string
    {
        // Override só para QA (fake local) — nunca vai para o .env de produção.
        $override = (string) (config('services.melhor_envio.base_url') ?? '');
        if ($override !== '') {
            return rtrim($override, '/');
        }

        return self::app()['ambiente'] === 'sandbox' ? self::URL_SANDBOX : self::URL_PRODUCAO;
    }

    public static function callbackUrl(): string
    {
        return route('integracao.melhor-envio.callback');
    }

    /** URL de autorização para a EMPRESA autorizar a conta dela. */
    public static function urlAutorizacao(int $empresaId): string
    {
        $app = self::app();

        return self::baseUrl() . '/oauth/authorize?' . http_build_query([
            'client_id' => $app['client_id'],
            'redirect_uri' => self::callbackUrl(),
            'response_type' => 'code',
            'scope' => $app['scopes'],
            'state' => self::gerarState($empresaId),
        ]);
    }

    /** `state` cifrado: empresa + validade de 15 min. Volta intacto no callback. */
    public static function gerarState(int $empresaId): string
    {
        return Crypt::encryptString(json_encode([
            'empresa_id' => $empresaId,
            'exp' => time() + 900,
            'n' => bin2hex(random_bytes(6)),
        ]));
    }

    /** @return int empresa_id, ou lança InvalidArgumentException */
    public static function lerState(string $state): int
    {
        try {
            $dados = json_decode(Crypt::decryptString($state), true);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Estado de autorização inválido.');
        }
        if (! is_array($dados) || empty($dados['empresa_id']) || ($dados['exp'] ?? 0) < time()) {
            throw new \InvalidArgumentException('Autorização expirada — clique em Conectar de novo.');
        }

        return (int) $dados['empresa_id'];
    }

    /**
     * Troca o `code` do callback por tokens e grava no gateway da empresa.
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public static function trocarCodigo(string $code): array
    {
        $app = self::app();
        $res = self::http()->post(self::baseUrl() . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $app['client_id'],
            'client_secret' => $app['client_secret'],
            'redirect_uri' => self::callbackUrl(),
            'code' => $code,
        ])->throw()->json();

        return self::tokensDaResposta($res);
    }

    private static function tokensDaResposta(array $res): array
    {
        if (empty($res['access_token'])) {
            throw new \RuntimeException('Melhor Envio não devolveu access_token.');
        }

        return [
            'access_token' => (string) $res['access_token'],
            'refresh_token' => (string) ($res['refresh_token'] ?? ''),
            'expires_in' => (int) ($res['expires_in'] ?? 2592000),
        ];
    }

    private static function http(?string $token = null): PendingRequest
    {
        $app = self::app();
        $req = Http::acceptJson()
            ->asJson()
            ->withHeaders(['User-Agent' => 'IA365 ERP (' . ($app['email'] ?: 'suporte@ia365.com.br') . ')'])
            ->timeout(20);

        return $token ? $req->withToken($token) : $req;
    }

    // ------------------------------------------------------------ por empresa

    public static function ativoPara(int $empresaId): ?self
    {
        if (! self::appConfigurado()) {
            return null;
        }
        $gw = EmpresaGateway::ativoPara($empresaId, EmpresaGateway::PROVEDOR_MELHOR_ENVIO);

        return ($gw && filled($gw->access_token)) ? new self($gw) : null;
    }

    public static function paraGateway(EmpresaGateway $gw): self
    {
        return new self($gw);
    }

    public function gateway(): EmpresaGateway
    {
        return $this->gateway;
    }

    public function conectado(): bool
    {
        return filled($this->gateway->access_token);
    }

    /** Grava tokens novos no gateway (troca do code ou refresh). */
    public function gravarTokens(array $tokens): void
    {
        $this->gateway->access_token = $tokens['access_token'];
        if (filled($tokens['refresh_token'] ?? null)) {
            $this->gateway->refresh_token = $tokens['refresh_token'];
        }
        $this->gateway->token_expira_em = now()->addSeconds(max(3600, (int) $tokens['expires_in']));
        $this->gateway->ultima_falha = null;
        $this->gateway->save();
    }

    /** Renova pelo refresh_token (o cron chama todo dia; o token() chama sob demanda). */
    public function renovarToken(): void
    {
        if (blank($this->gateway->refresh_token)) {
            throw new \RuntimeException('Sem refresh_token — reconectar a conta do Melhor Envio.');
        }
        $app = self::app();
        $res = self::http()->post(self::baseUrl() . '/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $app['client_id'],
            'client_secret' => $app['client_secret'],
            'refresh_token' => $this->gateway->refresh_token,
        ])->throw()->json();

        $this->gravarTokens(self::tokensDaResposta($res));
    }

    public function precisaRenovar(): bool
    {
        $exp = $this->gateway->token_expira_em;

        return $exp === null || $exp->lte(now()->addDays(self::RENOVAR_ANTES_DE_DIAS));
    }

    private function token(): string
    {
        if ($this->precisaRenovar() && filled($this->gateway->refresh_token)) {
            try {
                $this->renovarToken();
            } catch (\Throwable $e) {
                Log::channel('integracao')->warning('Melhor Envio: falha ao renovar token', [
                    'empresa_id' => $this->gateway->empresa_id, 'erro' => $e->getMessage(),
                ]);
                // segue com o token atual: se ainda vale, a chamada passa
            }
        }

        return (string) $this->gateway->access_token;
    }

    /** @return array{nome: string, email: string} */
    public function me(): array
    {
        $res = self::http($this->token())->get(self::baseUrl() . '/api/v2/me')->throw()->json();

        return [
            'nome' => trim(($res['firstname'] ?? '') . ' ' . ($res['lastname'] ?? '')) ?: (string) ($res['email'] ?? ''),
            'email' => (string) ($res['email'] ?? ''),
        ];
    }

    /** Testa a conexão e atualiza nome/e-mail da conta no config. */
    public function testarConexao(): array
    {
        $me = $this->me();
        $config = $this->gateway->config ?? [];
        $config['conta_nome'] = $me['nome'];
        $config['conta_email'] = $me['email'];
        $this->gateway->config = $config;
        $this->gateway->ultima_falha = null;
        $this->gateway->save();

        return $me;
    }

    // --------------------------------------------------------------- cotação

    /** Pacote padrão da loja (config do gateway) com fallback nos valores fixos. */
    public function pacotePadrao(): array
    {
        $c = $this->gateway->config ?? [];

        return [
            'altura' => (float) ($c['pacote_altura'] ?? self::PACOTE_PADRAO['altura']),
            'largura' => (float) ($c['pacote_largura'] ?? self::PACOTE_PADRAO['largura']),
            'comprimento' => (float) ($c['pacote_comprimento'] ?? self::PACOTE_PADRAO['comprimento']),
            'peso' => (float) ($c['pacote_peso'] ?? self::PACOTE_PADRAO['peso']),
        ];
    }

    /**
     * Medidas do produto para a API (cm inteiros / kg), caindo no pacote
     * padrão campo a campo e respeitando os mínimos das transportadoras.
     * @return array{width: int, height: int, length: int, weight: float}
     */
    public function pacoteDoProduto(?Produto $p): array
    {
        $padrao = $this->pacotePadrao();
        // ⚠️ casts decimal devolvem STRING ("0.000" é truthy): converter antes de
        // decidir, senão produto com peso zero viraria 0,01 kg em vez do padrão.
        $ou = fn (mixed $valor, float $fallback): float => ((float) ($valor ?? 0)) > 0 ? (float) $valor : $fallback;
        $altura = $ou($p?->altura_cm, $padrao['altura']);
        $largura = $ou($p?->largura_cm, $padrao['largura']);
        $comprimento = $ou($p?->comprimento_cm, $padrao['comprimento']);
        $peso = $ou($p?->peso_bruto, $ou($p?->peso_liquido, $padrao['peso']));

        return [
            'width' => (int) ceil(max($largura, self::MIN['largura'])),
            'height' => (int) ceil(max($altura, self::MIN['altura'])),
            'length' => (int) ceil(max($comprimento, self::MIN['comprimento'])),
            'weight' => round(max($peso, self::MIN['peso']), 3),
        ];
    }

    /**
     * Cota o frete da loja (CEP da unidade) até o CEP do cliente.
     *
     * @param  array<int, array{produto: ?Produto, quantidade: float, valor_unitario: float}>  $itens
     * @return array<int, array{servico_id: string, nome: string, transportadora: string, valor: float, prazo_dias: int}>
     *         ordenado do mais barato para o mais caro; vazio = nenhum serviço atende
     */
    public function cotar(Unidade $unidade, string $cepDestino, array $itens, ?string $apenasServico = null): array
    {
        $origem = preg_replace('/\D/', '', (string) $unidade->cep);
        $destino = preg_replace('/\D/', '', $cepDestino);
        if (strlen($origem) !== 8) {
            throw new \RuntimeException('A loja não tem CEP cadastrado — preencha em Lojas para cotar o frete.');
        }
        if (strlen($destino) !== 8) {
            throw new \InvalidArgumentException('CEP de destino inválido.');
        }

        $seguro = (bool) ($this->gateway->config['seguro'] ?? true);
        $products = [];
        foreach ($itens as $i => $item) {
            $p = $item['produto'] ?? null;
            $pacote = $this->pacoteDoProduto($p);
            $products[] = [
                'id' => (string) ($p?->id ?? ('item' . ($i + 1))),
                'width' => $pacote['width'],
                'height' => $pacote['height'],
                'length' => $pacote['length'],
                'weight' => $pacote['weight'],
                'insurance_value' => $seguro ? round((float) ($item['valor_unitario'] ?? 0), 2) : 0,
                'quantity' => max(1, (int) ceil((float) ($item['quantidade'] ?? 1))),
            ];
        }
        if ($products === []) {
            $pacote = $this->pacoteDoProduto(null);
            $products[] = ['id' => 'pacote', 'insurance_value' => 0, 'quantity' => 1] + $pacote;
        }

        $servicos = trim((string) ($this->gateway->config['servicos'] ?? ''));
        $payload = [
            'from' => ['postal_code' => $origem],
            'to' => ['postal_code' => $destino],
            'products' => $products,
            'options' => ['receipt' => false, 'own_hand' => false],
        ];
        if ($apenasServico) {
            $payload['services'] = (string) $apenasServico;
        } elseif ($servicos !== '') {
            $payload['services'] = $servicos;
        }

        $res = self::http($this->token())
            ->post(self::baseUrl() . '/api/v2/me/shipment/calculate', $payload)
            ->throw()
            ->json();

        $opcoes = [];
        foreach ((array) $res as $op) {
            if (! is_array($op) || ! empty($op['error']) || ! isset($op['id'])) {
                continue;
            }
            $valor = (float) ($op['custom_price'] ?? $op['price'] ?? 0);
            if ($valor <= 0) {
                continue;
            }
            $opcoes[] = [
                'servico_id' => (string) $op['id'],
                'nome' => (string) ($op['name'] ?? ''),
                'transportadora' => (string) ($op['company']['name'] ?? ''),
                'valor' => round($valor, 2),
                'prazo_dias' => (int) ($op['custom_delivery_time'] ?? $op['delivery_time'] ?? 0),
            ];
        }
        usort($opcoes, fn ($a, $b) => $a['valor'] <=> $b['valor']);

        return $opcoes;
    }

    // ------------------------------------------------------------ utilidades

    /**
     * O CEP é da mesma cidade da loja? (decide Uber local × Melhor Envio quando
     * a Uber não tem faixas de CEP cadastradas). ViaCEP com cache de 30 dias;
     * se a consulta falhar, devolve TRUE (mantém o comportamento antigo: Uber).
     */
    public static function mesmaCidade(string $cep, Unidade $unidade): bool
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            return true;
        }
        $dados = Cache::remember("viacep_{$cep}", 60 * 60 * 24 * 30, function () use ($cep) {
            try {
                $r = Http::timeout(6)->get("https://viacep.com.br/ws/{$cep}/json/")->json();

                return (is_array($r) && empty($r['erro'])) ? ['cidade' => $r['localidade'] ?? '', 'uf' => $r['uf'] ?? ''] : null;
            } catch (\Throwable) {
                return null;
            }
        });
        if (! $dados || $dados['cidade'] === '') {
            return true;
        }

        return self::normalizar($dados['cidade']) === self::normalizar((string) $unidade->cidade)
            && mb_strtoupper(trim($dados['uf'])) === mb_strtoupper(trim((string) $unidade->uf));
    }

    private static function normalizar(string $s): string
    {
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;

        return mb_strtoupper(preg_replace('/[^a-z0-9]/i', '', $s));
    }
}
