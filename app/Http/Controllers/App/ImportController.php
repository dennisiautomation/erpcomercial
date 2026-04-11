<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Produto;
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
            $cpfCnpj = preg_replace('/\D/', '', $row['cpf_cnpj'] ?? $row['cpf'] ?? $row['cnpj'] ?? '');
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

            return Produto::updateOrCreate(
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
        });
    }

    public function fornecedores(Request $request): JsonResponse
    {
        return $this->processImport($request, 'fornecedores', function ($row, $empresaId) {
            $cpfCnpj = preg_replace('/\D/', '', $row['cpf_cnpj'] ?? $row['cnpj'] ?? '');
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

    public function template(string $tipo)
    {
        $templates = [
            'clientes' => "cpf_cnpj;nome;nome_fantasia;cep;logradouro;numero;bairro;cidade;uf;telefone;email\n12345678901;João Silva;;01001000;Rua Exemplo;100;Centro;São Paulo;SP;11999999999;joao@email.com",
            'produtos' => "descricao;codigo_barras;unidade;preco_custo;markup;preco_venda;ncm;cfop;icms;pis;cofins\nNotebook Dell;;UN;3500;42.86;4999.90;84713012;5102;18;1.65;7.6",
            'fornecedores' => "cnpj;razao_social;nome_fantasia;cep;logradouro;numero;bairro;cidade;uf;telefone;email;contato\n12345678000190;Distribuidora Exemplo;Dist Ex;01001000;Rua Teste;200;Centro;São Paulo;SP;1143211234;contato@dist.com;João",
        ];

        $content = $templates[$tipo] ?? '';
        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=modelo_{$tipo}.csv",
        ]);
    }

    // ─── Internal ───────────────────────────────────────────

    private function processImport(Request $request, string $tipo, callable $processor): JsonResponse
    {
        $request->validate(['arquivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240']);

        $file = $request->file('arquivo');
        $empresaId = auth()->user()->empresa_id;

        if (!$empresaId) {
            return response()->json(['success' => false, 'error' => 'Empresa não identificada'], 422);
        }

        try {
            $content = file_get_contents($file->getRealPath());
            // Detect encoding and convert to UTF-8
            $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding && $encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            }
            // Remove BOM
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

            $lines = array_filter(explode("\n", $content), fn($l) => trim($l) !== '');
            if (count($lines) < 2) {
                return response()->json(['success' => false, 'error' => 'Arquivo vazio ou sem dados'], 422);
            }

            // Detect delimiter
            $firstLine = $lines[0];
            $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

            $headers = array_map(fn($h) => Str::snake(trim(strtolower($h), " \t\n\r\0\x0B\"")), str_getcsv($firstLine, $delimiter));

            $imported = 0;
            $errors = [];

            DB::beginTransaction();

            for ($i = 1; $i < count($lines); $i++) {
                try {
                    $values = str_getcsv($lines[$i], $delimiter);
                    if (count($values) < 2) continue;

                    $row = [];
                    foreach ($headers as $idx => $header) {
                        $row[$header] = trim($values[$idx] ?? '', " \t\n\r\0\x0B\"");
                    }

                    $result = $processor($row, $empresaId);
                    if ($result) $imported++;
                } catch (\Throwable $e) {
                    $errors[] = "Linha " . ($i + 1) . ": " . $e->getMessage();
                    if (count($errors) > 10) break;
                }
            }

            DB::commit();

            Log::info("[Import] {$tipo}: {$imported} importados", ['errors' => count($errors)]);

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'errors' => $errors,
                'total_lines' => count($lines) - 1,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[Import] Erro: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
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
