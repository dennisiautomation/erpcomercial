<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Leitura e escrita de planilhas .xlsx sem dependência externa.
 *
 * O projeto não usa PhpSpreadsheet (vendor mora só na imagem Docker e sumiria em rebuild).
 * Um .xlsx é um ZIP de XMLs — aqui geramos/lemos o subconjunto que Excel, LibreOffice,
 * Numbers e Google Sheets abrem sem reclamar.
 *
 * Escrita: strings inline (t="inlineStr"), cabeçalho em negrito e congelado, larguras
 * automáticas. Colunas "de código" (CPF/CNPJ, CEP, NCM, EAN...) saem SEMPRE como texto —
 * se virassem número o Excel comeria o zero à esquerda e transformaria EAN em notação
 * científica.
 */
class Planilha
{
    /** Cabeçalhos que nunca podem virar número no Excel (zero à esquerda / notação científica). */
    private const COLUNAS_TEXTO = [
        'cpf', 'cnpj', 'cpf_cnpj', 'cep', 'telefone', 'fone', 'tel', 'celular', 'whatsapp',
        'ie', 'im', 'inscricao_estadual', 'inscricao_municipal',
        'ncm', 'cest', 'cfop', 'cst', 'csosn', 'cst_csosn', 'cst_pis', 'cst_cofins', 'cst_ipi',
        'cst_ibs_cbs', 'classificacao_ibs', 'origem', 'codigo_lc116',
        'codigo', 'cod', 'codigo_interno', 'codigo_barras', 'ean', 'barcode', 'sku',
        'numero', 'num', 'numero_serie', 'chave_acesso', 'protocolo',
    ];

    // ─── Escrita ────────────────────────────────────────────

    /**
     * Monta o .xlsx e devolve o conteúdo binário.
     *
     * @param  array<int,string>        $cabecalhos
     * @param  array<int,array<int,mixed>> $linhas
     */
    public static function gerar(array $cabecalhos, array $linhas, string $aba = 'Dados'): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'planilha_') . '.xlsx';

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar a planilha temporária.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::relsRaiz());
        $zip->addFromString('xl/workbook.xml', self::workbook($aba));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::relsWorkbook());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($cabecalhos, $linhas));
        $zip->close();

        $conteudo = file_get_contents($tmp);
        @unlink($tmp);

        return $conteudo;
    }

    /**
     * Resposta HTTP de download já com Content-Length (sem ele o Chrome às vezes
     * engole o download de arquivo pequeno gerado em memória).
     */
    public static function download(array $cabecalhos, array $linhas, string $nomeArquivo, string $aba = 'Dados')
    {
        $conteudo = self::gerar($cabecalhos, $linhas, $aba);
        $nomeArquivo = str_ends_with(strtolower($nomeArquivo), '.xlsx') ? $nomeArquivo : $nomeArquivo . '.xlsx';

        return response($conteudo, 200, [
            'Content-Type'              => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition'       => 'attachment; filename="' . $nomeArquivo . '"',
            'Content-Length'            => (string) strlen($conteudo),
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control'             => 'no-store, no-cache, must-revalidate',
            'Pragma'                    => 'public',
            'X-Content-Type-Options'    => 'nosniff',
        ]);
    }

    // ─── Leitura ────────────────────────────────────────────

    /** Detecta .xlsx pelo magic number do ZIP (não confia na extensão do upload). */
    public static function pareceXlsx(string $caminho): bool
    {
        $fh = @fopen($caminho, 'rb');
        if (! $fh) {
            return false;
        }
        $magic = fread($fh, 2);
        fclose($fh);

        return $magic === 'PK';
    }

    /**
     * Lê a primeira aba e devolve as linhas como arrays indexados por coluna (0 = A).
     * Linhas totalmente vazias são descartadas.
     *
     * @return array<int,array<int,string>>
     */
    public static function ler(string $caminho): array
    {
        $zip = new ZipArchive();
        if ($zip->open($caminho) !== true) {
            throw new RuntimeException('Arquivo .xlsx inválido ou corrompido.');
        }

        try {
            $sharedStrings = self::sharedStrings($zip);
            $planilha      = self::xmlDaPrimeiraAba($zip);
        } finally {
            $zip->close();
        }

        $linhas = [];

        foreach ($planilha->sheetData->row ?? [] as $row) {
            $linha    = [];
            $temValor = false;

            foreach ($row->c ?? [] as $c) {
                $ref  = (string) $c['r'];
                $tipo = (string) $c['t'];
                $col  = self::indiceColuna($ref);

                if ($tipo === 'inlineStr') {
                    $valor = self::textoDe($c->is);
                } elseif ($tipo === 's') {
                    $valor = $sharedStrings[(int) $c->v] ?? '';
                } else {
                    $valor = isset($c->v) ? (string) $c->v : '';
                }

                $valor = trim($valor);
                if ($valor !== '') {
                    $temValor = true;
                }
                $linha[$col] = $valor;
            }

            if (! $temValor) {
                continue;
            }

            // normaliza buracos (células vazias não vêm no XML)
            $largura = $linha === [] ? 0 : max(array_keys($linha)) + 1;
            $linhas[] = array_map(
                fn ($i) => $linha[$i] ?? '',
                range(0, max(0, $largura - 1))
            );
        }

        return $linhas;
    }

    // ─── Internos: leitura ──────────────────────────────────

    /** @return array<int,string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $conteudo = $zip->getFromName('xl/sharedStrings.xml');
        if ($conteudo === false) {
            return [];
        }

        $xml     = self::xml($conteudo);
        $strings = [];
        foreach ($xml->si ?? [] as $si) {
            $strings[] = self::textoDe($si);
        }

        return $strings;
    }

    private static function xmlDaPrimeiraAba(ZipArchive $zip): \SimpleXMLElement
    {
        $alvo = 'xl/worksheets/sheet1.xml';

        // resolve o caminho real da 1ª aba (workbook → rels), com fallback no sheet1
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels     = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $wb   = self::xml($workbook);
            $rid  = (string) ($wb->sheets->sheet[0]['id'] ?? '');
            $relsX = self::xml($rels);
            foreach ($relsX->Relationship ?? [] as $rel) {
                if ((string) $rel['Id'] === $rid && $rid !== '') {
                    $target = ltrim((string) $rel['Target'], '/');
                    $alvo   = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }

        $conteudo = $zip->getFromName($alvo);
        if ($conteudo === false) {
            throw new RuntimeException('A planilha não tem nenhuma aba legível.');
        }

        return self::xml($conteudo);
    }

    /** Remove prefixos e declarações de namespace — cada gerador usa um prefixo diferente. */
    private static function xml(string $conteudo): \SimpleXMLElement
    {
        $limpo = preg_replace('/(<\/?)[A-Za-z0-9_.-]+:/', '$1', $conteudo);
        $limpo = preg_replace('/\s+xmlns(:[A-Za-z0-9_.-]+)?="[^"]*"/', '', $limpo);

        $xml = @simplexml_load_string($limpo);
        if ($xml === false) {
            throw new RuntimeException('Não consegui interpretar o XML da planilha.');
        }

        return $xml;
    }

    /** Concatena os <t> de um nó (texto simples ou rich text com vários runs). */
    private static function textoDe(?\SimpleXMLElement $no): string
    {
        if ($no === null) {
            return '';
        }

        $texto = '';
        foreach ($no->t ?? [] as $t) {
            $texto .= (string) $t;
        }
        foreach ($no->r ?? [] as $r) {
            foreach ($r->t ?? [] as $t) {
                $texto .= (string) $t;
            }
        }

        return $texto;
    }

    /** "BC12" → 54 (índice 0-based da coluna). */
    private static function indiceColuna(string $ref): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
        $letras = $m[1] ?? 'A';

        $indice = 0;
        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return $indice - 1;
    }

    /** 0 → "A", 26 → "AA". */
    private static function letraColuna(int $indice): string
    {
        $letra = '';
        $n     = $indice + 1;
        while ($n > 0) {
            $resto = ($n - 1) % 26;
            $letra = chr(65 + $resto) . $letra;
            $n     = intdiv($n - 1 - $resto, 26);
        }

        return $letra;
    }

    // ─── Internos: escrita ──────────────────────────────────

    private static function sheet(array $cabecalhos, array $linhas): string
    {
        $cabecalhos = array_values($cabecalhos);
        $totalCols  = count($cabecalhos);

        $cols = '';
        foreach ($cabecalhos as $i => $titulo) {
            $largura = max(12, min(42, mb_strlen((string) $titulo) + 6));
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $largura . '" customWidth="1"/>';
        }

        $xml = '<row r="1">';
        foreach ($cabecalhos as $i => $titulo) {
            $xml .= self::celula(self::letraColuna($i) . '1', (string) $titulo, true, true);
        }
        $xml .= '</row>';

        $numeroLinha = 1;
        foreach ($linhas as $linha) {
            $numeroLinha++;
            $xml .= '<row r="' . $numeroLinha . '">';
            $valores = array_values($linha);
            for ($i = 0; $i < $totalCols; $i++) {
                $valor = $valores[$i] ?? '';
                if ($valor === null || $valor === '') {
                    continue; // célula vazia não precisa existir
                }
                $texto = self::forcaTexto($cabecalhos[$i] ?? '', $valor);
                $xml .= self::celula(self::letraColuna($i) . $numeroLinha, $valor, false, $texto);
            }
            $xml .= '</row>';
        }

        $ultimaCol = self::letraColuna(max(0, $totalCols - 1));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0" tabSelected="1">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . $xml . '</sheetData>'
            . '<autoFilter ref="A1:' . $ultimaCol . '1"/>'
            . '</worksheet>';
    }

    private static function celula(string $ref, $valor, bool $cabecalho, bool $forcaTexto): string
    {
        $estilo = $cabecalho ? ' s="1"' : '';

        if (! $forcaTexto && is_numeric($valor) && ! is_bool($valor)) {
            // notação sem separador de milhar; o Excel formata na exibição
            return '<c r="' . $ref . '"' . $estilo . '><v>' . (0 + $valor) . '</v></c>';
        }

        return '<c r="' . $ref . '"' . $estilo . ' t="inlineStr"><is><t xml:space="preserve">'
            . self::escapar((string) $valor) . '</t></is></c>';
    }

    private static function forcaTexto(string $cabecalho, $valor): bool
    {
        if (! is_numeric($valor)) {
            return true;
        }

        $chave = strtolower(trim($cabecalho));

        return in_array($chave, self::COLUNAS_TEXTO, true)
            || str_starts_with((string) $valor, '0'); // preserva zero à esquerda em qualquer coluna
    }

    private static function escapar(string $texto): string
    {
        // XML 1.0 não aceita caracteres de controle
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto);

        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function relsRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(string $aba): string
    {
        // Excel: nome de aba tem 31 chars e não aceita : \ / ? * [ ]
        $nome = mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '', $aba), 0, 31) ?: 'Dados';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::escapar($nome) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function relsWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF2C3E9E"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }
}
