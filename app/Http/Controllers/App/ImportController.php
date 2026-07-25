<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Support\Planilha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function clientes(Request $request): JsonResponse
    {
        return $this->processImport($request, 'clientes', function ($row, $empresaId) {
            $cpfCnpj = \App\Support\Cnpj::limparCpfCnpj($row['cpf_cnpj'] ?? $row['cpf'] ?? $row['cnpj'] ?? '');
            if (empty($cpfCnpj)) return null;

            return Cliente::updateOrCreate(
                ['empresa_id' => $empresaId, 'cpf_cnpj' => $cpfCnpj],
                [
                    'tipo_pessoa'       => strlen($cpfCnpj) > 11 ? 'pj' : 'pf',
                    'nome_razao_social' => $row['nome'] ?? $row['razao_social'] ?? $row['nome_razao_social'] ?? 'Sem nome',
                    'nome_fantasia'     => $row['nome_fantasia'] ?? $row['fantasia'] ?? null,
                    'ie'                => $row['ie'] ?? null,
                    'cep'               => preg_replace('/\D/', '', $row['cep'] ?? '') ?: '00000000',
                    'logradouro'        => $row['logradouro'] ?? $row['endereco'] ?? $row['rua'] ?? '-',
                    'numero'            => $row['numero'] ?? $row['num'] ?? 'S/N',
                    'complemento'       => $row['complemento'] ?? null,
                    'bairro'            => $row['bairro'] ?? '-',
                    'cidade'            => $row['cidade'] ?? $row['municipio'] ?? '-',
                    'uf'                => strtoupper($row['uf'] ?? $row['estado'] ?? 'SP'),
                    'telefone'          => $row['telefone'] ?? $row['fone'] ?? $row['tel'] ?? '',
                    'whatsapp'          => $row['whatsapp'] ?? $row['celular'] ?? null,
                    'email'             => $row['email'] ?? null,
                    'limite_credito'    => $row['limite_credito'] ?? $row['limite'] ?? null,
                    'status'            => 'ativo',
                ]
            );
        });
    }

    public function produtos(Request $request): JsonResponse
    {
        return $this->processImport($request, 'produtos', function ($row, $empresaId) {
            $descricao = $row['descricao'] ?? $row['produto'] ?? $row['nome'] ?? null;
            if (empty($descricao)) return null;

            $codigoInterno = $row['codigo'] ?? $row['codigo_interno'] ?? $row['cod'] ?? null;
            if (!$codigoInterno) {
                $last = Produto::withoutGlobalScopes()->where('empresa_id', $empresaId)->max('codigo_interno');
                $codigoInterno = str_pad(($last ? intval($last) + 1 : 1), 6, '0', STR_PAD_LEFT);
            }

            $produto = Produto::updateOrCreate(
                ['empresa_id' => $empresaId, 'codigo_interno' => $codigoInterno],
                [
                    'codigo_barras'    => $row['codigo_barras'] ?? $row['ean'] ?? $row['barcode'] ?? null,
                    'sku'              => $row['sku'] ?? null,
                    'descricao'        => $descricao,
                    'descricao_detalhada' => $row['descricao_detalhada'] ?? null,
                    'unidade_medida'   => strtoupper($row['unidade'] ?? $row['unidade_medida'] ?? $row['un'] ?? 'UN'),
                    'ncm'              => $row['ncm'] ?? null,
                    'cest'             => $row['cest'] ?? null,
                    'origem'           => $row['origem'] ?? 0,
                    'preco_custo'      => $this->parseNumber($row['preco_custo'] ?? $row['custo'] ?? 0),
                    'markup'           => $this->parseNumber($row['markup'] ?? $row['margem'] ?? 0),
                    'preco_venda'      => $this->parseNumber($row['preco_venda'] ?? $row['preco'] ?? $row['venda'] ?? 0),
                    'estoque_minimo'   => intval($row['estoque_minimo'] ?? $row['minimo'] ?? 0),
                    'cfop'             => $row['cfop'] ?? null,
                    'cst_csosn'        => $row['cst'] ?? $row['csosn'] ?? $row['cst_csosn'] ?? null,
                    'icms_aliquota'    => $this->parseNumber($row['icms'] ?? $row['icms_aliquota'] ?? 0),
                    'pis_aliquota'     => $this->parseNumber($row['pis'] ?? $row['pis_aliquota'] ?? 0),
                    'cofins_aliquota'  => $this->parseNumber($row['cofins'] ?? $row['cofins_aliquota'] ?? 0),
                    'ipi_aliquota'     => $this->parseNumber($row['ipi'] ?? $row['ipi_aliquota'] ?? 0),
                    'status'           => 'ativo',
                ]
            );

            // Tabelas de preço por forma de pagamento (colunas opcionais de override)
            foreach (['debito' => 'preco_debito', 'credito' => 'preco_credito'] as $modalidade => $coluna) {
                if (! isset($row[$coluna]) || $row[$coluna] === '') {
                    continue;
                }
                $registro = $produto->precos()->where('modalidade', $modalidade)->first();
                $valor = $this->parseNumber($row[$coluna]);
                if ($registro) {
                    $registro->update(['valor' => $valor]);
                } else {
                    $produto->precos()->create([
                        'empresa_id' => $empresaId,
                        'modalidade' => $modalidade,
                        'valor'      => $valor,
                    ]);
                }
            }

            return $produto;
        });
    }

    public function fornecedores(Request $request): JsonResponse
    {
        return $this->processImport($request, 'fornecedores', function ($row, $empresaId) {
            $cpfCnpj = \App\Support\Cnpj::limparCpfCnpj($row['cpf_cnpj'] ?? $row['cnpj'] ?? '');
            if (empty($cpfCnpj)) return null;

            return Fornecedor::updateOrCreate(
                ['empresa_id' => $empresaId, 'cpf_cnpj' => $cpfCnpj],
                [
                    'razao_social'          => $row['razao_social'] ?? $row['nome'] ?? 'Sem nome',
                    'nome_fantasia'         => $row['nome_fantasia'] ?? $row['fantasia'] ?? null,
                    'cep'                   => preg_replace('/\D/', '', $row['cep'] ?? '') ?: '00000000',
                    'logradouro'            => $row['logradouro'] ?? $row['endereco'] ?? '-',
                    'numero'                => $row['numero'] ?? 'S/N',
                    'complemento'           => $row['complemento'] ?? null,
                    'bairro'                => $row['bairro'] ?? '-',
                    'cidade'                => $row['cidade'] ?? '-',
                    'uf'                    => strtoupper($row['uf'] ?? 'SP'),
                    'telefone'              => $row['telefone'] ?? $row['fone'] ?? '',
                    'email'                 => $row['email'] ?? null,
                    'contato_representante' => $row['contato'] ?? $row['representante'] ?? null,
                    'condicoes_comerciais'  => $row['condicoes'] ?? $row['condicoes_comerciais'] ?? null,
                ]
            );
        });
    }

    // ─── Template download ──────────────────────────────────

    /**
     * Modelos de planilha. Padrão: .xlsx (abre no Excel/Numbers já em colunas).
     * `?formato=csv` mantém o modelo antigo para quem prefere texto puro.
     */
    public function template(string $tipo, Request $request)
    {
        $modelo = self::MODELOS[$tipo] ?? null;

        if (! $modelo) {
            abort(404, 'Modelo de planilha não encontrado.');
        }

        $nome = 'modelo_' . $tipo;

        if ($request->query('formato') === 'csv') {
            $csv = "\xEF\xBB\xBF" . implode(';', $modelo['colunas']) . "\n";
            foreach ($modelo['exemplos'] as $linha) {
                $csv .= implode(';', array_map(fn ($v) => str_replace(';', ',', (string) $v), $linha)) . "\n";
            }

            return response($csv, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $nome . '.csv"',
                'Content-Length'      => (string) strlen($csv),
            ]);
        }

        return Planilha::download($modelo['colunas'], $modelo['exemplos'], $nome, $modelo['aba']);
    }

    /**
     * Colunas aceitas pelo importador de cada tipo + linhas de exemplo.
     * As linhas de exemplo são descartadas na importação (ver EXEMPLOS_IGNORADOS).
     */
    private const MODELOS = [
        'clientes' => [
            'aba'     => 'Clientes',
            'colunas' => [
                'cpf_cnpj', 'nome', 'nome_fantasia', 'ie', 'cep', 'logradouro', 'numero',
                'complemento', 'bairro', 'cidade', 'uf', 'telefone', 'whatsapp', 'email', 'limite_credito',
            ],
            'exemplos' => [
                ['12345678901', 'João da Silva', '', '', '01001000', 'Rua Exemplo', '100', 'Apto 12', 'Centro', 'São Paulo', 'SP', '11999999999', '11999999999', 'joao@email.com', '1000,00'],
                ['12345678000190', 'Mercado Exemplo Ltda', 'Mercado Exemplo', 'ISENTO', '13010000', 'Av. Teste', '2000', '', 'Jardim', 'Campinas', 'SP', '1932221100', '', 'contato@mercado.com', ''],
            ],
        ],
        'produtos' => [
            'aba'     => 'Produtos',
            'colunas' => [
                'descricao', 'codigo', 'codigo_barras', 'sku', 'unidade', 'ncm', 'cest', 'origem',
                'preco_custo', 'markup', 'preco_venda', 'preco_debito', 'preco_credito',
                'estoque_minimo', 'cfop', 'cst', 'icms', 'pis', 'cofins', 'ipi',
            ],
            'exemplos' => [
                ['Notebook Dell Inspiron 15', '', '7891234567895', '', 'UN', '84713012', '', '0', '3500,00', '42,86', '4999,90', '4999,90', '5199,90', '2', '5102', '102', '0', '0', '0', '0'],
                ['Camiseta Algodão P', '', '', '', 'UN', '61091000', '', '0', '18,90', '100', '37,80', '', '', '5', '5102', '102', '0', '0', '0', '0'],
            ],
        ],
        'fornecedores' => [
            'aba'     => 'Fornecedores',
            'colunas' => [
                'cnpj', 'razao_social', 'nome_fantasia', 'cep', 'logradouro', 'numero',
                'complemento', 'bairro', 'cidade', 'uf', 'telefone', 'email', 'contato', 'condicoes',
            ],
            'exemplos' => [
                ['12345678000190', 'Distribuidora Exemplo Ltda', 'Dist Exemplo', '01001000', 'Rua Teste', '200', 'Galpão 3', 'Centro', 'São Paulo', 'SP', '1143211234', 'contato@dist.com', 'João Vendas', '30/60 dias'],
            ],
        ],
    ];

    /**
     * Linha idêntica a um exemplo do modelo é descartada — o lojista costuma
     * preencher abaixo do exemplo e não apagar a linha de demonstração.
     */
    private function ehLinhaDeExemplo(string $tipo, array $row): bool
    {
        $modelo = self::MODELOS[$tipo] ?? null;
        if (! $modelo) {
            return false;
        }

        $normaliza = fn ($v) => mb_strtolower(trim((string) $v));

        foreach ($modelo['exemplos'] as $exemplo) {
            $iguais = true;
            foreach ($modelo['colunas'] as $idx => $coluna) {
                $valorExemplo = $normaliza($exemplo[$idx] ?? '');
                if ($valorExemplo === '') {
                    continue; // campo vazio no exemplo não distingue nada
                }
                if ($normaliza($row[$coluna] ?? '') !== $valorExemplo) {
                    $iguais = false;
                    break;
                }
            }
            if ($iguais) {
                return true;
            }
        }

        return false;
    }

    // ─── Internal ───────────────────────────────────────────

    private function processImport(Request $request, string $tipo, callable $processor): JsonResponse
    {
        $request->validate(['arquivo' => 'required|file|mimes:csv,txt,xlsx|max:10240'], [
            'arquivo.mimes' => 'Envie uma planilha .xlsx ou um arquivo .csv. Formatos antigos (.xls) precisam ser salvos como .xlsx.',
        ]);

        $file = $request->file('arquivo');
        $empresaId = auth()->user()->empresa_id;

        if (!$empresaId) {
            return response()->json(['success' => false, 'error' => 'Empresa não identificada'], 422);
        }

        try {
            $matriz = Planilha::pareceXlsx($file->getRealPath())
                ? Planilha::ler($file->getRealPath())
                : $this->lerCsv($file->getRealPath());

            if (count($matriz) < 2) {
                return response()->json(['success' => false, 'error' => 'Arquivo vazio ou sem dados'], 422);
            }

            $headers = array_map(
                fn($h) => Str::snake(trim(strtolower((string) $h), " \t\n\r\0\x0B\"")),
                array_shift($matriz)
            );

            $imported = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($matriz as $i => $values) {
                try {
                    if (count(array_filter($values, fn($v) => trim((string) $v) !== '')) < 2) continue;

                    $row = [];
                    foreach ($headers as $idx => $header) {
                        if ($header === '') continue;
                        $valor = trim((string) ($values[$idx] ?? ''), " \t\n\r\0\x0B\"");
                        // célula vazia vira null: string vazia em coluna decimal
                        // (limite_credito, preços) é erro 1366 no MySQL e derrubava a linha inteira
                        $row[$header] = $valor === '' ? null : $valor;
                    }

                    if ($this->ehLinhaDeExemplo($tipo, $row)) continue;

                    $result = $processor($row, $empresaId);
                    if ($result) $imported++;
                } catch (\Throwable $e) {
                    $errors[] = "Linha " . ($i + 2) . ": " . $e->getMessage();
                    if (count($errors) > 10) break;
                }
            }

            DB::commit();

            Log::info("[Import] {$tipo}: {$imported} importados", ['errors' => count($errors)]);

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'errors' => $errors,
                'total_lines' => count($matriz),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[Import] Erro: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * CSV → matriz de linhas. Detecta encoding (Excel pt-BR salva em Windows-1252),
     * remove BOM e descobre o delimitador pela linha de cabeçalho.
     *
     * @return array<int,array<int,string>>
     */
    private function lerCsv(string $caminho): array
    {
        $content = file_get_contents($caminho);

        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $content),
            fn($l) => trim($l) !== ''
        ));

        if ($lines === []) {
            return [];
        }

        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';

        return array_map(fn($l) => str_getcsv($l, $delimiter, '"', '\\'), $lines);
    }

    private function parseNumber($value): float
    {
        if (is_numeric($value)) return (float) $value;
        // Handle Brazilian format: 1.234,56
        $clean = str_replace('.', '', $value);
        $clean = str_replace(',', '.', $clean);
        return (float) $clean;
    }
}
