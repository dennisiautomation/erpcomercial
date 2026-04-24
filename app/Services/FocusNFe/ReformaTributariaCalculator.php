<?php

namespace App\Services\FocusNFe;

use App\Models\ConfiguracaoFiscal;

/**
 * Calculadora dos tributos da Reforma Tributária (EC 132/2023).
 *
 * Tributos novos:
 *   - IBS (Imposto sobre Bens e Serviços)      : estadual/municipal
 *   - CBS (Contribuição sobre Bens e Serviços) : federal
 *   - IS  (Imposto Seletivo)                   : federal, sobre itens nocivos
 *
 * Estratégia de alíquota:
 *   1. Se o produto/serviço define alíquota própria → usa ela.
 *   2. Caso contrário, usa `*_aliquota_padrao` da ConfiguracaoFiscal.
 *   3. Se nenhum dos dois, usa a alíquota-teste legal de 2026:
 *        - CBS: 0,1%
 *        - IBS: 0,9%
 *        - IS : 0,0% (não incide na maioria dos itens)
 *
 * Em 2026 apenas cobrança-teste; valores podem ser compensados via PIS/COFINS.
 * A partir de 2027 o IS entra em vigor de verdade.
 */
class ReformaTributariaCalculator
{
    // Alíquotas-teste oficiais para 2026 (Lei Complementar 214/2025)
    public const IBS_TESTE_2026 = 0.9;
    public const CBS_TESTE_2026 = 0.1;

    public function __construct(
        private readonly ConfiguracaoFiscal $config,
    ) {}

    /**
     * Calcula os tributos da Reforma sobre um valor (base de cálculo).
     *
     * Retorna array com as partes ou null em cada chave quando a flag
     * correspondente está desligada na ConfiguracaoFiscal.
     *
     * @param  array<string, float|string|null>  $item  campos *_aliquota, cst_ibs_cbs, classificacao_ibs
     * @return array{valor_base: float, ibs: ?array, cbs: ?array, is: ?array}
     */
    public function calcular(float $valorBase, array $item = []): array
    {
        return [
            'valor_base' => $valorBase,
            'ibs' => $this->config->ibs_ativo ? $this->calcularParcela(
                $valorBase,
                $item['ibs_aliquota'] ?? null,
                (float) ($this->config->ibs_aliquota_padrao ?? self::IBS_TESTE_2026),
                $item['cst_ibs_cbs'] ?? null,
                $item['classificacao_ibs'] ?? null,
            ) : null,
            'cbs' => $this->config->cbs_ativo ? $this->calcularParcela(
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
     * Retorna a chave "RT" do payload da NF-e já pronta para concatenar
     * aos blocos de imposto. Formato alinhado com a especificação que a
     * Focus vem adotando para os campos novos (IBSBCalc, CBSBCalc, IS...).
     *
     * @return array<string, mixed>
     */
    public function blocoPayload(float $valorBase, array $item = []): array
    {
        $partes = $this->calcular($valorBase, $item);
        $payload = [];

        if ($partes['ibs']) {
            $payload['ibs'] = [
                'cst' => $partes['ibs']['cst'] ?? '000',
                'classificacao_tributaria' => $partes['ibs']['classificacao'] ?? null,
                'base_calculo' => $this->fmt($partes['ibs']['base']),
                'aliquota' => $this->fmt($partes['ibs']['aliquota']),
                'valor' => $this->fmt($partes['ibs']['valor']),
            ];
        }

        if ($partes['cbs']) {
            $payload['cbs'] = [
                'cst' => $partes['cbs']['cst'] ?? '000',
                'base_calculo' => $this->fmt($partes['cbs']['base']),
                'aliquota' => $this->fmt($partes['cbs']['aliquota']),
                'valor' => $this->fmt($partes['cbs']['valor']),
            ];
        }

        if ($partes['is'] && $partes['is']['valor'] > 0) {
            $payload['is'] = [
                'base_calculo' => $this->fmt($partes['is']['base']),
                'aliquota' => $this->fmt($partes['is']['aliquota']),
                'valor' => $this->fmt($partes['is']['valor']),
            ];
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

    private function fmt(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
