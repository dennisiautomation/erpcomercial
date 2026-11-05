<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConfiguracaoLoja;
use App\Models\Produto;
use App\Models\Vale;

/**
 * Tabelas de preço por forma de pagamento.
 *
 * Preço base do produto (preco_venda) = tabela Dinheiro/PIX. Débito e crédito
 * saem da regra geral (percentual em configuracoes_loja) com override por
 * produto em produto_precos. Modalidades ordenadas: dinheiro_pix < debito < credito.
 *
 * A regra `por_parte` (04/09/2026) inverte o desenho: o item fica no preço à
 * vista e o acréscimo do cartão é cobrado sobre o VALOR PAGO naquela forma —
 * ver `acrescimoSobre()`. É a única regra em que o preço do item não depende da
 * forma de pagamento, nem quando a venda tem uma forma só.
 *
 * O ATACADO é um segundo eixo, do cliente e não do pagamento: cliente marcado
 * como atacado leva o preço de atacado do produto independente da forma de
 * pagamento. Produto sem preço de atacado cadastrado cai no preço base.
 */
class TabelaPrecoService
{
    public const MODALIDADES = ['dinheiro_pix', 'debito', 'credito'];

    /**
     * Regra de split em que cada forma paga a tabela dela (04/09/2026).
     *
     * As outras três escolhem UMA tabela para a venda inteira. Nesta o item
     * fica sempre no preço à vista e o acréscimo do cartão vira valor de
     * pagamento — o mesmo caminho dos juros de parcelamento, que já sai na
     * NFC-e como `valor_outras_despesas` (vOutro) e no cupom como linha
     * própria. Ver `acrescimoSobre()`.
     */
    public const REGRA_POR_PARTE = 'por_parte';

    /** Forma de pagamento do PDV → modalidade de preço. */
    public const FORMA_MODALIDADE = [
        'dinheiro'       => 'dinheiro_pix',
        'pix'            => 'dinheiro_pix',
        'cartao_debito'  => 'debito',
        'cartao_credito' => 'credito',
        'credishop'      => 'credito', // cartão de crédito de rede própria (03/11/2026)
        // Demais formas (boleto, crediario, transferencia, vale) usam a tabela base
    ];

    /**
     * Preços das 3 modalidades (+ atacado) para um produto NA LOJA DA CONFIG, já com a
     * promoção do dia quando houver (05/11/2026). É por aqui que PDV, pedido, busca do
     * orçamento, etiqueta e troca leem o preço — a promoção vale em todos sem mexer neles.
     *
     * Promoção = preço À VISTA (Dinheiro/PIX). Se a promoção define o CARTÃO do produto (R$ ou %
     * sobre o normal — 05/11/2026), vale ele; senão o cartão leva a regra da loja por cima:
     * - regra de sempre ('avista'): débito/crédito = promo + % de acréscimo da loja;
     * - regra do cartão ('cartao'): cartão = promo ÷ (1 − desconto à vista), a mesma
     *   relação que a loja usa sem promoção.
     * Preço fixo de cartão do produto (produto_precos) não vale durante a promoção.
     * Atacado não muda. A promoção nunca aumenta um preço.
     */
    public function precosDoProduto(Produto $produto, ConfiguracaoLoja $config): array
    {
        $precos = $this->precosSemPromocao($produto, $config);
        $item = $this->promocaoDoProduto($produto, $config);

        if (! $item) {
            return $precos;
        }

        $avista = min($item->precoPromocional($precos['dinheiro_pix']), $precos['dinheiro_pix']);

        if ($item->temCartao()) {
            // Cartão definido na promoção (05/11/2026): R$ fixo (débito = crédito) ou % sobre o normal de cada um
            $debito = $item->precoCartaoPromocional($precos['debito']);
            $credito = $item->precoCartaoPromocional($precos['credito']);
        } elseif ($avista >= $precos['dinheiro_pix']) {
            return $precos;
        } elseif ($config->precoBaseCartao()) {
            $desconto = max(0.0, min(99.99, (float) $config->desconto_avista_percentual));
            $debito = $credito = round($avista / (1 - $desconto / 100), 2);
        } else {
            $debito = round($avista * (1 + ((float) $config->percentual_debito) / 100), 2);
            $credito = round($avista * (1 + ((float) $config->percentual_credito) / 100), 2);
        }

        return [
            'dinheiro_pix' => $avista,
            'debito'       => min($debito, $precos['debito']),
            'credito'      => min($credito, $precos['credito']),
            'atacado'      => $precos['atacado'],
        ];
    }

    /** Item de promoção que vale hoje para o produto na loja da config (ou null). */
    public function promocaoDoProduto(Produto $produto, ConfiguracaoLoja $config): ?\App\Models\PromocaoItem
    {
        if (! $produto->id || ! $config->empresa_id || ! $config->unidade_id) {
            return null;
        }

        return app(\App\Services\PromocaoService::class)
            ->itemVigente((int) $config->empresa_id, (int) $config->unidade_id, (int) $produto->id);
    }

    /** Preços das 3 modalidades para um produto, SEM promoção (override > regra geral > base). */
    public function precosSemPromocao(Produto $produto, ConfiguracaoLoja $config): array
    {
        $base = (float) $produto->preco_venda;

        $overrides = $produto->relationLoaded('precos')
            ? $produto->precos->pluck('valor', 'modalidade')
            : $produto->precos()->pluck('valor', 'modalidade');

        // Preço cadastrado = preço do cartão (25/10/2026, opção da loja, nasce
        // desligada): débito e crédito cobram o cadastrado e Dinheiro/PIX leva o
        // desconto. Os percentuais de acréscimo do cartão NÃO valem nesta regra.
        // Loja na regra de sempre ('avista') não passa por aqui — nada muda para ela.
        if ($config->precoBaseCartao()) {
            $desconto = max(0.0, min(100.0, (float) $config->desconto_avista_percentual));

            return [
                // Produto do formato antigo (preço FIXO no cartão, 01/11/2026): o
                // cadastrado dele já é o preço do Dinheiro/PIX — não leva desconto.
                'dinheiro_pix' => (float) ($overrides['dinheiro_pix']
                    ?? ($this->temCartaoFixo($overrides) ? $base : round($base * (1 - $desconto / 100), 2))),
                'debito'       => (float) ($overrides['debito'] ?? $base),
                'credito'      => (float) ($overrides['credito'] ?? $base),
                'atacado'      => (float) ($overrides['atacado'] ?? $base),
            ];
        }

        return [
            'dinheiro_pix' => (float) ($overrides['dinheiro_pix'] ?? $base),
            'debito'       => (float) ($overrides['debito']
                ?? round($base * (1 + ((float) $config->percentual_debito) / 100), 2)),
            'credito'      => (float) ($overrides['credito']
                ?? round($base * (1 + ((float) $config->percentual_credito) / 100), 2)),
            // Sem preço de atacado cadastrado o cliente de atacado paga o base —
            // nunca um preço inventado por regra geral.
            'atacado'      => (float) ($overrides['atacado'] ?? $base),
        ];
    }

    /**
     * O produto tem preço FIXO no cartão? É o formato de quem cadastrou na regra
     * à vista: `preco_venda` = Dinheiro/PIX e o cartão em `produto_precos`.
     * Quem cadastrou na regra do cartão tem o contrário: `preco_venda` = cartão e,
     * se quiser, o PIX fixo. Os dois formatos convivem na mesma empresa (o preço é
     * da empresa, a regra é da LOJA) e toda tela lê os dois do mesmo jeito.
     */
    public function temCartaoFixo($overrides): bool
    {
        return isset($overrides['credito']) || isset($overrides['debito']);
    }

    /**
     * O que a tela do produto mostra (lista, edição, preço inline), na regra da loja
     * — 01/11/2026. Antes a tela mostrava sempre `preco_venda` e só trocava o
     * rótulo: na STILO VINTE, ao ligar "preço do cartão", os produtos antigos
     * apareceram com o preço do PIX no campo do cartão e o PIX vazio.
     *
     * - principal: o preço do rótulo principal (cartão na regra 'cartao', Dinheiro/PIX na de sempre);
     * - secundario: o campo opcional da outra forma (PIX na regra 'cartao', cartão na de sempre),
     *   null quando ela segue a regra geral da loja.
     *
     * @return array{principal: float, secundario: ?float, rotulo_secundario: string}
     */
    public function precosParaTela(Produto $produto, ConfiguracaoLoja $config): array
    {
        $base = (float) $produto->preco_venda;
        $ov = $produto->relationLoaded('precos')
            ? $produto->precos->pluck('valor', 'modalidade')
            : $produto->precos()->pluck('valor', 'modalidade');
        $cartaoFixo = $ov['credito'] ?? $ov['debito'] ?? null;

        if ($config->precoBaseCartao()) {
            return [
                'principal'         => (float) ($cartaoFixo ?? $base),
                'secundario'        => isset($ov['dinheiro_pix']) ? (float) $ov['dinheiro_pix']
                    : ($cartaoFixo !== null ? $base : null),
                'rotulo_secundario' => 'Dinheiro/PIX',
            ];
        }

        return [
            'principal'         => (float) ($ov['dinheiro_pix'] ?? $base),
            'secundario'        => $cartaoFixo !== null ? (float) $cartaoFixo
                : (isset($ov['dinheiro_pix']) ? $base : null),
            'rotulo_secundario' => 'Cartão',
        ];
    }

    /**
     * O contrário de precosParaTela(): o que gravar a partir dos dois campos da
     * tela, SEM mudar o formato do produto — assim as lojas da mesma empresa que
     * estão na outra regra continuam cobrando o mesmo preço.
     *
     * Devolve `preco_venda` e os preços fixos (`debito`, `credito`, `dinheiro_pix`);
     * null = sem preço fixo naquela forma (apaga).
     *
     * @return array{preco_venda: float, debito: ?float, credito: ?float, dinheiro_pix: ?float}
     */
    public function gravarDaTela(Produto $produto, ConfiguracaoLoja $config, float $principal, ?float $secundario): array
    {
        $ov = $produto->exists ? $produto->precos()->pluck('valor', 'modalidade') : collect();
        $cartaoFixo = $this->temCartaoFixo($ov);

        if ($config->precoBaseCartao()) {
            if ($cartaoFixo) {
                // Formato antigo: o cartão fica no fixo e o cadastrado segue sendo o PIX
                $desconto = max(0.0, min(100.0, (float) $config->desconto_avista_percentual));

                return ['preco_venda' => $secundario ?? round($principal * (1 - $desconto / 100), 2),
                    'debito' => $principal, 'credito' => $principal, 'dinheiro_pix' => null];
            }

            return ['preco_venda' => $principal, 'debito' => null, 'credito' => null, 'dinheiro_pix' => $secundario];
        }

        if (isset($ov['dinheiro_pix']) && ! $cartaoFixo && $secundario !== null) {
            // Produto cadastrado na regra do cartão, editado numa loja à vista
            return ['preco_venda' => $secundario, 'debito' => null, 'credito' => null, 'dinheiro_pix' => $principal];
        }

        return ['preco_venda' => $principal, 'debito' => $secundario, 'credito' => $secundario, 'dinheiro_pix' => null];
    }

    /** Preço que vale o "preço cadastrado" na regra da loja (busca, orçamento, troca sem cupom). */
    public function precoDoCadastro(Produto $produto, ConfiguracaoLoja $config): float
    {
        return $this->precosDoProduto($produto, $config)[$this->modalidadeDoCadastro($config)];
    }

    /**
     * Tabela de preço que o vale compra (01/11/2026). Vale nascido de troca compra na
     * tabela da venda de onde a peça voltou: peça paga no dinheiro → a nova sai no
     * preço do dinheiro; paga no cartão → no do cartão. Antes o vale caía sempre na
     * tabela do preço cadastrado e, na regra do cartão, a troca de uma peça paga em
     * dinheiro pedia a diferença do cartão (STILO VINTE: "Faltam R$ 2,50").
     * Vale sem venda de origem (peça sem cupom) segue o preço cadastrado.
     */
    public function modalidadeDoVale(Vale $vale, ConfiguracaoLoja $config): string
    {
        $venda = $vale->devolucao?->venda;
        if (! $venda) {
            return $this->modalidadeDoCadastro($config);
        }

        $formas = collect($venda->pagamento_detalhes ?? [])->pluck('forma')->filter()->values()->all();
        if (empty($formas)) {
            $formas = [(string) $venda->forma_pagamento];
        }

        return $this->modalidadeDosPagamentos($formas, $config);
    }

    /**
     * Modalidade de preço da venda: o tipo do cliente vem antes da forma de pagamento.
     *
     * Cliente de atacado leva o preço de atacado em qualquer forma; cliente de
     * varejo (ou venda sem cliente) segue as tabelas por pagamento de sempre.
     */
    public function modalidadeDaVenda(array $formas, ConfiguracaoLoja $config, ?Cliente $cliente = null): string
    {
        if ($cliente && $cliente->tipo_preco === 'atacado') {
            return 'atacado';
        }

        return $this->modalidadeDosPagamentos($formas, $config);
    }

    /**
     * Modalidade de preço aplicável ao conjunto de formas de pagamento da venda.
     *
     * Venda simples (1 forma) segue a forma. Split segue a regra parametrizada:
     * - cartao_maior: a maior tabela entre as formas presentes
     * - sempre_menor: tabela base (dinheiro/PIX)
     * - sempre_maior: tabela crédito
     */
    public function modalidadeDosPagamentos(array $formas, ConfiguracaoLoja $config): string
    {
        // Boleto, crediário, transferência e vale usam a tabela do preço
        // cadastrado: Dinheiro/PIX na regra de sempre, o cartão na regra 'cartao'
        // (o desconto é só de Dinheiro/PIX).
        $tabelaCadastro = $this->modalidadeDoCadastro($config);
        // "modalidade:<tabela>" = vale de troca, que compra na tabela da venda de
        // origem (modalidadeDoVale, 01/11/2026). O PDV manda a mesma marcação.
        $modalidades = array_map(
            fn ($forma) => str_starts_with((string) $forma, 'modalidade:')
                ? substr((string) $forma, 11)
                : (self::FORMA_MODALIDADE[$forma] ?? $tabelaCadastro),
            $formas
        );

        // Na regra por parte o ITEM nunca muda de tabela — nem com uma forma só.
        // O acréscimo vira valor de pagamento, então o preço unitário que vai
        // para a nota e para o cupom é sempre o da etiqueta. Sem isso haveria um
        // caso de borda feio: o caixa que registra o crédito PRIMEIRO teria os
        // itens inflados e, ao acrescentar a segunda forma, o valor já digitado
        // estaria numa tabela que deixou de valer.
        if ($this->cobraPorParte($config)) {
            return 'dinheiro_pix';
        }

        if (count($formas) <= 1) {
            return $modalidades[0] ?? $tabelaCadastro;
        }

        return match ($config->regra_preco_split ?? 'cartao_maior') {
            'sempre_menor' => 'dinheiro_pix',
            'sempre_maior' => 'credito',
            default        => $this->maiorModalidade($modalidades),
        };
    }

    /**
     * Tabela que vale o preço cadastrado no produto: 'dinheiro_pix' na regra de
     * sempre, 'credito' quando a loja cadastra o preço do cartão (25/10/2026).
     */
    public function modalidadeDoCadastro(ConfiguracaoLoja $config): string
    {
        return $config->precoBaseCartao() ? 'credito' : 'dinheiro_pix';
    }

    /** A loja cobra por parte (cada forma paga a tabela dela)? */
    public function cobraPorParte(ConfiguracaoLoja $config): bool
    {
        return ($config->regra_preco_split ?? 'cartao_maior') === self::REGRA_POR_PARTE;
    }

    /**
     * Acréscimo percentual que a forma de pagamento cobra sobre a parte dela.
     *
     * Débito e crédito saem da regra geral da loja; dinheiro, PIX, boleto,
     * crediário, transferência e vale não acrescentam nada.
     *
     * ⚠️ O override por produto (`produto_precos`) NÃO entra aqui: aquilo é um
     * preço fechado do item numa modalidade, e não há como transformá-lo em
     * percentual sobre um pedaço do pagamento. Loja que usa preço próprio no
     * cartão e liga esta regra passa a cobrar o percentual geral — a tela de
     * configuração avisa, com o número de produtos afetados.
     */
    public function acrescimoDaForma(string $forma, ConfiguracaoLoja $config): float
    {
        return match (self::FORMA_MODALIDADE[$forma] ?? 'dinheiro_pix') {
            'debito'  => max(0.0, (float) $config->percentual_debito),
            'credito' => max(0.0, (float) $config->percentual_credito),
            default   => 0.0,
        };
    }

    /**
     * Acréscimo em reais sobre um valor pago numa forma.
     *
     * O valor entra em preço à vista e sai com o acréscimo somado — a mesma
     * convenção do `JurosParcelamentoService::simular()`, para o caixa nunca
     * precisar saber em que "moeda" está digitando.
     *
     * @return array{percentual:float, acrescimo:float, total:float, tem_acrescimo:bool}
     */
    public function acrescimoSobre(float $valorBase, string $forma, ConfiguracaoLoja $config): array
    {
        $valorBase = round($valorBase, 2);
        $percentual = $this->acrescimoDaForma($forma, $config);
        $total = $percentual > 0 ? round($valorBase * (1 + $percentual / 100), 2) : $valorBase;

        return [
            'percentual'    => $percentual,
            'acrescimo'     => round($total - $valorBase, 2),
            'total'         => $total,
            'tem_acrescimo' => $percentual > 0,
        ];
    }

    private function maiorModalidade(array $modalidades): string
    {
        $peso = array_flip(self::MODALIDADES); // dinheiro_pix=0, debito=1, credito=2
        $modalidades = array_values(array_filter($modalidades, fn ($m) => isset($peso[$m])));

        usort($modalidades, fn ($a, $b) => $peso[$b] <=> $peso[$a]);

        return $modalidades[0] ?? 'dinheiro_pix';
    }
}
