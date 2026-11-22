<?php

namespace App\Services\OpenFinance;

use App\Models\CartaoCredito;
use App\Models\ContaBancaria;
use App\Models\LancamentoBancario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Traz o extrato do banco para dentro do ERP (17/11/2026).
 *
 * Cada passada faz UMA coisa por conta, na ordem:
 *  1. conta aguardando → pergunta se o dono já autorizou no banco;
 *  2. protocolo pedido → busca o resultado e grava os lançamentos;
 *  3. chegou a hora → pede um protocolo novo (a Tecnospeed aceita 1 a cada 6 h por conta).
 */
class SincronizadorExtrato
{
    /** Horas entre um pedido de extrato e o próximo (limite da Tecnospeed em produção). */
    public const INTERVALO_HORAS = 6;

    /** Dias para trás no primeiro extrato de uma conta. */
    public const PRIMEIRA_CARGA_DIAS = 90;

    /** Dias que cada extrato volta sobre o anterior, para pegar lançamento que o banco ajustou. */
    public const SOBREPOSICAO_DIAS = 5;

    public function __construct(
        private readonly TecnospeedClient $api,
        private readonly ConciliadorSugestoes $conciliador,
    ) {}

    /** @return string o que aconteceu, em uma frase (vai para o log da rotina e para a tela) */
    public function sincronizar(ContaBancaria $conta, bool $forcar = false): string
    {
        if (! $conta->account_hash || $conta->situacao === 'desconectada') {
            return 'conta desconectada';
        }

        $resultado = $this->passoDaConta($conta, $forcar);

        // Cartões só depois que a conta conectou; cada cartão tem o seu próprio relógio de 6 h.
        $conta->refresh();
        if ($conta->situacao === 'conectada') {
            $this->atualizarSaldo($conta);

            foreach ($conta->cartoes()->where('importar', true)->get() as $cartao) {
                $resultado .= ' · ' . $cartao->nome() . ': ' . $this->passoDoCartao($conta, $cartao, $forcar);
            }
        }

        return $resultado;
    }

    private function passoDaConta(ContaBancaria $conta, bool $forcar): string
    {
        try {
            if ($conta->situacao !== 'conectada') {
                return $this->conferirConexao($conta);
            }

            if ($conta->protocolo_pendente) {
                return $this->buscarProtocolo($conta);
            }

            if ($forcar || ! $conta->proxima_sincronizacao_em || $conta->proxima_sincronizacao_em->isPast()) {
                return $this->pedirExtrato($conta);
            }

            return 'ainda não é hora';
        } catch (OpenFinanceException $e) {
            // 429 = já pediu extrato nas últimas 6 h: não é erro, é só esperar.
            $conta->update([
                'ultimo_erro' => Str::limit($e->getMessage(), 490),
                'proxima_sincronizacao_em' => now()->addHours($e->status === 429 ? self::INTERVALO_HORAS : 1),
            ]);

            return 'erro: ' . $e->getMessage();
        }
    }

    private function passoDoCartao(ContaBancaria $conta, CartaoCredito $cartao, bool $forcar): string
    {
        try {
            if ($cartao->protocolo_pendente) {
                $dados = $this->api->consultarExtrato($cartao->protocolo_pendente);
                $status = strtoupper((string) data_get($dados, 'statement.status'));

                if ($status === 'PROCESSING' || $status === '') {
                    return 'banco ainda processando';
                }

                $limites = array_filter([
                    'limite' => data_get($dados, 'statement.creditCardCurrentCreditLimit'),
                    'limite_disponivel' => data_get($dados, 'statement.creditCardCurrentAvaliableCreditLimit'),
                ], 'is_numeric');

                if ($status !== 'SUCCESS') {
                    $vazio = $this->semMovimento($dados);
                    $cartao->update($limites + [
                        'protocolo_pendente' => null,
                        'ultima_sincronizacao_em' => $vazio ? now() : $cartao->ultima_sincronizacao_em,
                        'ultimo_erro' => $vazio ? null : Str::limit('O banco não devolveu as compras: ' . (data_get($dados, 'statement.reason') ?: $status), 490),
                    ]);

                    return $vazio ? 'sem compras no período' : 'extrato recusado pelo banco';
                }

                $novos = $this->gravar($conta, $dados, $cartao);
                $cartao->update($limites + ['protocolo_pendente' => null, 'ultima_sincronizacao_em' => now(), 'ultimo_erro' => null]);
                $this->conciliador->sugerirPendentes($conta->empresa_id);

                return "{$novos} compra(s) nova(s)" . ($this->noBanco ? " · {$this->noBanco} pendente(s) no banco" : '');
            }

            if ($forcar || ! $cartao->proxima_sincronizacao_em || $cartao->proxima_sincronizacao_em->isPast()) {
                $ultima = LancamentoBancario::withoutGlobalScopes()->where('cartao_credito_id', $cartao->id)->max('data');
                $inicio = $ultima ? Carbon::parse($ultima)->subDays(self::SOBREPOSICAO_DIAS) : now()->subDays(self::PRIMEIRA_CARGA_DIAS);

                $protocolo = $this->api->pedirExtrato($conta->account_hash, $inicio->toDateString(), now()->toDateString(), $cartao->final);
                $cartao->update([
                    'protocolo_pendente' => $protocolo,
                    'protocolo_inicio' => $inicio->toDateString(),
                    'protocolo_fim' => now()->toDateString(),
                    'proxima_sincronizacao_em' => now()->addHours(self::INTERVALO_HORAS),
                    'ultimo_erro' => null,
                ]);

                return 'compras pedidas ao banco';
            }

            return 'ainda não é hora';
        } catch (OpenFinanceException $e) {
            $cartao->update([
                'ultimo_erro' => Str::limit($e->getMessage(), 490),
                'proxima_sincronizacao_em' => now()->addHours($e->status === 429 ? self::INTERVALO_HORAS : 1),
            ]);

            return 'erro: ' . $e->getMessage();
        }
    }

    /**
     * Saldo informado pelo banco. Serve para a pessoa VER quanto tem; nunca entra na conciliação
     * (o banco atualiza só de 6 em 6 horas, então pode estar atrás do extrato). Falha aqui não
     * atrapalha o extrato: tenta de novo em 1 hora.
     */
    public function atualizarSaldo(ContaBancaria $conta, bool $forcar = false): bool
    {
        if (! $forcar && $conta->saldo_proxima_em && $conta->saldo_proxima_em->isFuture()) {
            return false;
        }

        try {
            $d = $this->api->saldo($conta->account_hash);
        } catch (OpenFinanceException) {
            $conta->update(['saldo_proxima_em' => now()->addHour()]);

            return false;
        }

        if (! is_numeric($d['balance'] ?? null)) {
            $conta->update(['saldo_proxima_em' => now()->addHour()]);

            return false;
        }

        $conta->update([
            'saldo' => $d['balance'],
            'saldo_bloqueado' => is_numeric($d['blockedBalance'] ?? null) ? $d['blockedBalance'] : null,
            'saldo_aplicado' => is_numeric($d['automaticallyInvestedBalance'] ?? null) ? $d['automaticallyInvestedBalance'] : null,
            'saldo_atualizado_em' => ! empty($d['updateDateTime']) ? Carbon::parse($d['updateDateTime'])->setTimezone(config('app.timezone')) : now(),
            'saldo_proxima_em' => ! empty($d['nextUpdate']) ? Carbon::parse($d['nextUpdate'])->setTimezone(config('app.timezone')) : now()->addHours(self::INTERVALO_HORAS),
        ]);

        return true;
    }

    /** "FAILED — nenhuma transação foi localizada no período" não é erro: é período sem movimento. */
    private function semMovimento(array $dados): bool
    {
        return str_contains(mb_strtolower((string) data_get($dados, 'statement.reason')), 'nenhuma transa');
    }

    /** Lê na Tecnospeed os cartões da conta e guarda os novos (desligados: a empresa escolhe quais importar). */
    public function buscarCartoes(ContaBancaria $conta): int
    {
        $novos = 0;
        foreach ($this->api->listarCartoes($conta->account_hash) as $c) {
            $final = str_pad(preg_replace('/\D/', '', (string) ($c['cardNumber'] ?? '')), 4, '0', STR_PAD_LEFT);
            if ($final === '0000' && empty($c['cardNumber'])) {
                continue;
            }

            $cartao = CartaoCredito::withoutGlobalScopes()->firstOrNew(['conta_bancaria_id' => $conta->id, 'final' => $final]);
            $novos += $cartao->exists ? 0 : 1;
            $cartao->fill([
                'empresa_id' => $conta->empresa_id,
                'adicionais' => Str::limit((string) ($c['additionalCards'] ?? ''), 118, '') ?: null,
                'limite' => is_numeric($c['creditLimit'] ?? null) ? $c['creditLimit'] : $cartao->limite,
                'limite_disponivel' => is_numeric($c['availableCreditLimit'] ?? null) ? $c['availableCreditLimit'] : $cartao->limite_disponivel,
            ])->save();
        }

        return $novos;
    }

    /** O dono já abriu o link e autorizou no banco? */
    public function conferirConexao(ContaBancaria $conta): string
    {
        $dados = $this->api->consultarConta($conta->account_hash);
        $status = (string) ($dados['statusOpenfinance'] ?? '');
        $conectada = ! empty($dados['openfinanceId']) || in_array(strtoupper($status), ['ATIVO', 'ATIVA', 'ATIVADO', 'ACTIVE', 'CONECTADO'], true);

        $conta->update([
            'status_banco' => $status ?: $conta->status_banco,
            'link_conexao' => ($dados['openfinanceLink'] ?? null) ?: $conta->link_conexao,
            'situacao' => $conectada ? 'conectada' : 'aguardando',
            'conectada_em' => $conectada ? ($conta->conectada_em ?? now()) : null,
            'ultimo_erro' => $conectada ? null : (Str::limit((string) ($dados['openFinanceErrorMessage'] ?? ''), 490) ?: null),
        ]);

        return $conectada ? 'conta conectada' : 'aguardando autorização no banco';
    }

    private function pedirExtrato(ContaBancaria $conta): string
    {
        $ultima = LancamentoBancario::withoutGlobalScopes()->where('conta_bancaria_id', $conta->id)->whereNull('cartao_credito_id')->max('data');
        $inicio = $ultima
            ? Carbon::parse($ultima)->subDays(self::SOBREPOSICAO_DIAS)
            : now()->subDays(self::PRIMEIRA_CARGA_DIAS);
        $fim = now();

        $protocolo = $this->api->pedirExtrato($conta->account_hash, $inicio->toDateString(), $fim->toDateString());

        $conta->update([
            'protocolo_pendente' => $protocolo,
            'protocolo_inicio' => $inicio->toDateString(),
            'protocolo_fim' => $fim->toDateString(),
            'proxima_sincronizacao_em' => now()->addHours(self::INTERVALO_HORAS),
            'ultimo_erro' => null,
        ]);

        return 'extrato pedido ao banco';
    }

    private function buscarProtocolo(ContaBancaria $conta): string
    {
        $dados = $this->api->consultarExtrato($conta->protocolo_pendente);
        $status = strtoupper((string) data_get($dados, 'statement.status'));

        if ($status === 'PROCESSING' || $status === '') {
            return 'banco ainda processando o extrato';
        }

        if ($status !== 'SUCCESS') {
            $vazio = $this->semMovimento($dados);
            $conta->update([
                'protocolo_pendente' => null,
                'ultima_sincronizacao_em' => $vazio ? now() : $conta->ultima_sincronizacao_em,
                'ultimo_erro' => $vazio ? null : Str::limit('O banco não devolveu o extrato: ' . (data_get($dados, 'statement.reason') ?: $status), 490),
            ]);

            return $vazio ? 'sem movimento no período' : 'extrato recusado pelo banco';
        }

        $novos = $this->gravar($conta, $dados);

        $conta->update([
            'protocolo_pendente' => null,
            'ultima_sincronizacao_em' => now(),
            'ultimo_erro' => null,
        ]);

        $sugeridos = $this->conciliador->sugerirPendentes($conta->empresa_id);

        return "{$novos} lançamento(s) novo(s), {$sugeridos} com sugestão";
    }

    /** Compras do cartão que vieram PENDING no último gravar() (fatura aberta). */
    public int $noBanco = 0;

    /**
     * Grava o que o banco já efetivou (POSTED), sem duplicar; devolve quantos são novos.
     * Cartão (22/11/2026): a compra da fatura aberta vem PENDING — fica como 'no_banco'
     * (visível, fora da conciliação) e vira 'pendente' quando o banco mandar POSTED.
     * PENDING da conta corrente continua de fora.
     */
    public function gravar(ContaBancaria $conta, array $dados, ?CartaoCredito $cartao = null): int
    {
        $novos = 0;
        $vistos = [];
        $this->noBanco = 0;

        foreach (['transaction', 'transactionDuplicated'] as $bloco) {
            foreach (['credit' => 'credito', 'debit' => 'debito'] as $chave => $tipo) {
                foreach ((array) data_get($dados, "{$bloco}.{$chave}", []) as $t) {
                    $id = (string) ($t['transactionId'] ?? '');
                    if ($id !== '' && $cartao) {
                        $id = 'cc' . $cartao->final . ':' . $id;
                    }
                    $situacao = strtoupper((string) ($t['status'] ?? 'POSTED'));
                    $aberta = $cartao && $situacao === 'PENDING';
                    if ($id === '' || ($situacao !== 'POSTED' && ! $aberta)) {
                        continue;
                    }
                    $vistos[] = $id;

                    // No crédito quem interessa é quem pagou; no débito, quem recebeu.
                    $creditoNoCartao = $cartao && $tipo === 'credito';
                    $existente = LancamentoBancario::withoutGlobalScopes()
                        ->where('conta_bancaria_id', $conta->id)->where('transaction_id', $id)->first();

                    if ($existente) {
                        if ($existente->status === 'no_banco') {
                            if ($aberta) {
                                $this->noBanco++;
                            } else {
                                // O banco efetivou: entra na conciliação agora (conta como nova).
                                $existente->update(['status' => 'pendente', 'data' => $t['date'] ?? $existente->data,
                                    'valor' => abs((float) ($t['amount'] ?? $existente->valor)), 'bruto' => $t]);
                                $novos++;
                            }

                            continue;
                        }
                        // Voltou a aparecer depois de ter sumido: volta para a fila.
                        if ($existente->status === 'removido') {
                            $existente->update(['status' => $aberta && ! $creditoNoCartao ? 'no_banco' : 'pendente']);
                        }
                        if ($existente->sumiu_no_banco) {
                            $existente->update(['sumiu_no_banco' => false]);
                        }

                        continue;
                    }

                    $parte = (array) ($tipo === 'credito' ? ($t['participantPayer'] ?? []) : ($t['participantReceiver'] ?? []));
                    $doc = preg_replace('/\D/', '', (string) data_get($parte, 'documentNumber.value', ''));
                    // Compra no cartão: a loja vem em creditCardMerchant (nome + CNPJ), não em participantReceiver.
                    $loja = $cartao ? (array) ($t['creditCardMerchant'] ?? []) : [];
                    if ($doc === '' && $loja) {
                        $doc = preg_replace('/\D/', '', (string) ($loja['cpfCnpj'] ?? ''));
                        $parte['name'] = ($parte['name'] ?? '') ?: ($loja['name'] ?? '');
                    }

                    // No cartão, o crédito é pagamento de fatura ou estorno: abate o saldo do cartão, não é receita.

                    LancamentoBancario::create([
                        'empresa_id' => $conta->empresa_id,
                        'conta_bancaria_id' => $conta->id,
                        'cartao_credito_id' => $cartao?->id,
                        'parcela_numero' => $cartao && is_numeric($t['creditCardInstallmentNumber'] ?? null) ? (int) $t['creditCardInstallmentNumber'] : null,
                        'parcela_total' => $cartao && is_numeric($t['creditCardTotalInstallments'] ?? null) ? (int) $t['creditCardTotalInstallments'] : null,
                        'transaction_id' => $id,
                        'data' => $t['date'] ?? now()->toDateString(),
                        'tipo' => $tipo,
                        'valor' => abs((float) ($t['amount'] ?? 0)),
                        'descricao' => Str::limit((string) ($t['description'] ?? ''), 250, ''),
                        'operacao' => Str::limit((string) ($t['operationType'] ?? ''), 30, '') ?: null,
                        'contraparte_nome' => Str::limit((string) (($parte['name'] ?? '') ?: ($t['paymentName'] ?? '') ?: ''), 158, '') ?: null,
                        'contraparte_documento' => in_array(strlen($doc), [11, 14], true) ? $doc : null,
                        'linha_digitavel' => Str::limit(preg_replace('/\D/', '', (string) ($t['paymentDigitableLine'] ?? '')), 60, '') ?: null,
                        'bruto' => $t,
                        'status' => $creditoNoCartao ? 'ignorado' : ($aberta ? 'no_banco' : 'pendente'),
                        'sugestao_motivo' => $creditoNoCartao ? 'Pagamento de fatura ou estorno no cartão' : null,
                    ]);
                    if ($aberta && ! $creditoNoCartao) {
                        $this->noBanco++;
                    } else {
                        $novos += $creditoNoCartao ? 0 : 1;   // pagamento de fatura não é "compra nova"
                    }
                }
            }
        }

        $this->marcarSumidos($conta, $vistos, $cartao);

        return $novos;
    }

    /**
     * O banco às vezes apaga um lançamento e manda de novo com outro número. O que estava no
     * período pedido e não voltou sai da fila; se já estava conciliado, fica marcado para revisão
     * (não desfaz baixa sozinho).
     */
    private function marcarSumidos(ContaBancaria $conta, array $vistos, ?CartaoCredito $cartao = null): void
    {
        $inicio = $cartao ? $cartao->protocolo_inicio : $conta->protocolo_inicio;
        $fim = $cartao ? $cartao->protocolo_fim : $conta->protocolo_fim;
        if (! $inicio || ! $fim) {
            return;
        }

        $base = LancamentoBancario::withoutGlobalScopes()
            ->where('conta_bancaria_id', $conta->id)
            ->when($cartao, fn ($q) => $q->where('cartao_credito_id', $cartao->id), fn ($q) => $q->whereNull('cartao_credito_id'))
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->whereNotIn('transaction_id', $vistos ?: ['-']);

        (clone $base)->whereIn('status', ['pendente', 'sugerido', 'no_banco'])->update([
            'status' => 'removido',
            'sugestao_conta_receber_id' => null,
            'sugestao_conta_pagar_id' => null,
            'sugestao_confianca' => null,
            'sugestao_motivo' => null,
        ]);
        (clone $base)->where('status', 'conciliado')->update(['sumiu_no_banco' => true]);
    }
}
