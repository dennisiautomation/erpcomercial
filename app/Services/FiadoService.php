<?php

namespace App\Services;

use App\Enums\TipoMovimentacaoCaixa;
use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\FiadoRecebimento;
use App\Models\MovimentacaoCaixa;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fiado / pagamento direto com a loja (16/11/2026).
 *
 * Onde vive: a DÍVIDA são as contas a receber do cliente com forma `crediario`
 * em aberto (pendente/vencida); o PAGAMENTO é `fiado_recebimentos` + uma entrada
 * `recebimento` no caixa. Cada loja dá ao botão o nome que quiser ("FIADO",
 * "Crediário", "Conta da loja") — o código gravado continua `crediario`.
 *
 * Antes daqui o PDV gravava o crediário como já pago na hora e ninguém sabia
 * quem devia.
 */
class FiadoService
{
    public const FORMA = 'crediario';

    /** Prazo do 1º vencimento quando a tela não informa. */
    public const PRAZO_PADRAO_DIAS = 30;

    public const MAX_PARCELAS = 12;

    /** Formas em que o cliente pode pagar o fiado (código gravado). */
    public const FORMAS_RECEBIMENTO = ['dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'transferencia'];

    public static function ehFiado(?string $forma): bool
    {
        return $forma === self::FORMA;
    }

    /** Contas em aberto do cliente, da mais antiga para a mais nova. */
    public function contasAbertas(int $empresaId, int $clienteId, bool $travar = false): Collection
    {
        $q = ContaReceber::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('cliente_id', $clienteId)
            ->where('forma_pagamento', self::FORMA)
            ->whereIn('status', ['pendente', 'vencida'])
            ->whereNull('deleted_at')
            ->orderBy('vencimento')
            ->orderBy('id');

        if ($travar) {
            $q->lockForUpdate();
        }

        return $q->get();
    }

    /**
     * Situação do cliente: quanto deve, quanto está vencido, limite e quanto
     * ainda pode comprar. Limite 0/vazio = sem limite definido.
     */
    public function saldo(int $empresaId, int $clienteId): array
    {
        $contas = $this->contasAbertas($empresaId, $clienteId);
        $hoje = now()->startOfDay();
        $devendo = round((float) $contas->sum(fn ($c) => (float) $c->valor - (float) $c->valor_pago), 2);
        $vencido = round((float) $contas
            ->filter(fn ($c) => $c->vencimento && $c->vencimento->lt($hoje))
            ->sum(fn ($c) => (float) $c->valor - (float) $c->valor_pago), 2);

        $cliente = Cliente::withoutGlobalScopes()->where('empresa_id', $empresaId)->find($clienteId);
        $limite = $cliente ? round((float) $cliente->limite_credito, 2) : 0.0;

        return [
            'devendo'      => $devendo,
            'vencido'      => $vencido,
            'limite'       => $limite > 0 ? $limite : null,
            'disponivel'   => $limite > 0 ? round(max(0, $limite - $devendo), 2) : null,
            'qtd_contas'   => $contas->count(),
            'proximo_venc' => $contas->first()?->vencimento?->format('Y-m-d'),
        ];
    }

    /**
     * Confere a venda no fiado ANTES de gravar: cliente obrigatório, dentro do limite.
     *
     * @throws \DomainException
     */
    public function validarVenda(int $empresaId, ?int $clienteId, float $valorFiado, string $nomeForma = 'fiado'): void
    {
        if ($valorFiado <= 0) {
            return;
        }
        if (! $clienteId) {
            throw new \DomainException("Venda no \"{$nomeForma}\" precisa do cliente: escolha o cliente antes de finalizar.");
        }

        $cliente = Cliente::withoutGlobalScopes()->where('empresa_id', $empresaId)->find($clienteId);
        if (! $cliente) {
            throw new \DomainException('Cliente não encontrado nesta empresa.');
        }

        $saldo = $this->saldo($empresaId, $clienteId);
        if ($saldo['limite'] !== null && round($saldo['devendo'] + $valorFiado, 2) > $saldo['limite'] + 0.005) {
            throw new \DomainException(sprintf(
                '%s passa do limite: limite R$ %s, já deve R$ %s, pode comprar até R$ %s no "%s". Aumente o limite no cadastro do cliente ou receba uma parte.',
                $cliente->nome_razao_social,
                $this->brl($saldo['limite']),
                $this->brl($saldo['devendo']),
                $this->brl($saldo['disponivel']),
                $nomeForma
            ));
        }
    }

    /**
     * Data do 1º vencimento: a escolhida na tela (hoje ou depois) ou hoje + 30.
     *
     * @throws \DomainException
     */
    public function primeiroVencimento(?string $data): Carbon
    {
        if ($data === null || trim($data) === '') {
            return now()->startOfDay()->addDays(self::PRAZO_PADRAO_DIAS);
        }
        try {
            $d = Carbon::createFromFormat('Y-m-d', trim($data))->startOfDay();
        } catch (\Throwable) {
            throw new \DomainException('Data de vencimento do fiado inválida.');
        }
        if ($d->lt(now()->startOfDay())) {
            throw new \DomainException('O vencimento do fiado não pode ser antes de hoje.');
        }
        if ($d->gt(now()->startOfDay()->addYear())) {
            throw new \DomainException('O vencimento do fiado não pode passar de 1 ano.');
        }

        return $d;
    }

    /**
     * Cria as contas em aberto de um pagamento no fiado (1 por parcela, a cada mês).
     *
     * `$base` traz empresa_id, unidade_id, cliente_id, venda_id, pedido_id?,
     * forma_chave, forma_nome. `$prefixo` é "Venda PDV #12" / "Pedido #8".
     *
     * @param  ContaReceber|null  $reaproveitar  conta já aberta (pedido confirmado) que vira a 1ª parcela
     * @return ContaReceber[]
     */
    public function criarContas(array $base, string $prefixo, float $total, int $parcelas, Carbon $primeiro, ?ContaReceber $reaproveitar = null): array
    {
        $parcelas = max(1, min(self::MAX_PARCELAS, $parcelas));
        $nome = (string) ($base['forma_nome'] ?? '') ?: 'Fiado';
        $valorParcela = round($total / $parcelas, 2);
        $acumulado = 0.0;
        $contas = [];

        for ($i = 1; $i <= $parcelas; $i++) {
            $valorEsta = $i === $parcelas ? round($total - $acumulado, 2) : $valorParcela;
            $acumulado = round($acumulado + $valorEsta, 2);

            $dados = $base + [
                'forma_pagamento' => self::FORMA,
                'descricao'       => "{$prefixo} - {$nome}" . ($parcelas > 1 ? " {$i}/{$parcelas}" : ''),
                'valor'           => $valorEsta,
                'valor_pago'      => 0,
                'vencimento'      => $primeiro->copy()->addMonthsNoOverflow($i - 1),
                'pago_em'         => null,
                'parcela'         => $i,
                'total_parcelas'  => $parcelas,
                'status'          => 'pendente',
            ];

            if ($i === 1 && $reaproveitar) {
                $reaproveitar->update($dados);
                $contas[] = $reaproveitar;
            } else {
                $contas[] = ContaReceber::create($dados);
            }
        }

        return $contas;
    }

    /**
     * Cliente paga o fiado no caixa: abate as contas mais antigas primeiro
     * (ou só as escolhidas), registra o recebimento e lança a entrada no caixa.
     *
     * `$pgto` = ['forma' => código, 'forma_chave' => ?, 'forma_nome' => ?].
     *
     * @throws \DomainException
     */
    public function receber(int $empresaId, int $clienteId, float $valor, array $pgto, Caixa $caixa, int $userId, ?array $contaIds = null, ?string $observacoes = null): FiadoRecebimento
    {
        $valor = round($valor, 2);
        if ($valor <= 0) {
            throw new \DomainException('Informe o valor que o cliente está pagando.');
        }
        $forma = (string) ($pgto['forma'] ?? '');
        if (! in_array($forma, self::FORMAS_RECEBIMENTO, true)) {
            throw new \DomainException('Escolha como o cliente pagou: dinheiro, PIX, cartão ou transferência.');
        }

        $cliente = Cliente::withoutGlobalScopes()->where('empresa_id', $empresaId)->find($clienteId);
        if (! $cliente) {
            throw new \DomainException('Cliente não encontrado nesta empresa.');
        }

        return DB::transaction(function () use ($empresaId, $cliente, $valor, $pgto, $forma, $caixa, $userId, $contaIds, $observacoes) {
            $todas = $this->contasAbertas($empresaId, (int) $cliente->id, true);
            $saldoAntes = round((float) $todas->sum(fn ($c) => (float) $c->valor - (float) $c->valor_pago), 2);

            $alvo = $todas;
            if ($contaIds) {
                $ids = array_map('intval', $contaIds);
                $alvo = $todas->filter(fn ($c) => in_array((int) $c->id, $ids, true))->values();
                if ($alvo->isEmpty()) {
                    throw new \DomainException('As contas escolhidas já foram pagas ou não são deste cliente. Recarregue a tela.');
                }
            }

            $aberto = round((float) $alvo->sum(fn ($c) => (float) $c->valor - (float) $c->valor_pago), 2);
            if ($aberto <= 0) {
                throw new \DomainException("{$cliente->nome_razao_social} não tem fiado em aberto.");
            }
            if ($valor > $aberto + 0.005) {
                throw new \DomainException(sprintf('O valor (R$ %s) é maior do que o cliente deve (R$ %s).', $this->brl($valor), $this->brl($aberto)));
            }

            $numero = (int) FiadoRecebimento::withoutGlobalScopes()->where('empresa_id', $empresaId)->lockForUpdate()->max('numero') + 1;
            $restante = $valor;
            $detalhe = [];

            foreach ($alvo as $conta) {
                if ($restante <= 0.004) {
                    break;
                }
                $falta = round((float) $conta->valor - (float) $conta->valor_pago, 2);
                $abate = round(min($falta, $restante), 2);
                $novoPago = round((float) $conta->valor_pago + $abate, 2);
                $quitou = $novoPago >= (float) $conta->valor - 0.004;

                $conta->update([
                    'valor_pago'  => $novoPago,
                    'status'      => $quitou ? 'paga' : $conta->status,
                    'pago_em'     => $quitou ? now() : $conta->pago_em,
                    'observacoes' => trim(($conta->observacoes ? $conta->observacoes . "\n" : '')
                        . sprintf('Recebido R$ %s em %s (recibo de fiado nº %d)', $this->brl($abate), now()->format('d/m/Y H:i'), $numero)),
                ]);

                $detalhe[] = [
                    'conta_id'   => $conta->id,
                    'descricao'  => $conta->descricao,
                    'vencimento' => $conta->vencimento?->format('Y-m-d'),
                    'abatido'    => $abate,
                    'restante'   => round((float) $conta->valor - $novoPago, 2),
                ];
                $restante = round($restante - $abate, 2);
            }

            $mov = MovimentacaoCaixa::create([
                'empresa_id'      => $empresaId,
                'unidade_id'      => $caixa->unidade_id,
                'caixa_id'        => $caixa->id,
                'venda_id'        => null,
                'tipo'            => TipoMovimentacaoCaixa::Recebimento,
                'valor'           => $valor,
                'forma_pagamento' => $forma,
                'forma_chave'     => $pgto['forma_chave'] ?? null,
                'forma_nome'      => $pgto['forma_nome'] ?? null,
                'descricao'       => "Recebimento de fiado nº {$numero} — " . mb_substr((string) $cliente->nome_razao_social, 0, 120),
                'user_id'         => $userId,
            ]);

            return FiadoRecebimento::create([
                'empresa_id'            => $empresaId,
                'unidade_id'            => $caixa->unidade_id,
                'cliente_id'            => $cliente->id,
                'caixa_id'              => $caixa->id,
                'movimentacao_caixa_id' => $mov->id,
                'user_id'               => $userId,
                'numero'                => $numero,
                'valor'                 => $valor,
                'forma_pagamento'       => $forma,
                'forma_chave'           => $pgto['forma_chave'] ?? null,
                'forma_nome'            => $pgto['forma_nome'] ?? null,
                'contas'                => $detalhe,
                'saldo_antes'           => $saldoAntes,
                'saldo_depois'          => round($saldoAntes - $valor, 2),
                'observacoes'           => $observacoes ? mb_substr($observacoes, 0, 255) : null,
            ]);
        });
    }

    /** Botões ligados da empresa que servem para RECEBER o fiado (dinheiro, PIX, cartão, transferência). */
    public function formasDeRecebimento(int $empresaId): Collection
    {
        return app(FormaPagamentoService::class)->ativas($empresaId)
            ->filter(fn ($f) => in_array($f->codigoGravado(), self::FORMAS_RECEBIMENTO, true))
            ->values();
    }

    /** Clientes com fiado em aberto (para a tela "Quem me deve"). */
    public function devedores(int $empresaId, ?string $busca = null, bool $soVencidos = false): Collection
    {
        $hoje = now()->toDateString();
        $q = DB::table('contas_receber as c')
            ->join('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->where('c.empresa_id', $empresaId)
            ->where('c.forma_pagamento', self::FORMA)
            ->whereIn('c.status', ['pendente', 'vencida'])
            ->whereNull('c.deleted_at')
            ->groupBy('c.cliente_id', 'cl.nome_razao_social', 'cl.cpf_cnpj', 'cl.telefone', 'cl.whatsapp', 'cl.limite_credito')
            ->selectRaw('c.cliente_id, cl.nome_razao_social, cl.cpf_cnpj, cl.telefone, cl.whatsapp, cl.limite_credito,
                SUM(c.valor - c.valor_pago) as devendo,
                SUM(CASE WHEN c.vencimento < ? THEN c.valor - c.valor_pago ELSE 0 END) as vencido,
                MIN(c.vencimento) as primeiro_venc, COUNT(*) as qtd', [$hoje])
            ->havingRaw('SUM(c.valor - c.valor_pago) > 0.004')
            ->orderByRaw('vencido > 0 DESC, primeiro_venc ASC');

        if ($busca !== null && trim($busca) !== '') {
            $t = '%' . trim($busca) . '%';
            $digitos = preg_replace('/\D/', '', $busca);
            $q->where(function ($w) use ($t, $digitos) {
                $w->where('cl.nome_razao_social', 'like', $t);
                if ($digitos !== '') {
                    // cadastro grava com máscara (062.456.789-09)
                    $w->orWhereRaw("REPLACE(REPLACE(REPLACE(cl.cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE ?", ['%' . $digitos . '%']);
                }
            });
        }
        if ($soVencidos) {
            $q->havingRaw('SUM(CASE WHEN c.vencimento < ? THEN c.valor - c.valor_pago ELSE 0 END) > 0.004', [$hoje]);
        }

        return $q->get();
    }

    private function brl(?float $v): string
    {
        return number_format((float) $v, 2, ',', '.');
    }
}
