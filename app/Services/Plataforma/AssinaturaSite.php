<?php

namespace App\Services\Plataforma;

use App\Models\Empresa;
use App\Models\PlataformaConfiguracao;
use App\Models\PlataformaFatura;
use App\Models\User;
use App\Services\Pagamento\InfinitePayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Assinatura feita pela própria empresa, dentro do sistema (20/11/2026).
 *
 * ONDE CADA COISA VIVE: a cobrança (cliente, mensalidade, PIX/boleto do Inter e a
 * recorrência) nasce e é baixada no caixa.ia365. O ERP guarda o teste, a tela
 * "Assinar agora", o espelho da fatura, a faixa e o bloqueio.
 *
 *  - PIX e boleto: o ERP pede ao caixa.ia365 a 1ª mensalidade; quando o banco confirma,
 *    o caixa transforma em recorrência mensal e avisa aqui (a empresa deixa de ser teste).
 *  - Cartão: a InfinitePay só tem link de pagamento avulso (sem débito automático). Cada
 *    fatura ganha um link; pago, o ERP confere em `payment_check`, dá a baixa aqui e pede
 *    a baixa no caixa.ia365 (que cancela o PIX/boleto da mesma fatura).
 *  - Bloqueio: teste vencido sem assinar bloqueia; fatura vencida há mais de X dias bloqueia
 *    (X = "dias de tolerância" em Admin → Planos). Pagou, libera sozinho.
 *
 * Só vale para empresa de origem 'site'. As criadas pelo admin não mudam em nada.
 */
class AssinaturaSite
{
    public const FORMAS = ['pix', 'boleto', 'cartao'];

    public const CHAVE_TOLERANCIA = 'site_assinatura_tolerancia_dias';
    public const CHAVE_HANDLE = 'site_assinatura_infinitepay_handle';
    public const CHAVE_WEBHOOK = 'site_assinatura_infinitepay_webhook_token';

    public const TOLERANCIA_PADRAO = 5;

    /* ------------------------------------------------------------------ */
    /*  Ajustes (Admin → Planos)                                           */
    /* ------------------------------------------------------------------ */

    private static function cfg(string $chave, ?string $padrao = null): ?string
    {
        try {
            return PlataformaConfiguracao::get($chave, $padrao);
        } catch (\Throwable) {
            return $padrao;
        }
    }

    public static function toleranciaDias(): int
    {
        return max(0, min(60, (int) self::cfg(self::CHAVE_TOLERANCIA, (string) self::TOLERANCIA_PADRAO)));
    }

    public static function definirTolerancia(int $dias): void
    {
        PlataformaConfiguracao::set(self::CHAVE_TOLERANCIA, (string) max(0, min(60, $dias)));
    }

    /** InfiniteTag da IA365 (sem '$'). Vazia = cartão desligado. */
    public static function handle(): string
    {
        return InfinitePayService::normalizarHandle(self::cfg(self::CHAVE_HANDLE, ''));
    }

    public static function definirHandle(?string $handle): void
    {
        $h = InfinitePayService::normalizarHandle($handle);
        PlataformaConfiguracao::set(self::CHAVE_HANDLE, $h !== '' ? $h : null);
    }

    public static function webhookToken(): string
    {
        $t = (string) self::cfg(self::CHAVE_WEBHOOK, '');
        if ($t === '') {
            $t = Str::random(40);
            PlataformaConfiguracao::set(self::CHAVE_WEBHOOK, $t);
        }

        return $t;
    }

    /** O ERP sabe chamar o caixa.ia365 (endereço e segredo configurados)? */
    public static function caixaConfigurado(): bool
    {
        return (string) config('services.caixa_ia365.url') !== '' && (string) config('services.caixa_ia365.token') !== '';
    }

    public static function cartaoDisponivel(): bool
    {
        return self::handle() !== '';
    }

    /* ------------------------------------------------------------------ */
    /*  Situação da empresa                                                */
    /* ------------------------------------------------------------------ */

    /** A tela "Assinar agora" vale para esta empresa? */
    public static function vale(?Empresa $empresa): bool
    {
        return $empresa !== null && $empresa->veioDoSite();
    }

    /** Ainda pode pedir a assinatura (não pediu, ou pediu e a 1ª fatura não chegou a nascer)? */
    public static function podeAssinar(Empresa $empresa): bool
    {
        return self::vale($empresa) && $empresa->assinatura_ativada_em === null && ! self::primeiraFatura($empresa);
    }

    public static function primeiraFatura(Empresa $empresa): ?PlataformaFatura
    {
        return PlataformaFatura::where('empresa_id', $empresa->id)->pendentes()->orderBy('vencimento')->first();
    }

    public static function valorMensal(Empresa $empresa): float
    {
        return (float) ($empresa->getPlanoAtivo()?->preco_mensal ?? 0);
    }

    /** Vencimento da 1ª mensalidade: o último dia do teste; teste já vencido = hoje. */
    public static function primeiroVencimento(Empresa $empresa): Carbon
    {
        $hoje = Carbon::today();

        return $empresa->trial_fim && $empresa->trial_fim->gt($hoje) ? $empresa->trial_fim->copy() : $hoje;
    }

    /* ------------------------------------------------------------------ */
    /*  Conversa com o caixa.ia365                                         */
    /* ------------------------------------------------------------------ */

    /**
     * O que a EMPRESA lê quando o caixa recusa. O motivo do caixa é escrito para a IA365
     * ("Ajustes → Banco (Inter)…"): vai para o log e para `detalhe`, não para a tela do cliente.
     * A exceção é o cadastro incompleto para o boleto, que só a empresa resolve.
     */
    public static function mensagemParaAEmpresa(int $status, string $motivo): string
    {
        if ($status === 422 && preg_match('/está sem: (.+)$/u', $motivo, $m)) {
            return 'Para gerar o boleto falta completar o cadastro da empresa: ' . $m[1] . '. Complete em Gestão → Empresa ou escolha o PIX.';
        }
        if ($status === 409) {
            return 'Esta cobrança já foi paga ou cancelada. Atualize a página.';
        }
        if ($status === 404) {
            return 'Esta cobrança não foi encontrada no financeiro. Fale com a IA365.';
        }

        return 'Não foi possível gerar a cobrança agora. Tente de novo em alguns minutos ou fale com a IA365.';
    }

    /** @return array{ok: bool, dados: array, erro: ?string, detalhe?: ?string} */
    private static function caixa(string $caminho, array $corpo): array
    {
        if (! self::caixaConfigurado()) {
            return ['ok' => false, 'dados' => [], 'erro' => 'A cobrança ainda não está ligada. Fale com a IA365.'];
        }
        try {
            $res = Http::withToken((string) config('services.caixa_ia365.token'))
                ->acceptJson()->asJson()->timeout(40)
                ->post(rtrim((string) config('services.caixa_ia365.url'), '/') . '/api/erp/maquina/' . $caminho, $corpo);
        } catch (\Throwable $e) {
            Log::warning('[AssinaturaSite] caixa.ia365 fora do alcance', ['caminho' => $caminho, 'erro' => $e->getMessage()]);

            return ['ok' => false, 'dados' => [], 'erro' => 'Não conseguimos falar com o financeiro agora. Tente de novo em alguns minutos.'];
        }
        $json = (array) ($res->json() ?? []);
        if (! $res->successful()) {
            Log::warning('[AssinaturaSite] caixa.ia365 recusou', ['caminho' => $caminho, 'status' => $res->status(), 'resposta' => mb_substr($res->body(), 0, 300)]);

            $motivo = (string) ($json['message'] ?? '');

            return ['ok' => false, 'dados' => $json, 'erro' => self::mensagemParaAEmpresa($res->status(), $motivo), 'detalhe' => $motivo];
        }

        return ['ok' => true, 'dados' => $json, 'erro' => null];
    }

    /** Espelha na fatura o estado que o caixa devolveu (mesmo formato do que ele manda sozinho). */
    private static function espelhar(Empresa $empresa, array $estado): ?PlataformaFatura
    {
        if (empty($estado['ref']) || empty($estado['situacao'])) {
            return null;
        }
        $r = FaturasPlataforma::receberDoCaixa($empresa, $estado);

        return $r['fatura_id'] ? PlataformaFatura::find($r['fatura_id']) : null;
    }

    /**
     * "Assinar agora": pede a 1ª mensalidade ao caixa.ia365 e liga o bloqueio automático.
     * Cartão também nasce com PIX, para a empresa ter como pagar se o cartão não passar.
     *
     * @return array{ok: bool, fatura: ?PlataformaFatura, erro: ?string}
     */
    public static function assinar(Empresa $empresa, string $forma, ?User $por = null): array
    {
        if (! in_array($forma, self::FORMAS, true)) {
            return ['ok' => false, 'fatura' => null, 'erro' => 'Escolha PIX, boleto ou cartão.'];
        }
        if (! self::vale($empresa) || $empresa->assinatura_ativada_em) {
            return ['ok' => false, 'fatura' => null, 'erro' => 'Esta empresa já tem assinatura.'];
        }
        if ($forma === 'cartao' && ! self::cartaoDisponivel()) {
            return ['ok' => false, 'fatura' => null, 'erro' => 'O pagamento por cartão não está disponível agora. Escolha PIX ou boleto.'];
        }
        $valor = self::valorMensal($empresa);
        if ($valor <= 0) {
            return ['ok' => false, 'fatura' => null, 'erro' => 'O plano desta empresa está sem preço. Fale com a IA365.'];
        }

        $plano = $empresa->getPlanoAtivo();
        $dono = $por ?: FaturasPlataforma::destinatarios($empresa)->first();
        $r = self::caixa('assinatura', [
            'empresa_id' => $empresa->id,
            'razao_social' => $empresa->razao_social,
            'nome_fantasia' => $empresa->nome_fantasia,
            'cnpj' => preg_replace('/[^A-Za-z0-9]/', '', (string) $empresa->cnpj),
            'email' => $empresa->email,
            'telefone' => preg_replace('/\D/', '', (string) $empresa->telefone) ?: null,
            'contato' => $dono?->name,
            'cep' => $empresa->cep,
            'logradouro' => $empresa->logradouro,
            'numero' => $empresa->numero,
            'complemento' => $empresa->complemento,
            'bairro' => $empresa->bairro,
            'cidade' => $empresa->cidade,
            'uf' => $empresa->uf,
            'valor' => $valor,
            'descricao' => 'Mensalidade ERP Comercial' . ($plano ? ' — plano ' . $plano->nome : ''),
            'vencimento' => self::primeiroVencimento($empresa)->format('Y-m-d'),
            'forma' => $forma === 'boleto' ? 'boleto' : 'pix',
        ]);
        if (! $r['ok']) {
            return ['ok' => false, 'fatura' => null, 'erro' => $r['erro'], 'detalhe' => $r['detalhe'] ?? null];
        }

        $fatura = self::espelhar($empresa, (array) ($r['dados']['estado'] ?? []));
        if (! $fatura) {
            return ['ok' => false, 'fatura' => null, 'erro' => 'A fatura não voltou do financeiro. Tente de novo em alguns minutos.'];
        }

        $empresa->forceFill([
            'assinatura_forma' => $forma,
            'assinatura_solicitada_em' => $empresa->assinatura_solicitada_em ?: now(),
            'cobranca_bloqueio_automatico' => true,
            'cobranca_tolerancia_dias' => self::toleranciaDias(),
        ])->save();
        Log::info('[AssinaturaSite] assinatura pedida', ['empresa_id' => $empresa->id, 'forma' => $forma, 'fatura_id' => $fatura->id, 'por' => $por?->id]);

        return ['ok' => true, 'fatura' => $fatura, 'erro' => null];
    }

    /**
     * "Gerar boleto" / "Gerar PIX" numa fatura em aberto que veio do caixa.
     *
     * @return array{ok: bool, erro: ?string}
     */
    public static function emitir(PlataformaFatura $fatura, string $tipo): array
    {
        if (! $fatura->isPendente() || ! $fatura->doCaixa() || ! in_array($tipo, ['pix', 'boleto'], true)) {
            return ['ok' => false, 'erro' => 'Esta fatura não aceita essa emissão.'];
        }
        $r = self::caixa('emitir', ['empresa_id' => $fatura->empresa_id, 'ref' => (int) $fatura->caixa_ref, 'tipo' => $tipo]);
        if (! $r['ok']) {
            return ['ok' => false, 'erro' => $r['erro'], 'detalhe' => $r['detalhe'] ?? null];
        }
        self::espelhar($fatura->empresa, (array) ($r['dados']['estado'] ?? []));

        return ['ok' => true, 'erro' => null];
    }

    /* ------------------------------------------------------------------ */
    /*  Cartão (link da InfinitePay)                                       */
    /* ------------------------------------------------------------------ */

    private static function infinitepay()
    {
        return Http::acceptJson()->asJson()->timeout(20)->baseUrl(rtrim((string) config('services.infinitepay.base_url'), '/'));
    }

    private static function centavos(PlataformaFatura $fatura): int
    {
        return (int) round((float) $fatura->valor * 100);
    }

    public static function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/integracao/v1/webhooks/infinitepay-plataforma/' . self::webhookToken();
    }

    /**
     * Link de cartão da fatura. Reaproveita o já gerado enquanto o valor for o mesmo
     * (o valor vai dentro da referência: mudou o valor, nasce outro link).
     *
     * @return array{ok: bool, link: ?string, erro: ?string}
     */
    public static function linkCartao(PlataformaFatura $fatura): array
    {
        if (! $fatura->isPendente()) {
            return ['ok' => false, 'link' => null, 'erro' => 'Esta fatura não está mais em aberto.'];
        }
        if (! self::cartaoDisponivel()) {
            return ['ok' => false, 'link' => null, 'erro' => 'O pagamento por cartão não está disponível agora.'];
        }
        $centavos = self::centavos($fatura);
        $prefixo = "fat{$fatura->id}v{$centavos}-";
        if ($fatura->cartao_link && $fatura->cartao_nsu && str_starts_with($fatura->cartao_nsu, $prefixo)) {
            return ['ok' => true, 'link' => $fatura->cartao_link, 'erro' => null];
        }

        $nsu = $prefixo . Str::lower(Str::random(10));
        $empresa = $fatura->empresa;
        try {
            $res = self::infinitepay()->post('/links', array_filter([
                'handle' => self::handle(),
                'order_nsu' => $nsu,
                'items' => [['quantity' => 1, 'price' => $centavos, 'description' => mb_substr($fatura->titulo(), 0, 100)]],
                'webhook_url' => self::webhookUrl(),
                'redirect_url' => route('app.faturas.cartao.retorno'),
                'customer' => array_filter([
                    'name' => mb_substr((string) ($empresa?->razao_social), 0, 100),
                    'email' => filter_var($empresa?->email, FILTER_VALIDATE_EMAIL) ? $empresa->email : null,
                ]) ?: null,
            ], fn ($v) => $v !== null && $v !== []))->throw()->json();
        } catch (\Throwable $e) {
            Log::warning('[AssinaturaSite] InfinitePay não criou o link', ['fatura_id' => $fatura->id, 'erro' => mb_substr($e->getMessage(), 0, 300)]);

            return ['ok' => false, 'link' => null, 'erro' => 'Não foi possível abrir o pagamento por cartão agora. Tente de novo ou pague por PIX ou boleto.'];
        }
        $link = (string) ($res['url'] ?? $res['payment_url'] ?? $res['link'] ?? '');
        if ($link === '') {
            return ['ok' => false, 'link' => null, 'erro' => 'A InfinitePay não devolveu o link de pagamento.'];
        }

        $fatura->forceFill([
            'cartao_link' => mb_substr($link, 0, 500),
            'cartao_nsu' => $nsu,
            'cartao_slug' => isset($res['slug']) ? mb_substr((string) $res['slug'], 0, 120) : null,
            'cartao_transaction_nsu' => null,
            'cartao_gerado_em' => now(),
        ])->save();
        Log::info('[AssinaturaSite] link de cartão criado', ['fatura_id' => $fatura->id, 'empresa_id' => $fatura->empresa_id, 'order_nsu' => $nsu]);

        return ['ok' => true, 'link' => $link, 'erro' => null];
    }

    /**
     * Confere o pagamento do link na InfinitePay (o aviso dela não é assinado: só vale o que
     * `payment_check` responder). Pago e com o valor cheio → baixa aqui e pede a baixa no caixa.
     *
     * @return array{pago: bool, consultou: bool}
     */
    public static function conferirCartao(PlataformaFatura $fatura, ?string $transactionNsu = null, ?string $slug = null): array
    {
        if (! $fatura->cartao_nsu) {
            return ['pago' => false, 'consultou' => false];
        }
        if ($fatura->cartao_pago_em) {
            return ['pago' => true, 'consultou' => false];
        }
        $fatura->forceFill(array_filter([
            'cartao_transaction_nsu' => $transactionNsu ? mb_substr($transactionNsu, 0, 120) : null,
            'cartao_slug' => $slug ? mb_substr($slug, 0, 120) : null,
        ]))->save();

        try {
            $res = self::infinitepay()->post('/payment_check', array_filter([
                'handle' => self::handle(),
                'order_nsu' => $fatura->cartao_nsu,
                'transaction_nsu' => $fatura->cartao_transaction_nsu,
                'slug' => $fatura->cartao_slug,
            ], fn ($v) => filled($v)))->throw()->json() ?? [];
        } catch (\Throwable $e) {
            Log::warning('[AssinaturaSite] payment_check falhou', ['fatura_id' => $fatura->id, 'erro' => mb_substr($e->getMessage(), 0, 300)]);

            return ['pago' => false, 'consultou' => false];
        }

        $pago = ($res['paid'] ?? false) === true;
        $recebido = (int) ($res['paid_amount'] ?? $res['amount'] ?? 0);
        if (! $pago || $recebido < self::centavos($fatura)) {
            if ($pago) {
                Log::warning('[AssinaturaSite] cartão pago com valor menor que a fatura — não baixei', ['fatura_id' => $fatura->id, 'recebido' => $recebido, 'esperado' => self::centavos($fatura)]);
            }

            return ['pago' => false, 'consultou' => true];
        }
        if (! $fatura->isPendente()) {
            // paga por outro caminho (PIX/boleto) e também no cartão: não mexe na fatura, só registra para a IA365 devolver
            $fatura->forceFill(['cartao_pago_em' => now()])->save();
            Log::warning('[AssinaturaSite] cartão pago em fatura que já não estava em aberto — conferir duplicidade', ['fatura_id' => $fatura->id, 'status' => $fatura->status]);

            return ['pago' => true, 'consultou' => true];
        }

        $fatura->forceFill(['cartao_pago_em' => now(), 'caixa_baixa_pendente' => $fatura->doCaixa()])->save();
        FaturasPlataforma::baixar($fatura, ['pago_em' => Carbon::today(), 'forma_pagamento' => 'cartão (InfinitePay)']);
        FaturasPlataforma::avisar($fatura, 'paga');
        Log::info('[AssinaturaSite] fatura paga no cartão', ['fatura_id' => $fatura->id, 'empresa_id' => $fatura->empresa_id, 'order_nsu' => $fatura->cartao_nsu]);
        self::avisarCaixaDaBaixa($fatura);

        return ['pago' => true, 'consultou' => true];
    }

    /** Pede ao caixa.ia365 a baixa da fatura paga no cartão. Falhou → a rotina tenta de novo. */
    public static function avisarCaixaDaBaixa(PlataformaFatura $fatura): bool
    {
        if (! $fatura->caixa_baixa_pendente || ! $fatura->caixa_ref) {
            return true;
        }
        $r = self::caixa('baixa', [
            'empresa_id' => $fatura->empresa_id,
            'ref' => (int) $fatura->caixa_ref,
            'pago_em' => ($fatura->pago_em ?: Carbon::today())->format('Y-m-d'),
            'nsu' => $fatura->cartao_nsu,
        ]);
        if ($r['ok']) {
            $fatura->forceFill(['caixa_baixa_pendente' => false])->save();
        }

        return $r['ok'];
    }

    /* ------------------------------------------------------------------ */
    /*  Efeito do pagamento                                                */
    /* ------------------------------------------------------------------ */

    /** Uma fatura da empresa foi paga: se era teste do site, vira assinante. */
    public static function aoPagar(Empresa $empresa): void
    {
        if (! self::vale($empresa) || $empresa->assinatura_ativada_em) {
            return;
        }
        $empresa->forceFill([
            'assinatura_ativada_em' => now(),
            'assinatura_solicitada_em' => $empresa->assinatura_solicitada_em ?: now(),
            'em_trial' => false,
            'cobranca_bloqueio_automatico' => true,
            'cobranca_tolerancia_dias' => $empresa->assinatura_solicitada_em ? $empresa->cobranca_tolerancia_dias : self::toleranciaDias(),
        ])->save();
        Log::info('[AssinaturaSite] assinatura ativada (1ª mensalidade paga)', ['empresa_id' => $empresa->id]);
    }
}
