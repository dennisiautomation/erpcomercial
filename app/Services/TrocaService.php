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
use App\Models\Produto;
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
                if ($di->venda_item_id === null) {
                    continue; // peça sem cupom desta venda (09/09/2026) — não é item do cupom
                }
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

    /**
     * Situação para troca SEM venda no sistema (09/09/2026): mesmo formato do
     * `situacao()`, com `venda` nula e lista vazia — o caixa só bipa a peça.
     * Não há prazo a vencer nem parcela a abater; as demais regras (sobra em
     * vale ou dinheiro, gerente para dinheiro) são as da loja.
     */
    public function situacaoSemVenda(ConfiguracaoLoja $config, User $user): array
    {
        $usuarioGerente = $this->ehGerente($user);

        return [
            'venda' => null,
            'itens' => [],
            'politica' => [
                'dias_desde_venda'   => 0,
                'prazo_dias'         => (int) $config->troca_prazo_dias,
                'fora_prazo'         => false,
                'sobra'              => $config->troca_sobra ?: 'vale',
                'permite_dinheiro'   => $config->troca_sobra === 'dinheiro',
                'vale_validade_dias' => (int) $config->troca_vale_validade_dias,
                'senha_gerente'      => (bool) $config->troca_senha_gerente,
                'usuario_e_gerente'  => $usuarioGerente,
                'exige_gerente_fora_prazo' => false,
                'exige_gerente_dinheiro'   => (bool) $config->troca_senha_gerente && ! $usuarioGerente,
            ],
            'parcelas_abertas' => 0.0,
            'pode_trocar'      => true,
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
     *   itens: array<int, array{venda_item_id?:int|null, produto_id?:int|null, quantidade:float, retorna_estoque?:bool, estoque_id?:int|null}>,
     *     — linha com `venda_item_id` = item do cupom; linha só com `produto_id` = peça que NÃO
     *       está nesta venda (bipada no F6, 09/09/2026): entra pelo preço de venda atual, sem
     *       limite de quantidade e com as MESMAS regras da troca normal (decisão do Dennis).
     *   motivo: string, motivo_texto?: string|null,
     *   sobra_destino?: 'vale'|'dinheiro',
     *   gerente_email?: string|null, gerente_senha?: string|null,
     *   observacoes?: string|null
     * } $dados
     */
    public function registrar(?Venda $venda, array $dados, User $user, ConfiguracaoLoja $config, int $unidadeSessao, ?int $caixaId, ?int $empresaIdSessao = null): Devolucao
    {
        // Sem venda (09/09/2026): peça que o sistema não vendeu — só linhas por produto
        $empresaId = $venda ? (int) $venda->empresa_id : (int) $empresaIdSessao;
        if (! $empresaId) {
            throw new \DomainException('Empresa da sessão não identificada.');
        }

        if ($venda && $venda->status !== StatusVenda::Concluida) {
            throw new \DomainException('Só é possível trocar itens de uma venda concluída. Esta venda está ' . $venda->status->label() . '.');
        }

        $situacao = $venda ? $this->situacao($venda, $config, $user) : $this->situacaoSemVenda($config, $user);
        $porItem = collect($situacao['itens'])->keyBy('venda_item_id');

        $tipo = $dados['tipo'] === 'troca' ? 'troca' : 'devolucao';
        $sobraDestino = $tipo === 'troca' ? 'vale' : ($dados['sobra_destino'] ?? 'vale');

        if ($sobraDestino === 'dinheiro' && ! $situacao['politica']['permite_dinheiro']) {
            throw new \DomainException('Esta loja devolve a sobra como crédito na loja (vale). Para devolver em dinheiro, ligue a opção em Configurações da Loja → Trocas.');
        }

        // Itens: quantidade dentro do que ainda não voltou
        $linhas = [];
        $semCupom = [];
        foreach ($dados['itens'] as $linha) {
            $qtd = round((float) ($linha['quantidade'] ?? 0), 3);
            if ($qtd <= 0) {
                continue;
            }

            if (empty($linha['venda_item_id']) && ! empty($linha['produto_id'])) {
                // Peça que não está nesta venda: identifica pelo produto, vale o preço de hoje
                $info = $this->infoSemCupom($empresaId, (int) $linha['produto_id'], $config);
                $semCupom[] = $info['descricao'] . ' × ' . rtrim(rtrim(number_format($qtd, 3, ',', ''), '0'), ',');
            } else {
                $info = $porItem->get((int) ($linha['venda_item_id'] ?? 0));
                if (! $info) {
                    continue;
                }
                if ($qtd > $info['disponivel'] + 0.0005) {
                    throw new \DomainException("Quantidade maior que a disponível para \"{$info['descricao']}\" (restam {$info['disponivel']}).");
                }
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
                : $this->autenticarGerente($empresaId, $dados['gerente_email'] ?? null, $dados['gerente_senha'] ?? null);
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

        $rotuloVenda = $venda ? "venda #{$venda->numero}" : 'peça sem venda no sistema';

        return DB::transaction(function () use ($venda, $empresaId, $rotuloVenda, $linhas, $valorDevolvido, $tipo, $sobraDestino, $foraPrazo, $motivosFora, $aprovador, $caixa, $motivo, $dados, $user, $config, $unidadeSessao, $situacao, $semCupom) {
            $devolucao = Devolucao::create([
                'empresa_id'           => $empresaId,
                'unidade_id'           => $unidadeSessao,
                'venda_id'             => $venda?->id,
                'tipo'                 => $tipo,
                'caixa_id'             => $caixa?->id,
                'user_id'              => $user->id,
                'motivo'               => $motivo,
                'valor_estornado'      => $valorDevolvido,
                'fora_politica'        => $foraPrazo,
                'motivo_fora_politica' => $motivosFora ? implode('; ', $motivosFora) : null,
                'aprovado_por'         => $aprovador?->id,
                'status'               => 'concluida',
                'observacoes'          => trim(($dados['observacoes'] ?? '') . ($semCupom
                    ? "\n" . ($venda ? 'Peça(s) sem cupom desta venda' : 'Peça(s) sem venda neste sistema') . ", pelo preço de venda atual: " . implode('; ', $semCupom)
                    : '')) ?: null,
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
                        $empresaId,
                        $unidadeSessao,
                        (int) $l['estoque_id'],
                        (int) $l['info']['produto_id'],
                        TipoMovimentacaoEstoque::Devolucao->value,
                        (float) $l['quantidade'],
                        [
                            'custo_unitario' => $l['info']['valor_unitario'],
                            'origem_tipo'    => Devolucao::class,
                            'origem_id'      => $devolucao->id,
                            'observacoes'    => ucfirst($tipo) . " — {$rotuloVenda}",
                            'user_id'        => $user->id,
                        ]
                    );
                }
            }

            // Parcelas em aberto (crediário/boleto) são abatidas antes de
            // qualquer crédito: o cliente não leva vale enquanto ainda deve a venda.
            $abatimentos = [];
            $abatido = $venda ? $this->abaterParcelas($venda, $valorDevolvido, $devolucao, $abatimentos) : 0.0;
            $sobra = round($valorDevolvido - $abatido, 2);

            $formaSobra = 'nenhuma';
            $vale = null;
            if ($sobra <= 0) {
                $formaSobra = $abatido > 0 ? 'parcelas' : 'nenhuma';
            } elseif ($sobraDestino === 'dinheiro') {
                $formaSobra = 'dinheiro';
                MovimentacaoCaixa::create([
                    'empresa_id'      => $empresaId,
                    'unidade_id'      => $unidadeSessao,
                    'caixa_id'        => $caixa->id,
                    'tipo'            => TipoMovimentacaoCaixa::Devolucao,
                    'valor'           => $sobra,
                    'forma_pagamento' => 'dinheiro',
                    'descricao'       => "Devolução {$rotuloVenda}",
                    'user_id'         => $user->id,
                ]);
            } else {
                $formaSobra = 'vale';
                $validadeDias = (int) $config->troca_vale_validade_dias;
                $vale = Vale::create([
                    'empresa_id'   => $empresaId,
                    'unidade_id'   => $unidadeSessao,
                    'cliente_id'   => $venda?->cliente_id,
                    'devolucao_id' => $devolucao->id,
                    'user_id'      => $user->id,
                    'codigo'       => Vale::gerarCodigo(),
                    'valor'        => $sobra,
                    'saldo'        => $sobra,
                    'validade'     => $validadeDias > 0 ? today()->addDays($validadeDias) : null,
                    'status'       => 'ativo',
                    'observacoes'  => ucfirst($tipo) . ($venda ? " da venda #{$venda->numero}" : ' — peça sem venda no sistema'),
                ]);
            }

            $devolucao->update([
                'forma_sobra'            => $formaSobra,
                'valor_sobra'            => max(0, $sobra),
                'valor_abatido_parcelas' => $abatido,
                'abatimentos'            => $abatimentos ?: null,
                'vale_id'                => $vale?->id,
            ]);

            // Tudo voltou? A venda vira "devolvida". Parcial continua concluída —
            // o histórico de trocas fica na tela da venda.
            $restante = collect($situacao['itens'])->sum('disponivel')
                - collect($linhas)->filter(fn ($l) => $l['info']['venda_item_id'] !== null)->sum('quantidade');
            if ($venda && $restante <= 0.0005) {
                $venda->update(['status' => StatusVenda::Devolvida]);
            }

            return $devolucao->fresh(['itens.produto', 'vale', 'venda', 'aprovador', 'user']);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Apoio                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * O que a venda de fato cobrou, rateado sobre os itens.
     *
     * Desconto global desce o fator; acréscimo (juros de parcelamento e o
     * acréscimo do cartão cobrado por parte) sobe. O item já é líquido do
     * desconto próprio dele.
     *
     * 🔑 O acréscimo entrou aqui em 04/09/2026, por decisão do Dennis: quem
     * pagou 10% a mais no cartão recebe esses 10% de volta ao devolver a peça.
     * Vale também para venda parcelada com juros — que até então devolvia sem
     * eles, porque o acréscimo não estava no preço do item.
     */
    private function fatorDescontoGlobal(Venda $venda): float
    {
        $subtotal = (float) $venda->subtotal;
        $desconto = (float) $venda->desconto_valor;
        $acrescimo = (float) $venda->outras_despesas;

        if ($subtotal <= 0 || ($desconto <= 0 && $acrescimo <= 0)) {
            return 1.0;
        }

        return max(0, ($subtotal - $desconto + $acrescimo) / $subtotal);
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

    private function abaterParcelas(Venda $venda, float $valor, Devolucao $devolucao, array &$detalhe = []): float
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
            $detalhe[] = ['conta_id' => $conta->id, 'valor' => round($abate, 2)];
        }

        return $abatido;
    }

    /**
     * Peça bipada no F6 que NÃO está na venda encontrada (09/09/2026).
     *
     * Vale o preço de venda atual (tabela à vista) — não há cupom para dizer
     * quanto o cliente pagou. Sem teto de quantidade. Regras de prazo/gerente
     * são as da troca normal (decisão do Dennis: "as regras podem ser as normais").
     */
    private function infoSemCupom(int $empresaId, int $produtoId, ConfiguracaoLoja $config): array
    {
        $produto = Produto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->whereNull('deleted_at')
            ->find($produtoId);
        if (! $produto) {
            throw new \DomainException('Produto não encontrado no cadastro desta empresa.');
        }

        return [
            'venda_item_id'  => null,
            'produto_id'     => $produto->id,
            'descricao'      => $produto->descricao,
            'codigo'         => $produto->codigo_interno,
            'quantidade'     => 0.0,
            'devolvida'      => 0.0,
            'disponivel'     => INF,
            // "Preço de venda de hoje" = o preço cadastrado na regra da loja — na
            // regra do cartão, o do cartão, mesmo no produto com cartão fixo (01/11/2026)
            'valor_unitario' => round(app(TabelaPrecoService::class)->precoDoCadastro($produto, $config), 2),
            'e_servico'      => false,
            'sem_cupom'      => true,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Cancelamento (03/11/2026)                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Desfaz uma troca/devolução já registrada — tudo ou nada.
     *
     * Desfaz, na ordem: a venda nova da troca (estoque, caixa e vale dela),
     * a peça que voltou ao estoque, o que a sobra virou (vale cancelado, dinheiro
     * de volta à gaveta) e o abatimento das parcelas. Bloqueia, sem mexer em
     * nada, quando o vale já foi usado em OUTRA venda, a peça já não está no
     * estoque, não há caixa aberto para receber o dinheiro de volta, ou a venda
     * nova tem documento fiscal vivo / outra troca em cima.
     *
     * @throws \DomainException
     */
    public function cancelar(Devolucao $devolucao, User $user, ConfiguracaoLoja $config, ?string $motivo, ?string $gerenteEmail = null, ?string $gerenteSenha = null): Devolucao
    {
        $motivo = trim((string) $motivo);
        if (mb_strlen($motivo) < 3) {
            throw new \DomainException('Informe o motivo do cancelamento.');
        }

        // Autorização: mesma regra da troca fora da política (quem já é gerente/dono
        // autoriza sozinho; os demais digitam e-mail e senha de um gerente).
        $aprovador = $user;
        if ($config->troca_senha_gerente && ! $this->ehGerente($user)) {
            $aprovador = $this->autenticarGerente((int) $devolucao->empresa_id, $gerenteEmail, $gerenteSenha, 'Cancelar uma troca');
        }

        return DB::transaction(function () use ($devolucao, $user, $aprovador, $motivo) {
            $dev = Devolucao::withoutGlobalScopes()->lockForUpdate()->findOrFail($devolucao->id);
            if ($dev->status !== 'concluida') {
                throw new \DomainException('Só uma troca/devolução concluída pode ser cancelada (esta está ' . $dev->status . ').');
            }
            $dev->load(['itens', 'venda']);
            $rotulo = $dev->tipoLabel() . " #{$dev->id}";

            // 1. Venda nova da troca: sai primeiro (devolve o vale que ela gastou)
            $vendaNova = $dev->venda_nova_id ? Venda::withoutGlobalScopes()->find($dev->venda_nova_id) : null;
            if ($vendaNova && $vendaNova->status !== StatusVenda::Cancelada) {
                $outra = Devolucao::withoutGlobalScopes()
                    ->where('venda_id', $vendaNova->id)->where('status', '!=', 'cancelada')->where('id', '!=', $dev->id)->exists();
                if ($outra) {
                    throw new \DomainException("A venda nova #{$vendaNova->numero} desta troca já tem outra troca/devolução registrada. Cancele essa primeiro.");
                }
                $vendaNova->load('itens');
                app(VendaCancelamentoService::class)->cancelar($vendaNova, (int) $user->id, true);
            }

            // Caixa que recebe o dinheiro de volta (só se houver dinheiro a repor)
            $vale = $dev->vale_id ? Vale::withoutGlobalScopes()->lockForUpdate()->find($dev->vale_id) : null;
            $usosDinheiroNova = $vale && $vendaNova
                ? $vale->usos()->where('tipo', 'dinheiro')->where('venda_id', $vendaNova->id)->get()
                : collect();
            $precisaCaixa = $dev->forma_sobra === 'dinheiro' || $usosDinheiroNova->isNotEmpty();
            $caixa = null;
            if ($precisaCaixa) {
                // O caixa de onde o dinheiro saiu: o da devolução em dinheiro ou, na sobra do
                // vale devolvida pela venda nova, o da movimentação "Sobra do vale ..."
                $caixaOrigemId = $dev->caixa_id ?: ($vale
                    ? MovimentacaoCaixa::withoutGlobalScopes()->where('tipo', TipoMovimentacaoCaixa::Devolucao)
                        ->where('descricao', 'like', "Sobra do vale {$vale->codigo}%")->value('caixa_id')
                    : null);
                $original = $caixaOrigemId ? Caixa::withoutGlobalScopes()->find($caixaOrigemId) : null;
                $caixa = ($original && $original->status->value === 'aberto')
                    ? $original
                    : app(LancamentoCaixaService::class)->caixaAbertoDoUsuario((int) $dev->unidade_id, (int) $user->id);
                if (! $caixa) {
                    throw new \DomainException('O dinheiro devolvido ao cliente volta para a gaveta: abra o seu caixa nesta loja para cancelar a troca.');
                }
            }

            // 2. Peças que voltaram ao estoque saem de novo
            foreach ($dev->itens as $item) {
                if (! $item->retorna_estoque || ! $item->produto_id || ! $item->estoque_id) {
                    continue;
                }
                $saldo = SaldoEstoque::noEstoque((int) $item->estoque_id, (int) $item->produto_id);
                if ($saldo + 0.0005 < (float) $item->quantidade) {
                    $nome = $item->produto?->descricao ?? 'produto';
                    throw new \DomainException("A peça \"{$nome}\" já não está no estoque (saldo " . rtrim(rtrim(number_format($saldo, 3, ',', ''), '0'), ',')
                        . ", precisa de " . rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', ''), '0'), ',') . '): foi vendida depois da troca. Desfaça essa venda antes.');
                }
                SaldoEstoque::registrar(
                    (int) $dev->empresa_id,
                    (int) $dev->unidade_id,
                    (int) $item->estoque_id,
                    (int) $item->produto_id,
                    TipoMovimentacaoEstoque::Saida->value,
                    -(float) $item->quantidade,
                    [
                        'custo_unitario' => $item->valor_unitario,
                        'origem_tipo'    => Devolucao::class,
                        'origem_id'      => $dev->id,
                        'observacoes'    => "Cancelamento de {$rotulo}",
                        'user_id'        => $user->id,
                    ]
                );
            }

            // 3. O que a sobra virou
            if ($vale) {
                // Uso em qualquer venda que não seja a nova (líquido dos estornos) = vale gasto
                $liquidoPorVenda = [];
                foreach ($vale->usos()->get() as $uso) {
                    if ($vendaNova && (int) $uso->venda_id === (int) $vendaNova->id) {
                        continue;
                    }
                    $liquidoPorVenda[(int) $uso->venda_id] = ($liquidoPorVenda[(int) $uso->venda_id] ?? 0)
                        + ($uso->tipo === 'estorno' ? -1 : 1) * (float) $uso->valor;
                }
                $usadas = array_keys(array_filter($liquidoPorVenda, fn ($v) => $v > 0.005));
                if ($usadas) {
                    $numeros = Venda::withoutGlobalScopes()->whereIn('id', $usadas)->pluck('numero')->map(fn ($n) => '#' . $n)->implode(', ');
                    throw new \DomainException("O vale {$vale->codigo} já foi usado em outra venda ({$numeros}). Cancele essa venda (o saldo volta ao vale) e depois cancele a troca.");
                }

                // A sobra em dinheiro que a venda nova devolveu pela gaveta volta para ela
                foreach ($usosDinheiroNova as $uso) {
                    MovimentacaoCaixa::create([
                        'empresa_id'      => $dev->empresa_id,
                        'unidade_id'      => $caixa->unidade_id,
                        'caixa_id'        => $caixa->id,
                        'tipo'            => TipoMovimentacaoCaixa::Suprimento,
                        'valor'           => $uso->valor,
                        'forma_pagamento' => 'dinheiro',
                        'descricao'       => "Estorno da sobra do vale {$vale->codigo} — cancelamento de {$rotulo}",
                        'user_id'         => $user->id,
                    ]);
                    $vale->estornar((float) $uso->valor, $uso->venda_id, $user->id);
                }

                $vale->refresh();
                $vale->update([
                    'status'      => 'cancelado',
                    'observacoes' => trim(($vale->observacoes ? $vale->observacoes . "\n" : '')
                        . "Cancelado com {$rotulo} por {$user->name} em " . now()->format('d/m/Y H:i')),
                ]);
            }

            if ($dev->forma_sobra === 'dinheiro') {
                // A saída da devolução foi uma movimentação 'devolucao' (−); a volta entra como suprimento (+)
                MovimentacaoCaixa::create([
                    'empresa_id'      => $dev->empresa_id,
                    'unidade_id'      => $caixa->unidade_id,
                    'caixa_id'        => $caixa->id,
                    'tipo'            => TipoMovimentacaoCaixa::Suprimento,
                    'valor'           => $dev->valor_sobra,
                    'forma_pagamento' => 'dinheiro',
                    'descricao'       => "Estorno do dinheiro devolvido — cancelamento de {$rotulo}",
                    'user_id'         => $user->id,
                ]);
            }

            // 4. Parcelas abatidas voltam a dever
            if ((float) $dev->valor_abatido_parcelas > 0) {
                if (empty($dev->abatimentos)) {
                    throw new \DomainException('Esta troca abateu parcelas em aberto antes do registro conta a conta existir; o cancelamento automático não sabe quais. Ajuste as parcelas à mão ou fale com o suporte.');
                }
                foreach ($dev->abatimentos as $ab) {
                    $conta = ContaReceber::withoutGlobalScopes()->lockForUpdate()->find($ab['conta_id'] ?? 0);
                    if (! $conta || $conta->status === 'cancelada') {
                        continue;
                    }
                    $novoPago = max(0, round((float) $conta->valor_pago - (float) $ab['valor'], 2));
                    $conta->update([
                        'valor_pago'  => $novoPago,
                        'status'      => $novoPago >= (float) $conta->valor - 0.005 ? 'paga' : 'pendente',
                        'pago_em'     => $novoPago >= (float) $conta->valor - 0.005 ? $conta->pago_em : null,
                        'observacoes' => trim(($conta->observacoes ? $conta->observacoes . "\n" : '')
                            . 'Abatimento de R$ ' . number_format((float) $ab['valor'], 2, ',', '.') . " desfeito: cancelamento de {$rotulo}"),
                    ]);
                }
            }

            // 5. Marca a devolução e reabre a venda de origem
            $dev->update([
                'status'              => 'cancelada',
                'cancelado_por'       => $aprovador->id,
                'cancelado_em'        => now(),
                'motivo_cancelamento' => $motivo,
            ]);

            if ($dev->venda && $dev->venda->status === StatusVenda::Devolvida) {
                $dev->venda->update(['status' => StatusVenda::Concluida]);
            }

            return $dev->fresh(['itens.produto', 'vale', 'venda', 'vendaNova', 'canceladoPor']);
        });
    }

    public function ehGerente(User $user): bool
    {
        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : (string) $user->perfil;

        return $user->is_admin || in_array($perfil, ['admin', 'dono', 'gerente'], true);
    }

    /** Gerente/dono da empresa autenticado pelo e-mail + senha digitados no balcão. */
    private function autenticarGerente(int $empresaId, ?string $email, ?string $senha, string $contexto = 'Esta troca está fora da política da loja e'): User
    {
        if (! $email || ! $senha) {
            throw new \DomainException($contexto . ' precisa da autorização de um gerente: informe e-mail e senha do gerente.');
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
