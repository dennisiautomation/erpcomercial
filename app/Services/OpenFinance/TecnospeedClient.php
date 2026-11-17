<?php

namespace App\Services\OpenFinance;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * API de Extrato Open Finance da Tecnospeed (PlugBank) — 17/11/2026.
 *
 * Uma chave só (OPERATIONAL, escopo OPENFINANCE) da IA365: todas as contas de todas as
 * empresas ficam sob ela, e quem separa uma empresa da outra é o ERP (contas_bancarias).
 * A chave vira um Access Token de ~15 min, guardado em cache.
 */
class TecnospeedClient
{
    public function configurado(): bool
    {
        return config('services.tecnospeed.client_id') !== '' && config('services.tecnospeed.client_secret') !== '';
    }

    /** Cadastra a conta e devolve os dados dela (accountHash, openfinanceLink, statusOpenfinance). */
    public function criarConta(array $conta): array
    {
        $r = $this->http()->post('/api/v1/account', [array_filter([
            'bankCode' => $conta['banco_codigo'],
            'agency' => $conta['agencia'],
            'agencyDigit' => $conta['agencia_digito'] ?? null,
            'accountNumber' => $conta['conta'],
            'accountNumberDigit' => $conta['conta_digito'] ?? null,
            'statementActived' => true,
        ], fn ($v) => $v !== null && $v !== '')]);

        $this->exigir($r, 'cadastrar a conta no banco');

        return (array) ($r->json('accounts.0') ?? []);
    }

    public function consultarConta(string $hash): array
    {
        $r = $this->http()->get('/api/v1/account/' . $hash);
        $this->exigir($r, 'consultar a conta');
        $dados = (array) $r->json();

        return isset($dados['accounts']) && is_array($dados['accounts']) ? $dados['accounts'] : $dados;
    }

    /** Tira a autorização no banco e apaga o cadastro, para a conta sair da fatura da Tecnospeed. */
    public function removerConta(string $hash): void
    {
        // A revogação pode falhar quando a conta nunca chegou a conectar — segue para apagar.
        $this->http()->put('/api/v1/account/' . $hash . '/openfinance/revoke', ['revokeAndDisable' => true]);

        $r = $this->http()->send('DELETE', '/api/v1/account', ['json' => ['accountHash' => [$hash]]]);
        if ($r->status() !== 404) {
            $this->exigir($r, 'remover a conta');
        }
    }

    /** Pede o extrato do período; devolve o protocolo (uniqueId) para consultar depois. */
    public function pedirExtrato(string $hash, string $inicio, string $fim, ?string $cartaoFinal = null): string
    {
        $r = $this->http()->post('/api/v1/statement/openfinance', [
            'accountHash' => $hash,
            'dateStart' => $inicio,
            'dateEnd' => $fim,
        ] + ($cartaoFinal !== null
            ? ['statementType' => 'CREDIT_CARD', 'cardNumber' => $cartaoFinal]
            : ['statementType' => 'BANK']));
        $this->exigir($r, 'pedir o extrato');

        return (string) $r->json('uniqueId');
    }

    /** Cartões de crédito da conta: [['cardNumber' => '5817', 'additionalCards' => '3210,1646', 'creditLimit' => ..., 'availableCreditLimit' => ...]]. */
    public function listarCartoes(string $hash): array
    {
        $r = $this->http()->get('/api/v1/statement/credit-card/openfinance', ['accountHash' => $hash]);
        if ($r->status() === 404) {
            return [];   // "Nenhum cartão de crédito encontrado para esta conta"
        }
        $this->exigir($r, 'buscar os cartões');

        return array_values(array_filter((array) $r->json(), 'is_array'));
    }

    /** Protocolo completo: ['statement' => [...], 'transaction' => ['credit' => [], 'debit' => []], ...]. */
    public function consultarExtrato(string $protocolo): array
    {
        $r = $this->http()->get('/api/v1/statement/openfinance/' . $protocolo);
        $this->exigir($r, 'consultar o extrato');

        return (array) $r->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.tecnospeed.base_url'))
            ->withToken($this->token())
            ->withHeaders(['User-Agent' => 'ERP-Comercial-IA365'])
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    private function token(): string
    {
        if (! $this->configurado()) {
            throw new OpenFinanceException('A ligação com o banco ainda não foi configurada pela IA365.');
        }

        $chave = 'tecnospeed:token:' . md5(config('services.tecnospeed.base_url') . config('services.tecnospeed.client_id'));

        $token = Cache::get($chave);
        if ($token) {
            return $token;
        }

        $r = Http::baseUrl(config('services.tecnospeed.base_url'))
            ->withBasicAuth(config('services.tecnospeed.client_id'), config('services.tecnospeed.client_secret'))
            ->asForm()
            ->timeout(20)
            ->post('/oauth/token', ['grant_type' => 'client_credentials']);

        if (! $r->successful() || ! $r->json('access_token')) {
            throw new OpenFinanceException('Não foi possível entrar na Tecnospeed: ' . ($r->json('message') ?: 'erro ' . $r->status()), $r->status());
        }

        Cache::put($chave, $r->json('access_token'), max(60, (int) $r->json('expires_in', 900) - 60));

        return $r->json('access_token');
    }

    private function exigir(Response $r, string $acao): void
    {
        if ($r->successful()) {
            return;
        }

        $msg = $r->json('errors.0.message') ?: $r->json('message') ?: 'erro ' . $r->status();

        throw new OpenFinanceException("Não foi possível {$acao}: {$msg}", $r->status());
    }
}
