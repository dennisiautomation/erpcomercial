<?php

namespace App\Services;

class OFXParser
{
    /**
     * Parse OFX file content and return structured data.
     */
    public function parse(string $content): array
    {
        $result = [
            'banco' => $this->extractTag($content, 'BANKID'),
            'agencia' => $this->extractTag($content, 'BRANCHID'),
            'conta' => $this->extractTag($content, 'ACCTID'),
            'saldo' => $this->extractBalance($content),
            'transacoes' => $this->extractTransactions($content),
        ];

        return $result;
    }

    /**
     * Extract transactions from OFX content.
     */
    protected function extractTransactions(string $content): array
    {
        $transactions = [];

        // Match STMTTRN blocks
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $content, $matches);

        // If no closing tags (SGML style), try alternative pattern
        if (empty($matches[1])) {
            preg_match_all('/<STMTTRN>(.*?)(?=<STMTTRN>|<\/BANKTRANLIST>|<\/STMTRS>)/si', $content, $matches);
        }

        foreach ($matches[1] ?? [] as $block) {
            $amount = (float) $this->extractTag($block, 'TRNAMT');
            $dateRaw = $this->extractTag($block, 'DTPOSTED');

            $transactions[] = [
                'data' => $this->parseDate($dateRaw),
                'descricao' => trim($this->extractTag($block, 'MEMO') ?: $this->extractTag($block, 'NAME') ?: ''),
                'valor' => abs($amount),
                'tipo' => $amount >= 0 ? 'credito' : 'debito',
                'documento' => $this->extractTag($block, 'CHECKNUM') ?: $this->extractTag($block, 'REFNUM'),
            ];
        }

        return $transactions;
    }

    /**
     * Extract balance from OFX content.
     */
    protected function extractBalance(string $content): float
    {
        // Try LEDGERBAL > BALAMT
        if (preg_match('/<LEDGERBAL>.*?<BALAMT>([\-\d\.]+)/si', $content, $match)) {
            return (float) $match[1];
        }

        return 0;
    }

    /**
     * Extract a single tag value from SGML-style OFX.
     */
    protected function extractTag(string $content, string $tag): ?string
    {
        // XML style: <TAG>value</TAG>
        if (preg_match('/<' . $tag . '>(.*?)<\/' . $tag . '>/i', $content, $match)) {
            return trim($match[1]);
        }

        // SGML style: <TAG>value\n
        if (preg_match('/<' . $tag . '>([^\r\n<]+)/i', $content, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Parse OFX date format (YYYYMMDDHHMMSS or YYYYMMDD).
     */
    protected function parseDate(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }

        // Remove timezone bracket content: 20230101120000[-3:BRT]
        $dateStr = preg_replace('/\[.*?\]/', '', $dateStr);
        $dateStr = trim($dateStr);

        if (strlen($dateStr) >= 8) {
            $year = substr($dateStr, 0, 4);
            $month = substr($dateStr, 4, 2);
            $day = substr($dateStr, 6, 2);

            return "{$year}-{$month}-{$day}";
        }

        return null;
    }
}
