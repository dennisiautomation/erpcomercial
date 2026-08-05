<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Planilha;
use Carbon\Carbon;
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
            $nome = trim((string) ($row['nome'] ?? $row['razao_social'] ?? $row['nome_razao_social'] ?? ''));

            // Sem CPF/CNPJ e sem nome não há como identificar o cliente
            if (empty($cpfCnpj) && $nome === '') return null;

            // Sem documento (comum em base migrada de outro sistema):
            // importa mesmo assim, deduplicando pelo nome exato
            $chave = ! empty($cpfCnpj)
                ? ['empresa_id' => $empresaId, 'cpf_cnpj' => $cpfCnpj]
                : ['empresa_id' => $empresaId, 'cpf_cnpj' => null, 'nome_razao_social' => $nome];

            return Cliente::updateOrCreate(
                $chave,
                [
                    'tipo_pessoa'       => strlen($cpfCnpj) > 11 ? 'pj' : 'pf',
                    'nome_razao_social' => $nome !== '' ? $nome : 'Sem nome',
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
    /**
     * Vendas históricas de outro sistema: entram como tipo 'importada' —
     * apenas histórico/relatórios, SEM movimentar estoque, caixa ou fiscal.
     */
    public function vendas(Request $request): JsonResponse
    {
        return $this->processImport($request, 'vendas', function ($row, $empresaId) {
            $data = $this->parseData($row['data'] ?? null);
            $total = $this->parseNumber($row['valor_total'] ?? $row['total'] ?? $row['valor'] ?? 0);
            if (! $data || $total <= 0) return null;

            $clienteId = $this->acharClienteId($row, $empresaId);

            $vendedorNome = trim((string) ($row['vendedor'] ?? ''));
            $vendedorId = $vendedorNome !== ''
                ? \App\Models\User::where('empresa_id', $empresaId)->where('name', $vendedorNome)->value('id')
                : null;

            $status = mb_strtolower(trim((string) ($row['status'] ?? 'concluida')));
            $cancelada = in_array($status, ['cancelada', 'cancelado']);

            $unidadeId = session('unidade_id');

            return \Illuminate\Support\Facades\DB::transaction(function () use ($row, $empresaId, $unidadeId, $data, $total, $clienteId, $vendedorId, $cancelada) {
                $ultimoNumero = Venda::withoutGlobalScopes()
                    ->where('empresa_id', $empresaId)
                    ->where('unidade_id', $unidadeId)
                    ->lockForUpdate()
                    ->max('numero');

                $numeroAntigo = trim((string) ($row['numero_antigo'] ?? $row['numero'] ?? ''));
                $obs = trim('Importada de outro sistema.'
                    . ($numeroAntigo !== '' ? " Nº original: {$numeroAntigo}." : '')
                    . ' ' . (string) ($row['observacoes'] ?? ''));

                $venda = new Venda([
                    'empresa_id'      => $empresaId,
                    'unidade_id'      => $unidadeId,
                    'numero'          => ($ultimoNumero ?? 0) + 1,
                    'cliente_id'      => $clienteId,
                    'vendedor_id'     => $vendedorId,
                    'tipo'            => 'importada',
                    'forma_pagamento' => $this->normalizarFormaPagamento((string) ($row['forma_pagamento'] ?? '')),
                    'subtotal'        => $total,
                    'desconto_valor'  => 0,
                    'total'           => $total,
                    'status'          => $cancelada ? 'cancelada' : 'concluida',
                    'observacoes'     => $obs,
                ]);
                $venda->created_at = $data;
                $venda->save();

                VendaItem::create([
                    'venda_id'       => $venda->id,
                    'produto_id'     => null,
                    'descricao'      => 'Venda importada — sistema anterior',
                    'quantidade'     => 1,
                    'preco_unitario' => $total,
                    'desconto_valor' => 0,
                    'total'          => $total,
                ]);

                return $venda;
            });
        });
    }

    public function contasReceber(Request $request): JsonResponse
    {
        return $this->processImport($request, 'contas_receber', function ($row, $empresaId) {
            $descricao = trim((string) ($row['descricao'] ?? ''));
            $valor = $this->parseNumber($row['valor'] ?? 0);
            $vencimento = $this->parseData($row['vencimento'] ?? null);
            if ($descricao === '' || $valor <= 0 || ! $vencimento) return null;

            [$parcela, $totalParcelas] = $this->parseParcela((string) ($row['parcela'] ?? ''));

            $status = mb_strtolower(trim((string) ($row['status'] ?? 'pendente')));
            $paga = in_array($status, ['paga', 'pago', 'quitada', 'quitado', 'recebida', 'recebido']);

            return ContaReceber::create([
                'empresa_id'      => $empresaId,
                'unidade_id'      => session('unidade_id'),
                'cliente_id'      => $this->acharClienteId($row, $empresaId),
                'descricao'       => $descricao,
                'valor'           => $valor,
                'valor_pago'      => $paga ? $valor : 0,
                'vencimento'      => $vencimento,
                'pago_em'         => $paga ? ($this->parseData($row['pago_em'] ?? null) ?? $vencimento) : null,
                'forma_pagamento' => $this->normalizarFormaPagamento((string) ($row['forma_pagamento'] ?? '')) ?: null,
                'parcela'         => $parcela,
                'total_parcelas'  => $totalParcelas,
                'status'          => $paga ? 'paga' : 'pendente',
                'observacoes'     => 'Importada por planilha',
            ]);
        });
    }

    /**
     * Cliente da linha: por CPF/CNPJ e, se não achar, por nome exato.
     * Devolve null sem erro — conta/venda pode ficar sem vínculo.
     */
    private function acharClienteId(array $row, int $empresaId): ?int
    {
        $doc = \App\Support\Cnpj::limparCpfCnpj(
            $row['cliente_cpf_cnpj'] ?? $row['cpf_cnpj'] ?? $row['cpf'] ?? $row['cnpj'] ?? ''
        );
        if (! empty($doc)) {
            $id = Cliente::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)->where('cpf_cnpj', $doc)->value('id');
            if ($id) return $id;
        }

        $nome = trim((string) ($row['cliente'] ?? $row['cliente_nome'] ?? ''));
        if ($nome !== '') {
            return Cliente::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)->where('nome_razao_social', $nome)->value('id');
        }

        return null;
    }

    /**
     * Datas em dd/mm/aaaa, aaaa-mm-dd ou serial do Excel (célula formatada
     * como data no .xlsx chega como número de dias desde 30/12/1899).
     */
    private function parseData($valor): ?Carbon
    {
        $valor = trim((string) $valor);
        if ($valor === '') return null;

        if (is_numeric($valor) && (float) $valor > 20000 && (float) $valor < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) (float) $valor);
        }

        foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y', 'd/m/Y H:i', 'Y-m-d H:i:s'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $valor)->startOfDay();
            } catch (\Throwable) {
                // tenta o próximo formato
            }
        }

        return null;
    }

    /** "2/5" → [2, 5]; "3" → [3, 3]; vazio → [1, 1]. */
    private function parseParcela(string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') return [1, 1];

        if (preg_match('#^(\d+)\s*/\s*(\d+)$#', $valor, $m)) {
            return [max(1, (int) $m[1]), max(1, (int) $m[2])];
        }

        $n = max(1, (int) $valor);
        return [$n, $n];
    }

    /** "Cartão de Crédito" → cartao_credito; mantém o texto snake_case se não reconhecer. */
    private function normalizarFormaPagamento(string $valor): string
    {
        $texto = $this->normalizarCabecalho($valor);
        if ($texto === '') return '';

        return match (true) {
            str_contains($texto, 'credito') => 'cartao_credito',
            str_contains($texto, 'debito') => 'cartao_debito',
            str_contains($texto, 'dinheiro') || str_contains($texto, 'especie') => 'dinheiro',
            str_contains($texto, 'pix') => 'pix',
            str_contains($texto, 'boleto') => 'boleto',
            str_contains($texto, 'transferencia') || str_contains($texto, 'ted') || str_contains($texto, 'doc') => 'transferencia',
            str_contains($texto, 'crediario') || str_contains($texto, 'prazo') || str_contains($texto, 'carne') => 'crediario',
            default => $texto,
        };
    }

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
        'vendas' => [
            'aba'     => 'Vendas',
            'colunas' => [
                'numero_antigo', 'data', 'cliente', 'cliente_cpf_cnpj', 'vendedor',
                'forma_pagamento', 'valor_total', 'status', 'observacoes',
            ],
            'exemplos' => [
                ['1042', '15/03/2026', 'João da Silva', '12345678901', 'Maria Vendedora', 'Cartão de Crédito', '150,00', 'concluida', 'Venda migrada do sistema antigo'],
                ['1043', '16/03/2026', 'Consumidor Final', '', '', 'Dinheiro', '89,90', 'concluida', ''],
            ],
        ],
        'contas_receber' => [
            'aba'     => 'Contas a Receber',
            'colunas' => [
                'cliente', 'cliente_cpf_cnpj', 'descricao', 'valor', 'vencimento',
                'parcela', 'status', 'pago_em', 'forma_pagamento',
            ],
            'exemplos' => [
                ['João da Silva', '12345678901', 'Carnê loja 2/10', '120,00', '10/09/2026', '2/10', 'pendente', '', 'crediario'],
                ['Maria Souza', '', 'Venda a prazo 1/3', '250,00', '20/08/2026', '1/3', 'paga', '18/08/2026', 'pix'],
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

    /**
     * "CPF/CNPJ" → cpf_cnpj, "Razão Social" → razao_social, "Preço Venda" → preco_venda.
     * Remove acentos e troca qualquer sequência não alfanumérica por _.
     */
    private function normalizarCabecalho(string $header): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($header, " \t\n\r\0\x0B\"")));

        return trim(preg_replace('/[^a-z0-9]+/', '_', $ascii) ?? '', '_');
    }

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

            // "CPF/CNPJ", "Razão Social" etc. viram cpf_cnpj, razao_social —
            // o snake_case puro deixava a barra e a coluna inteira era ignorada.
            $headers = array_map(
                fn($h) => $this->normalizarCabecalho((string) $h),
                array_shift($matriz)
            );

            $imported = 0;
            $errosTotal = 0;
            $puladasTotal = 0;
            $detalhes = [];       // mensagens linha a linha (erros + puladas), cap 50
            $capDetalhes = 50;

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
                    if ($result) {
                        $imported++;
                    } else {
                        // Antes a linha sumia em silêncio ("0 de 70 importadas")
                        $puladasTotal++;
                        if (count($detalhes) < $capDetalhes) {
                            $detalhes[] = "Linha " . ($i + 2) . ": pulada — campo obrigatório ausente (confira o Modelo Excel).";
                        }
                    }
                } catch (\Throwable $e) {
                    $errosTotal++;
                    if (count($detalhes) < $capDetalhes) {
                        $detalhes[] = "Linha " . ($i + 2) . ": " . $e->getMessage();
                    }
                    // sem break: o arquivo inteiro é processado (antes parava na 11ª falha)
                }
            }

            DB::commit();

            Log::warning("[Import] {$tipo}: {$imported} importadas, {$puladasTotal} puladas, {$errosTotal} com erro", [
                'detalhes' => array_slice($detalhes, 0, 20),
            ]);

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'puladas' => $puladasTotal,
                'erros_total' => $errosTotal,
                'errors' => $detalhes,
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
