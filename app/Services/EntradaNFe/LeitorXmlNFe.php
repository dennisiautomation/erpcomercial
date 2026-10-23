<?php

namespace App\Services\EntradaNFe;

/**
 * Lê o XML de uma NF-e (modelo 55) de compra e devolve só o que a entrada de
 * mercadoria precisa: cabeçalho, itens com o custo de cada um e as duplicatas.
 *
 * Aceita o XML autorizado (<nfeProc>) e a <NFe> solta. O custo do item soma o
 * que a nota já rateia POR ITEM (frete, seguro, outras, IPI, ST, FCP-ST) e abate
 * o desconto — é o que a loja de fato pagou por aquela linha.
 */
class LeitorXmlNFe
{
    private const NS = 'http://www.portalfiscal.inf.br/nfe';

    /**
     * @return array{chave:string, numero:?string, serie:?string, data_emissao:?string,
     *   cnpj_emitente:?string, emitente:array, cnpj_destinatario:?string,
     *   valor_produtos:float, valor_total:float, duplicatas:array, itens:array}
     *
     * @throws \InvalidArgumentException com mensagem para o lojista
     */
    public function ler(string $xml): array
    {
        $xml = trim(preg_replace('/^\xEF\xBB\xBF/', '', $xml));
        if ($xml === '' || ! str_starts_with($xml, '<')) {
            throw new \InvalidArgumentException('O arquivo não é um XML de nota fiscal.');
        }

        $anterior = libxml_use_internal_errors(true);
        // LIBXML_NONET: nunca buscar nada na rede a partir do arquivo enviado
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if ($doc === false) {
            throw new \InvalidArgumentException('Não consegui ler o XML — o arquivo está corrompido ou incompleto.');
        }

        $doc->registerXPathNamespace('n', self::NS);
        $inf = $doc->xpath('//n:infNFe')[0] ?? null;
        if (! $inf) {
            throw new \InvalidArgumentException('Este XML não é de NF-e (não achei o bloco infNFe). Envie o XML da nota de compra.');
        }
        $inf->registerXPathNamespace('n', self::NS);

        $ide = $this->filho($inf, 'ide');
        $modelo = $this->texto($ide, 'mod');
        if ($modelo !== null && $modelo !== '55') {
            throw new \InvalidArgumentException("Este XML é de modelo {$modelo} (cupom). A entrada aceita NF-e modelo 55.");
        }

        $chave = $this->chave($doc, $inf);
        if (strlen($chave) !== 44) {
            throw new \InvalidArgumentException('Não achei a chave de acesso de 44 dígitos no XML.');
        }

        $emit = $this->filho($inf, 'emit');
        $ender = $this->filho($emit, 'enderEmit');
        $dest = $this->filho($inf, 'dest');
        $total = $this->filho($this->filho($inf, 'total'), 'ICMSTot');

        $itens = [];
        foreach ($inf->xpath('n:det') as $det) {
            $det->registerXPathNamespace('n', self::NS);
            $itens[] = $this->item($det);
        }
        if ($itens === []) {
            throw new \InvalidArgumentException('A nota não tem itens.');
        }

        $duplicatas = [];
        foreach ($inf->xpath('n:cobr/n:dup') as $dup) {
            $valor = (float) $this->texto($dup, 'vDup');
            if ($valor <= 0) {
                continue;
            }
            $duplicatas[] = [
                'numero'     => $this->texto($dup, 'nDup'),
                'vencimento' => $this->texto($dup, 'dVenc'),
                'valor'      => round($valor, 2),
            ];
        }

        $dhEmi = $this->texto($ide, 'dhEmi') ?? $this->texto($ide, 'dEmi');

        return [
            'chave'             => $chave,
            'numero'            => $this->texto($ide, 'nNF'),
            'serie'             => $this->texto($ide, 'serie'),
            'data_emissao'      => $dhEmi ? substr($dhEmi, 0, 10) : null,
            'cnpj_emitente'     => $this->texto($emit, 'CNPJ') ?? $this->texto($emit, 'CPF'),
            'emitente'          => [
                'razao_social'  => $this->texto($emit, 'xNome'),
                'nome_fantasia' => $this->texto($emit, 'xFant'),
                'logradouro'    => $this->texto($ender, 'xLgr'),
                'numero'        => $this->texto($ender, 'nro'),
                'complemento'   => $this->texto($ender, 'xCpl'),
                'bairro'        => $this->texto($ender, 'xBairro'),
                'cidade'        => $this->texto($ender, 'xMun'),
                'uf'            => $this->texto($ender, 'UF'),
                'cep'           => $this->texto($ender, 'CEP'),
                'telefone'      => $this->texto($ender, 'fone'),
            ],
            'cnpj_destinatario' => $this->texto($dest, 'CNPJ') ?? $this->texto($dest, 'CPF'),
            'valor_produtos'    => round((float) $this->texto($total, 'vProd'), 2),
            'valor_total'       => round((float) $this->texto($total, 'vNF'), 2),
            'duplicatas'        => $duplicatas,
            'itens'             => $itens,
        ];
    }

    private function item(\SimpleXMLElement $det): array
    {
        $prod = $this->filho($det, 'prod');
        $imposto = $this->filho($det, 'imposto');

        $vProd = (float) $this->texto($prod, 'vProd');
        $custo = $vProd
            + (float) $this->texto($prod, 'vFrete')
            + (float) $this->texto($prod, 'vSeg')
            + (float) $this->texto($prod, 'vOutro')
            - (float) $this->texto($prod, 'vDesc');

        if ($imposto) {
            $imposto->registerXPathNamespace('n', self::NS);
            // IPI e ST são pagos ao fornecedor junto com a mercadoria → entram no custo
            foreach (['n:IPI/n:IPITrib/n:vIPI', 'n:ICMS/*/n:vICMSST', 'n:ICMS/*/n:vFCPST'] as $caminho) {
                foreach ($imposto->xpath($caminho) as $v) {
                    $custo += (float) $v;
                }
            }
        }

        $ean = $this->texto($prod, 'cEAN');
        if (! $ean || ! ctype_digit($ean)) {
            $ean = $this->texto($prod, 'cEANTrib');
        }

        return [
            'numero_item'       => (int) $det['nItem'],
            'codigo_fornecedor' => $this->texto($prod, 'cProd'),
            'ean'               => ($ean && ctype_digit($ean)) ? $ean : null, // "SEM GTIN" fica de fora
            'descricao'         => mb_substr((string) $this->texto($prod, 'xProd'), 0, 255),
            'ncm'               => $this->texto($prod, 'NCM'),
            'cfop'              => $this->texto($prod, 'CFOP'),
            'unidade_comercial' => mb_strtoupper(mb_substr((string) $this->texto($prod, 'uCom'), 0, 10)),
            'quantidade'        => (float) $this->texto($prod, 'qCom'),
            'valor_unitario'    => (float) $this->texto($prod, 'vUnCom'),
            'valor_total'       => round($vProd, 2),
            'custo_total'       => round(max($custo, 0), 2),
        ];
    }

    private function chave(\SimpleXMLElement $doc, \SimpleXMLElement $inf): string
    {
        $id = preg_replace('/\D/', '', (string) $inf['Id']);
        if (strlen($id) === 44) {
            return $id;
        }
        $prot = $doc->xpath('//n:protNFe/n:infProt/n:chNFe')[0] ?? null;

        return preg_replace('/\D/', '', (string) $prot);
    }

    private function filho(?\SimpleXMLElement $no, string $nome): ?\SimpleXMLElement
    {
        if (! $no) {
            return null;
        }
        $no->registerXPathNamespace('n', self::NS);
        $achou = $no->xpath("n:{$nome}")[0] ?? null;
        $achou?->registerXPathNamespace('n', self::NS);

        return $achou;
    }

    private function texto(?\SimpleXMLElement $no, string $nome): ?string
    {
        $filho = $this->filho($no, $nome);
        if ($filho === null) {
            return null;
        }
        $v = trim((string) $filho);

        return $v === '' ? null : $v;
    }
}
