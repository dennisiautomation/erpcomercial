<?php

namespace App\Services;

use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoCaixa;
use App\Enums\TipoMovimentacaoEstoque;
use App\Models\Caixa;
use App\Models\ConfiguracaoLoja;
use App\Models\ContaReceber;
use App\Models\Devolucao;
use App\Models\Estoque;
use App\Models\MovimentacaoCaixa;
use App\Models\User;
use App\Models\Vale;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Troca e devolução de itens de uma venda (03/09/2026).
 *
 * Ponto único que grava a devolução: entrada de estoque, abatimento de parcelas
 * em aberto, vale ou dinheiro pela sobra, status da venda. O PDV (troca com
 * venda nova na hora) e a tela /app/trocas (devolução sem levar nada) passam
 * os dois por aqui.
 *
 * Política da loja (Configurações da Loja → Trocas), lida da UNIDADE DA SESSÃO
 * — é a loja onde o cliente está, não a loja onde ele comprou:
 *   - troca_prazo_dias          fora do prazo = fora da política
 *   - troca_sobra               vale (padrão) | dinheiro
 *   - troca_vale_validade_dias  0 = sem validade
 *   - troca_senha_gerente       fora da política / dinheiro pede um gerente
 */
class TrocaService
{
    /** Formas em que o CLIENTE ainda deve à loja — só essas se abatem na devolução. */
    private const FORMAS_ABATIVEIS = ['crediario', 'boleto'];

    /* ------------------------------------------------------------------ */
    /*  Leitura                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Situação da venda para a tela de troca: itens com o que já voltou,
     * valor unitário líquido, prazo, parcelas em aberto, o que a política exige.
     */
    public function situacao(Venda $venda, ConfiguracaoLoja $config, User $user): array
    {
        $venda->loadMissing(['itens.produto', 'devolucoes.itens', 'cliente', 'unidade', 'contasReceber']);

        $devolvidoPorItem = [];
        foreach ($venda->devolucoes->where('status', '!=', 'cancelada') as $dev) {
            foreach ($dev->itens as $di) {
                $devolvidoPorItem[$di->venda_item_id] = ($devolvidoPorItem[$di->venda_item_id] ?? 0) + (float) $di->quantidade;
            }
        }

        $fator = $this->fatorDescontoGlobal($venda);

        $itens = $venda->itens->map(function ($item) use ($devolvidoPorItem, $fator) {
            $qtd = (float) $item->quantidade;
            $devolvida = (float) ($devolvidoPorItem[$item->id] ?? 0);
            $unitario = $qtd > 0 ? round(((float) $item->total / $qtd) * $fator, 2) : 0.0;

            return [
                'venda_item_id'  => $item->id,
                'produto_id'     => $item->produto_id,
                'descricao'      => $item->descricao ?: ($item->produto->descricao ?? 'Item'),
                'codigo'         => $item->produto->codigo_interno ?? null,
                'quantidade'     => $qtd,
                'devolvida'      => $devolvida,
                'disponivel'     => max(0, round($qtd - $devolvida, 3)),
                'valor_unitario' => $unitario,
                'e_servico'      => $item->servico_id !== null || $item->produto_id === null,
            ];
        })->values();

        $dias = (int) $venda->created_at->copy()->startOfDay()->diffInDays(today());
        $prazo = (int) $config->troca_prazo_dias;
        $foraPrazo = $prazo > 0 && $dias > $prazo;

        $parcelasAbertas = $venda->contasReceber
            ->whereIn('status', ['pendente', 'vencida'])
            ->whereIn('forma_pagamento', self::FORMAS_ABATIVEIS)
            ->sum(fn ($c) => max(0, (float) $c->valor - (float) $c->valor_pago));

        $usuarioGerente = $this->ehGerente($user);

        return [
            'venda' => [
                'id'          => $venda->id,
                'numero'      => $venda->numero,
                'data'        => $venda->created_at->format('d/m/Y H:i'),
                'total'       => (float) $venda->total,
                'status'      => $venda->status->value,
                'loja'        => $venda->unidade->nome ?? null,
                'unidade_id'  => $venda->unidade_id,
                'cliente_id'  => $venda->cliente_id,
                'cliente'     => $venda->cliente->nome_razao_social ?? null,
                'ja_devolvido'=> $venda->total_devolvido,
            ],
            'itens' => $itens,
            'politica' => [
                'dias_desde_venda'   => $dias,
                'prazo_dias'         => $prazo,
                'fora_prazo'         => $foraPrazo,
                'sobra'              => $config->troca_sobra ?: 'vale',
                'permite_dinheiro'   => $config->troca_sobra === 'dinheiro',
                'vale_validade_dias' => (int) $config->troca_vale_validade_dias,
                'senha_gerente'      => (bool) $config->troca_senha_gerente,
                'usuario_e_gerente'  => $usuarioGerente,
                // O front mostra os campos de gerente só quando vai precisar
                'exige_gerente_fora_prazo' => (bool) $config->troca_senha_gerente && $foraPrazo && ! $usuarioGerente,
                'exige_gerente_dinheiro'   => (bool) $config->troca_senha_gerente && ! $usuarioGerente,
            ],
            'parcelas_abertas' => round((float) $parcelasAbertas, 2),
            'pode_trocar'      => $venda->status === StatusVenda::Concluida && $itens->sum('disponivel') > 0,
            'motivos'          => Devolucao::MOTIVOS,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Gravação                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Registra a troca/devolução.
     *
     * @param array{
     *   tipo: 'troca'|'devolucao',
     *   itens: array<int, array{venda_item_id:int, quantidade:float, retorna_estoque?:bool, estoque_id?:int|null}>,
     *   motivo: string, motivo_texto?: string|null,
     *   sobra_destino?: 'vale'|'dinheiro',
     *   gerente_email?: string|null, gerente_senha?: string|null,
     *   observacoes?: string|null
     * } $dados
     */
    public function registrar(Venda $venda, array $dados, User $user, ConfiguracaoLoja $config, int $unidadeSessao, ?int $caixaId): Devolucao
    {
        if ($venda->status !== StatusVenda::Concluida) {
            throw new \DomainException('Só é possível trocar itens de uma venda concluída. Esta venda está ' . $venda->status->label() . '.');
        }

        $situacao = $this->situacao($venda, $config, $user);
        $porItem = collect($situacao['itens'])->keyBy('venda_item_id');

        $tipo = $dados['tipo'] === 'troca' ? 'troca' : 'devolucao';
        $sobraDestino = $tipo === 'troca' ? 'vale' : ($dados['sobra_destino'] ?? 'vale');

        if ($sobraDestino === 'dinheiro' && ! $situacao['politica']['permite_dinheiro']) {
            throw new \DomainException('Esta loja devolve a sobra como crédito na loja (vale). Para devolver em dinheiro, ligue a opção em Configurações da Loja → Trocas.');
        }

        // Itens: quantidade dentro do que ainda não voltou
        $linhas = [];
        foreach ($dados['itens'] as $linha) {
            $info = $porItem->get((int) ($linha['venda_item_id'] ?? 0));
            $qtd = round((float) ($linha['quantidade'] ?? 0), 3);
            if (! $info || $qtd <= 0) {
                continue;
            }
            if ($qtd > $info['disponivel'] + 0.0005) {
                throw new \DomainException("Quantidade maior que a disponível para \"{$info['descricao']}\" (restam {$info['disponivel']}).");
            }
            $retorna = $info['e_servico'] ? false : (bool) ($linha['retorna_estoque'] ?? true);
            $linhas[] = [
                'info'            => $info,
                'quantidade'      => $qtd,
                'retorna_estoque' => $retorna,
                'estoque_id'      => $retorna ? $this->resolverEstoque($linha['estoque_id'] ?? null, $unidadeSessao) : null,
                'condicao'        => $retorna ? 'revenda' : 'avariado',
                'total'           => round($info['valor_unitario'] * $qtd, 2),
            ];
        }
        if (empty($linhas)) {
            throw new \DomainException('Marque pelo menos um item para devolver.');
        }

        $valorDevolvido = round(array_sum(array_column($linhas, 'total')), 2);

        // Política: o que exige um gerente
        $foraPrazo = $situacao['politica']['fora_prazo'];
        $motivosFora = [];
        if ($foraPrazo) {
            $motivosFora[] = "prazo de {$situacao['politica']['prazo_dias']} dias vencido ({$situacao['politica']['dias_desde_venda']} dias)";
        }
        if ($sobraDestino === 'dinheiro') {
            $motivosFora[] = 'devolução em dinheiro';
        }
        $aprovador = null;
        if ($motivosFora && $config->troca_senha_gerente) {
            $aprovador = $this->ehGerente($user)
                ? $user
                : $this->autenticarGerente($venda->empresa_id, $dados['gerente_email'] ?? null, $dados['gerente_senha'] ?? null);
        }

        // Dinheiro só sai de um caixa aberto desta loja
        $caixa = null;
        if ($sobraDestino === 'dinheiro') {
            $caixa = $caixaId ? Caixa::withoutGlobalScopes()->find($caixaId) : null;
            if (! $caixa || $caixa->status->value !== 'aberto' || (int) $caixa->unidade_id !== $unidadeSessao) {
                throw new \DomainException('Para devolver em dinheiro é preciso ter um caixa aberto nesta loja. Abra o caixa no PDV ou escolha crédito na loja (vale).');
            }
        }

        $motivoLabel = Devolucao::MOTIVOS[$dados['motivo'] ?? ''] ?? ($dados['motivo'] ?? 'Não informado');
        $motivo = trim($motivoLabel . (! empty($dados['motivo_texto']) ? ' — ' . $dados['motivo_texto'] : ''));

        return DB::transaction(function () use ($venda, $linhas, $valorDevolvido, $tipo, $sobraDestino, $foraPrazo, $motivosFora, $aprovador, $caixa, $motivo, $dados, $user, $config, $unidadeSessao, $situacao) {
            $devolucao = Devolucao::create([
                'empresa_id'           => $venda->empresa_id,
                'unidade_id'           => $unidadeSessao,
                'venda_id'             => $venda->id,
                'tipo'                 => $tipo,
                'caixa_id'             => $caixa?->id,
                'user_id'              => $user->id,
                'motivo'               => $motivo,
                'valor_estornado'      => $valorDevolvido,
                'fora_politica'        => $foraPrazo,
                'motivo_fora_politica' => $motivosFora ? implode('; ', $motivosFora) : null,
                'aprovado_por'         => $aprovador?->id,
                'status'               => 'concluida',
                'observacoes'          => $dados['observacoes'] ?? null,
            ]);

            foreach ($linhas as $l) {
                $devolucao->itens()->create([
                    'venda_item_id'   => $l['info']['venda_item_id'],
                    'produto_id'      => $l['info']['produto_id'],
                    'estoque_id'      => $l['estoque_id'],
                    'retorna_estoque' => $l['retorna_estoque'],
                    'condicao'        => $l['condicao'],
                    'quantidade'      => $l['quantidade'],
                    'valor_unitario'  => $l['info']['valor_unitario'],
                    'total'           => $l['total'],
                ]);

                // A peça volta para a loja ONDE O CLIENTE ESTÁ (sessão), não para
                // a loja da venda: é onde ela fisicamente vai parar na prateleira.
                if ($l['retorna_estoque'] && $l['info']['produto_id']) {
                    SaldoEstoque::registrar(
                        (int) $venda->empresa_id,
                        $unidadeSessao,
                        (int) $l['estoque_id'],
                        (int) $l['info']['produto_id'],
                        TipoMovimentacaoEstoque::Devolucao->value,
                        (float) $l['quantidade'],
                        [
                            'custo_unitario' => $l['info']['valor_unitario'],
                            'origem_tipo'    => Devolucao::class,
                            'origem_id'      => $devolucao->id,
                            'observacoes'    => ucfirst($tipo) . " — venda #{$venda->numero}",
                            'user_id'        => $user->id,
                        ]
                    );
                }
            }

            // Parcelas em aberto (crediário/boleto) são abatidas antes de
            // qualquer crédito: o cliente não leva vale enquanto ainda deve a venda.
            $abatido = $this->abaterParcelas($venda, $valorDevolvido, $devolucao);
            $sobra = round($valorDevolvido - $abatido, 2);

            $formaSobra = 'nenhuma';
            $vale = null;
            if ($sobra <= 0) {
                $formaSobra = $abatido > 0 ? 'parcelas' : 'nenhuma';
            } elseif ($sobraDestino === 'dinheiro') {
                $formaSobra = 'dinheiro';
                MovimentacaoCaixa::create([
                    'empresa_id'      => $venda->empresa_id,
                    'unidade_id'      => $unidadeSessao,
                    'caixa_id'        => $caixa->id,
                    'tipo'            => TipoMovimentacaoCaixa::Devolucao,
                    'valor'           => $sobra,
                    'forma_pagamento' => 'dinheiro',
                    'descricao'       => "Devolução venda #{$venda->numero}",
                    'user_id'         => $user->id,
                ]);
            } else {
                $formaSobra = 'vale';
                $validadeDias = (int) $config->troca_vale_validade_dias;
                $vale = Vale::create([
                    'empresa_id'   => $venda->empresa_id,
                    'unidade_id'   => $unidadeSessao,
                    'cliente_id'   => $venda->cliente_id,
                    'devolucao_id' => $devolucao->id,
                    'user_id'      => $user->id,
                    'codigo'       => Vale::gerarCodigo(),
                    'valor'        => $sobra,
                    'saldo'        => $sobra,
                    'validade'     => $validadeDias > 0 ? today()->addDays($validadeDias) : null,
                    'status'       => 'ativo',
                    'observacoes'  => ucfirst($tipo) . " da venda #{$venda->numero}",
                ]);
            }

            $devolucao->update([
                'forma_sobra'            => $formaSobra,
                'valor_sobra'            => max(0, $sobra),
                'valor_abatido_parcelas' => $abatido,
                'vale_id'                => $vale?->id,
            ]);

            // Tudo voltou? A venda vira "devolvida". Parcial continua concluída —
            // o histórico de trocas fica na tela da venda.
            $restante = collect($situacao['itens'])->sum('disponivel')
                - array_sum(array_column($linhas, 'quantidade'));
            if ($restante <= 0.0005) {
                $venda->update(['status' => StatusVenda::Devolvida]);
            }

            return $devolucao->fresh(['itens.produto', 'vale', 'venda', 'aprovador', 'user']);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Apoio                                                              */
    /* ------------------------------------------------------------------ */

    /** Desconto global da venda rateado sobre os itens (o item já é líquido do desconto próprio). */
    private function fatorDescontoGlobal(Venda $venda): float
    {
        $subtotal = (float) $venda->subtotal;
        $desconto = (float) $venda->desconto_valor;
        if ($subtotal <= 0 || $desconto <= 0) {
            return 1.0;
        }

        return max(0, ($subtotal - $desconto) / $subtotal);
    }

    private function resolverEstoque(?int $estoqueId, int $unidadeId): int
    {
        if ($estoqueId) {
            $ok = Estoque::withoutGlobalScopes()
                ->where('id', $estoqueId)
                ->where('unidade_id', $unidadeId)
                ->where('status', 'ativo')
                ->exists();
            if ($ok) {
                return $estoqueId;
            }
        }

        $padrao = SaldoEstoque::estoqueDeVendaId($unidadeId);
        if (! $padrao) {
            throw new \DomainException('Esta loja não tem um estoque ativo para receber a peça devolvida. Cadastre em Configurações da Loja → Estoques.');
        }

        return $padrao;
    }

    private function abaterParcelas(Venda $venda, float $valor, Devolucao $devolucao): float
    {
        $restante = $valor;
        $abatido = 0.0;

        $contas = ContaReceber::withoutGlobalScopes()
            ->where('venda_id', $venda->id)
            ->whereIn('status', ['pendente', 'vencida'])
            ->whereIn('forma_pagamento', self::FORMAS_ABATIVEIS)
            ->orderBy('vencimento')
            ->lockForUpdate()
            ->get();

        foreach ($contas as $conta) {
            if ($restante <= 0) {
                break;
            }
            $saldo = round((float) $conta->valor - (float) $conta->valor_pago, 2);
            if ($saldo <= 0) {
                continue;
            }
            $abate = min($saldo, $restante);
            $novoPago = round((float) $conta->valor_pago + $abate, 2);
            $quitada = $novoPago >= (float) $conta->valor - 0.005;
            $conta->update([
                'valor_pago'  => $novoPago,
                'status'      => $quitada ? 'paga' : $conta->status,
                'pago_em'     => $quitada ? today() : $conta->pago_em,
                'observacoes' => trim(($conta->observacoes ? $conta->observacoes . "\n" : '')
                    . 'Abatido R$ ' . number_format($abate, 2, ',', '.') . " por {$devolucao->tipoLabel()} #{$devolucao->id}"),
            ]);
            $restante = round($restante - $abate, 2);
            $abatido = round($abatido + $abate, 2);
        }

        return $abatido;
    }

    public function ehGerente(User $user): bool
    {
        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : (string) $user->perfil;

        return $user->is_admin || in_array($perfil, ['admin', 'dono', 'gerente'], true);
    }

    /** Gerente/dono da empresa autenticado pelo e-mail + senha digitados no balcão. */
    private function autenticarGerente(int $empresaId, ?string $email, ?string $senha): User
    {
        if (! $email || ! $senha) {
            throw new \DomainException('Esta troca está fora da política da loja e precisa da autorização de um gerente: informe e-mail e senha do gerente.');
        }

        $gerente = User::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('email', trim($email))
            ->where('status', 'ativo')
            ->whereIn('perfil', ['admin', 'dono', 'gerente'])
            ->first();

        if (! $gerente || ! Hash::check($senha, $gerente->password)) {
            throw new \DomainException('E-mail ou senha do gerente inválidos.');
        }

        return $gerente;
    }
}
