<?php

namespace App\Services\FocusNFe;

use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;

/**
 * Calculadora dos tributos da Reforma Tributária (EC 132/2023 + LC 214/2025).
 *
 * Tributos novos:
 *   - IBS (Imposto sobre Bens e Serviços)      : estadual/municipal
 *   - CBS (Contribuição sobre Bens e Serviços) : federal
 *   - IS  (Imposto Seletivo)                   : federal, sobre itens nocivos
 *
 * Obrigatoriedade (NT 2025.002 v1.40):
 *   - Desde 01/07/2026 os grupos IBS/CBS são exigidos em HOMOLOGAÇÃO para o
 *     regime normal (CRT=3 — Lucro Presumido/Real).
 *   - A partir de 03/08/2026 NF-e/NFC-e do regime normal SEM os grupos UB/W03
 *     são REJEITADAS em produção. Simples Nacional entra em 04/01/2027.
 *   Por isso o envio é AUTOMÁTICO para empresas do regime normal
 *   (obrigatoriaParaEmpresa) — as flags ibs_ativo/cbs_ativo da configuração
 *   fiscal passam a servir para forçar o envio também no Simples Nacional.
 *
 * Estratégia de alíquota:
 *   1. Se o produto/serviço define alíquota própria → usa ela.
 *   2. Caso contrário, usa `*_aliquota_padrao` da ConfiguracaoFiscal.
 *   3. Se nenhum dos dois, usa a alíquota-teste legal de 2026 (LC 214/2025):
 *        - CBS: 0,9%
 *        - IBS: 0,1% (integral na esfera estadual em 2026; municipal = 0)
 *        - IS : 0,0% (não incide na maioria dos itens)
 *
 * Em 2026 apenas cobrança-teste; valores podem ser compensados via PIS/COFINS.
 */
class ReformaTributariaCalculator
{
    // Alíquotas-teste oficiais para 2026 (LC 214/2025, art. 348)
    public const IBS_TESTE_2026 = 0.1;
    public const CBS_TESTE_2026 = 0.9;

    // Defaults SEFAZ para operação onerosa padrão (tabela cClassTrib RT 2025.002)
    public const CST_PADRAO = '000';
    public const CLASSIFICACAO_PADRAO = '000001';

    public function __construct(
        private readonly ConfiguracaoFiscal $config,
        private readonly bool $obrigatoria = false,
    ) {}

    /**
     * Regime normal (CRT=3) é obrigado a destacar IBS/CBS — rejeição SEFAZ a
     * partir de 03/08/2026 (NT 2025.002 v1.40). Simples só a partir de 2027.
     */
    public static function obrigatoriaParaEmpresa(?Empresa $empresa): bool
    {
        return in_array(
            $empresa?->regime_tributario?->value,
            ['lucro_presumido', 'lucro_real'],
            true
        );
    }

    /** Instância pronta considerando flags da config + obrigatoriedade do regime. */
    public static function paraEmissao(ConfiguracaoFiscal $config, ?Empresa $empresa): ?self
    {
        $obrigatoria = self::obrigatoriaParaEmpresa($empresa);

        if (! $obrigatoria && ! $config->ibs_ativo && ! $config->cbs_ativo && ! $config->is_ativo) {
            return null;
        }

        return new self($config, $obrigatoria);
    }

    /**
     * Calcula os tributos da Reforma sobre um valor (base de cálculo).
     *
     * IBS e CBS são calculados quando a empresa é do regime normal
     * (obrigatória) OU quando a flag correspondente está ligada na config.
     * IS continua exclusivamente por flag.
     *
     * @param  array<string, float|string|null>  $item  campos *_aliquota, cst_ibs_cbs, classificacao_ibs
     * @return array{valor_base: float, ibs: ?array, cbs: ?array, is: ?array}
     */
    public function calcular(float $valorBase, array $item = []): array
    {
        $emitirIbs = $this->obrigatoria || $this->config->ibs_ativo;
        $emitirCbs = $this->obrigatoria || $this->config->cbs_ativo;

        return [
            'valor_base' => $valorBase,
            'ibs' => $emitirIbs ? $this->calcularParcela(
                $valorBase,
                $item['ibs_aliquota'] ?? null,
                (float) ($this->config->ibs_aliquota_padrao ?? self::IBS_TESTE_2026),
                $item['cst_ibs_cbs'] ?? null,
                $item['classificacao_ibs'] ?? null,
            ) : null,
            'cbs' => $emitirCbs ? $this->calcularParcela(
                $valorBase,
                $item['cbs_aliquota'] ?? null,
                (float) ($this->config->cbs_aliquota_padrao ?? self::CBS_TESTE_2026),
                $item['cst_ibs_cbs'] ?? null,
                null,
            ) : null,
            'is' => $this->config->is_ativo ? $this->calcularParcela(
                $valorBase,
                $item['is_aliquota'] ?? null,
                0.0,
                null,
                null,
            ) : null,
        ];
    }

    /**
     * Campos do grupo IBS/CBS/IS no formato FLAT que a API da Focus espera
     * (referência campos.focusnfe.com.br — grupo UB da NT 2025.002), prontos
     * para mesclar direto no item da NF-e/NFC-e (ou no `servico` da NFS-e
     * nacional):
     *
     *   ibs_cbs_situacao_tributaria     (CST IBS/CBS — default 000)
     *   ibs_cbs_classificacao_tributaria (cClassTrib — default 000001)
     *   ibs_cbs_base_calculo            (vBCIBSCBS)
     *   ibs_uf_aliquota / ibs_uf_valor  (pIBSUF / vIBSUF)
     *   ibs_mun_aliquota / ibs_mun_valor (pIBSMun / vIBSMun — 0 em 2026)
     *   ibs_valor_total                 (vIBS do item)
     *   cbs_aliquota / cbs_valor        (pCBS / vCBS)
     *   is_base_calculo / is_aliquota / is_valor (Imposto Seletivo, se ativo)
     *
     * @return array<string, string>
     */
    public function blocoPayload(float $valorBase, array $item = []): array
    {
        $partes = $this->calcular($valorBase, $item);
        $payload = [];

        if ($partes['ibs'] || $partes['cbs']) {
            $cst = $partes['ibs']['cst'] ?? $partes['cbs']['cst'] ?? null;
            $classificacao = $partes['ibs']['classificacao'] ?? null;

            $payload['ibs_cbs_situacao_tributaria'] = $cst ?: self::CST_PADRAO;
            $payload['ibs_cbs_classificacao_tributaria'] = $classificacao ?: self::CLASSIFICACAO_PADRAO;
            $payload['ibs_cbs_base_calculo'] = $this->fmt($valorBase);
        }

        if ($partes['ibs']) {
            // Em 2026 o IBS de 0,1% é integralmente estadual; municipal zerado.
            $payload['ibs_uf_aliquota'] = $this->fmt($partes['ibs']['aliquota'], 4);
            $payload['ibs_uf_valor'] = $this->fmt($partes['ibs']['valor']);
            $payload['ibs_mun_aliquota'] = $this->fmt(0, 4);
            $payload['ibs_mun_valor'] = $this->fmt(0);
            $payload['ibs_valor_total'] = $this->fmt($partes['ibs']['valor']);
        }

        if ($partes['cbs']) {
            $payload['cbs_aliquota'] = $this->fmt($partes['cbs']['aliquota'], 4);
            $payload['cbs_valor'] = $this->fmt($partes['cbs']['valor']);
        }

        if ($partes['is'] && $partes['is']['valor'] > 0) {
            $payload['is_base_calculo'] = $this->fmt($partes['is']['base']);
            $payload['is_aliquota'] = $this->fmt($partes['is']['aliquota'], 4);
            $payload['is_valor'] = $this->fmt($partes['is']['valor']);
        }

        return $payload;
    }

    /**
     * @return array{base: float, aliquota: float, valor: float, cst: ?string, classificacao: ?string}
     */
    private function calcularParcela(
        float $valorBase,
        float|string|null $aliquotaItem,
        float $aliquotaPadrao,
        ?string $cst,
        ?string $classificacao,
    ): array {
        $aliquota = $aliquotaItem !== null && $aliquotaItem !== ''
            ? (float) $aliquotaItem
            : $aliquotaPadrao;

        $valor = round($valorBase * ($aliquota / 100), 2);

        return [
            'base' => $valorBase,
            'aliquota' => $aliquota,
            'valor' => $valor,
            'cst' => $cst,
            'classificacao' => $classificacao,
        ];
    }

    private function fmt(float|int $v, int $casas = 2): string
    {
        return number_format((float) $v, $casas, '.', '');
    }
}
