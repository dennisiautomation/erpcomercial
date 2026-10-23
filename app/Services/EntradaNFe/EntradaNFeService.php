<?php

namespace App\Services\EntradaNFe;

use App\Models\ContaPagar;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fornecedor;
use App\Models\NFeEntrada;
use App\Models\NFeEntradaItem;
use App\Models\NFeRecebida;
use App\Models\Produto;
use App\Models\ProdutoFornecedorCodigo;
use App\Models\Unidade;
use App\Services\FiscalAutoConfig;
use App\Services\SaldoEstoque;
use App\Support\Cnpj;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Entrada de mercadoria pela NF-e de compra (23/10/2026) — ponto único.
 *
 * 1. criarRascunho(): lê o XML, guarda o arquivo e casa cada item com um produto
 *    (código do fornecedor já visto → código de barras → "criar novo").
 * 2. concluir(): com o que o lojista conferiu, lança a entrada no estoque, atualiza
 *    o custo (opcional), cria o fornecedor se faltar, gera as contas a pagar
 *    (opcional) e lembra o código do fornecedor para a próxima nota.
 *
 * Tudo que mexe em estoque passa por SaldoEstoque::registrar (cadeia por estoque).
 */
class EntradaNFeService
{
    /** Unidades de medida que o cadastro de produto aceita (enum da coluna). */
    private const UNIDADES = ['UN', 'KG', 'CX', 'PCT', 'LT', 'MT', 'M2', 'M3', 'PAR', 'JG'];

    public function __construct(private LeitorXmlNFe $leitor = new LeitorXmlNFe) {}

    /**
     * @throws \InvalidArgumentException com mensagem para o lojista
     */
    public function criarRascunho(string $xml, int $unidadeId, ?int $userId = null): NFeEntrada
    {
        $dados = $this->leitor->ler($xml);

        $unidade = Unidade::withoutGlobalScopes()->findOrFail($unidadeId);
        $empresaId = (int) $unidade->empresa_id;

        $existente = NFeEntrada::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('chave_acesso', $dados['chave'])
            ->first();

        if ($existente) {
            if ($existente->concluida()) {
                $quando = $existente->concluida_em?->timezone('America/Sao_Paulo')->format('d/m/Y H:i');
                throw new \InvalidArgumentException(
                    "Esta nota (nº {$existente->numero}) já deu entrada no estoque em {$quando}. Não dá para lançar duas vezes."
                );
            }
            if ((int) $existente->unidade_id !== $unidadeId) {
                $loja = Unidade::withoutGlobalScopes()->where('id', $existente->unidade_id)->value('nome');
                throw new \InvalidArgumentException(
                    "Esta nota já está em conferência na loja {$loja}. Termine (ou descarte) por lá."
                );
            }

            return $existente; // rascunho desta loja: continua de onde parou
        }

        $cnpjEmitente = Cnpj::limpar($dados['cnpj_emitente']);
        $caminho = "nfe-entradas/{$empresaId}/{$dados['chave']}.xml";
        Storage::disk('local')->put($caminho, $xml);

        return DB::transaction(function () use ($dados, $unidadeId, $empresaId, $cnpjEmitente, $caminho, $userId) {
            $entrada = NFeEntrada::create([
                'empresa_id'        => $empresaId,
                'unidade_id'        => $unidadeId,
                'estoque_id'        => SaldoEstoque::estoqueDeVendaId($unidadeId),
                'nfe_recebida_id'   => NFeRecebida::withoutGlobalScopes()
                    ->where('empresa_id', $empresaId)
                    ->where('chave_acesso', $dados['chave'])
                    ->value('id'),
                'fornecedor_id'     => $this->fornecedorExistenteId($empresaId, $cnpjEmitente),
                'chave_acesso'      => $dados['chave'],
                'numero'            => $dados['numero'],
                'serie'             => $dados['serie'],
                'cnpj_emitente'     => $cnpjEmitente ?: null,
                'nome_emitente'     => $dados['emitente']['razao_social'],
                'cnpj_destinatario' => Cnpj::limpar($dados['cnpj_destinatario']) ?: null,
                'data_emissao'      => $dados['data_emissao'],
                'valor_produtos'    => $dados['valor_produtos'],
                'valor_total'       => $dados['valor_total'],
                'duplicatas'        => $dados['duplicatas'],
                'xml_path'          => $caminho,
                'status'            => 'rascunho',
                'user_id'           => $userId,
            ]);

            foreach ($dados['itens'] as $item) {
                [$produtoId, $fator] = $this->casar($empresaId, $cnpjEmitente, $item);

                $entrada->itens()->create($item + [
                    'acao'            => $produtoId ? 'vincular' : 'criar',
                    'produto_id'      => $produtoId,
                    'fator_conversao' => $fator,
                ]);
            }

            return $entrada;
        });
    }

    /**
     * Lança a entrada. $opcoes:
     *  - estoque_id (int), atualizar_custo (bool), gerar_conta_pagar (bool),
     *    vencimento (Y-m-d, só quando a nota não traz duplicatas)
     *  - itens: [item_id => [acao, produto_id?, fator_conversao?, preco_venda?, unidade_medida?]]
     *
     * @throws \InvalidArgumentException com mensagem para o lojista
     */
    public function concluir(NFeEntrada $entrada, array $opcoes, int $userId): NFeEntrada
    {
        return DB::transaction(function () use ($entrada, $opcoes, $userId) {
            // Trava a linha: dois cliques (ou duas abas) não lançam o estoque duas vezes
            $entrada = NFeEntrada::withoutGlobalScopes()->lockForUpdate()->findOrFail($entrada->id);
            if ($entrada->concluida()) {
                throw new \InvalidArgumentException('Esta entrada já foi concluída.');
            }

            $unidadeId = (int) $entrada->unidade_id;
            $empresaId = (int) $entrada->empresa_id;

            $estoqueId = Estoque::withoutGlobalScopes()
                ->where('id', $opcoes['estoque_id'] ?? 0)
                ->where('unidade_id', $unidadeId)
                ->where('status', 'ativo')
                ->value('id');
            if (! $estoqueId) {
                throw new \InvalidArgumentException('Escolha um estoque desta loja para receber a mercadoria.');
            }

            $atualizarCusto = (bool) ($opcoes['atualizar_custo'] ?? false);
            $fornecedor = $this->fornecedorDaNota($entrada);
            $decisoes = $opcoes['itens'] ?? [];
            $lancados = 0;

            foreach ($entrada->itens()->get() as $item) {
                $d = $decisoes[$item->id] ?? [];
                $acao = in_array($d['acao'] ?? null, ['vincular', 'criar', 'ignorar'], true) ? $d['acao'] : $item->acao;
                $fator = (float) ($d['fator_conversao'] ?? $item->fator_conversao);
                if ($fator <= 0) {
                    throw new \InvalidArgumentException("Item {$item->numero_item}: a conversão tem de ser maior que zero.");
                }
                $item->fill(['acao' => $acao, 'fator_conversao' => $fator]);

                if ($acao === 'ignorar') {
                    $item->produto_id = null;
                    $item->save();
                    continue;
                }

                if ($acao === 'criar') {
                    $produto = $this->criarProduto($empresaId, $item, $d, $fornecedor);
                } else {
                    $produto = Produto::withoutGlobalScopes()
                        ->where('empresa_id', $empresaId)
                        ->where('id', $d['produto_id'] ?? $item->produto_id)
                        ->lockForUpdate()
                        ->first();
                    if (! $produto) {
                        throw new \InvalidArgumentException(
                            "Item {$item->numero_item} ({$item->descricao}): escolha o produto, marque \"Criar novo\" ou \"Não lançar\"."
                        );
                    }
                }
                $item->produto_id = $produto->id;

                $quantidade = $item->quantidadeEntrada();
                if ($quantidade <= 0) {
                    throw new \InvalidArgumentException("Item {$item->numero_item}: quantidade zerada.");
                }
                $custoUnitario = $item->custoUnitarioEntrada();

                $mov = SaldoEstoque::registrar($empresaId, $unidadeId, $estoqueId, $produto->id, 'entrada', $quantidade, [
                    'custo_unitario' => $custoUnitario,
                    'origem_tipo'    => NFeEntrada::class,
                    'origem_id'      => $entrada->id,
                    'user_id'        => $userId,
                    'observacoes'    => mb_substr("NF-e {$entrada->numero} — {$entrada->nome_emitente}", 0, 500),
                ]);
                $item->estoque_movimentacao_id = $mov->id;
                $item->save();

                if ($atualizarCusto && $custoUnitario > 0 && $acao === 'vincular') {
                    $produto->update(['preco_custo' => $custoUnitario]);
                }

                if ($item->codigo_fornecedor && $entrada->cnpj_emitente) {
                    ProdutoFornecedorCodigo::withoutGlobalScopes()->updateOrCreate(
                        [
                            'empresa_id'        => $empresaId,
                            'cnpj_emitente'     => $entrada->cnpj_emitente,
                            'codigo_fornecedor' => $item->codigo_fornecedor,
                        ],
                        ['produto_id' => $produto->id, 'fator_conversao' => $fator]
                    );
                }
                $lancados++;
            }

            if ($lancados === 0) {
                throw new \InvalidArgumentException('Nenhum item ficou para lançar — marque ao menos um.');
            }

            $contas = ! empty($opcoes['gerar_conta_pagar'])
                ? $this->gerarContasPagar($entrada, $fornecedor, $opcoes['vencimento'] ?? null)
                : 0;

            $entrada->update([
                'estoque_id'           => $estoqueId,
                'fornecedor_id'        => $fornecedor?->id,
                'status'               => 'concluida',
                'atualizou_custo'      => $atualizarCusto,
                'contas_pagar_geradas' => $contas,
                'concluida_por'        => $userId,
                'concluida_em'         => now(),
            ]);

            return $entrada;
        });
    }

    /**
     * Item → [produto_id|null, fator]. Ordem: código deste fornecedor já visto
     * numa nota anterior; depois código de barras igual a UM produto só.
     */
    private function casar(int $empresaId, string $cnpjEmitente, array $item): array
    {
        if ($cnpjEmitente && $item['codigo_fornecedor']) {
            $vinculo = ProdutoFornecedorCodigo::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->where('cnpj_emitente', $cnpjEmitente)
                ->where('codigo_fornecedor', $item['codigo_fornecedor'])
                ->first();
            $vivo = $vinculo && Produto::withoutGlobalScopes()
                ->where('id', $vinculo->produto_id)
                ->whereNull('deleted_at')
                ->exists();
            if ($vivo) {
                return [$vinculo->produto_id, (float) $vinculo->fator_conversao];
            }
        }

        if ($item['ean']) {
            $ids = Produto::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->where('codigo_barras', $item['ean'])
                ->whereNull('deleted_at')
                ->limit(2)
                ->pluck('id');
            if ($ids->count() === 1) {
                return [$ids->first(), 1.0];
            }
        }

        return [null, 1.0];
    }

    private function fornecedorExistenteId(int $empresaId, string $cnpj): ?int
    {
        if ($cnpj === '') {
            return null;
        }

        // O cadastro guarda com ou sem máscara — compara só os caracteres
        return Fornecedor::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '/', ''), '-', '')) = ?", [$cnpj])
            ->value('id');
    }

    /** Fornecedor da nota: o já cadastrado pelo CNPJ ou um novo com os dados do XML. */
    private function fornecedorDaNota(NFeEntrada $entrada): ?Fornecedor
    {
        $cnpj = (string) $entrada->cnpj_emitente;
        $id = $this->fornecedorExistenteId((int) $entrada->empresa_id, $cnpj);
        if ($id) {
            return Fornecedor::withoutGlobalScopes()->find($id);
        }
        if ($cnpj === '') {
            return null;
        }

        $emit = (new LeitorXmlNFe)->ler(Storage::disk('local')->get($entrada->xml_path))['emitente'];

        return Fornecedor::create([
            'empresa_id'    => $entrada->empresa_id,
            'cpf_cnpj'      => strlen($cnpj) === 14 ? Cnpj::formatar($cnpj) : $cnpj,
            'razao_social'  => $emit['razao_social'] ?: $entrada->nome_emitente,
            'nome_fantasia' => $emit['nome_fantasia'],
            'cep'           => $emit['cep'],
            'logradouro'    => $emit['logradouro'],
            'numero'        => $emit['numero'],
            'complemento'   => $emit['complemento'],
            'bairro'        => $emit['bairro'],
            'cidade'        => $emit['cidade'],
            'uf'            => $emit['uf'],
            'telefone'      => $emit['telefone'],
        ]);
    }

    private function criarProduto(int $empresaId, NFeEntradaItem $item, array $d, ?Fornecedor $fornecedor): Produto
    {
        $precoVenda = (float) str_replace(',', '.', (string) ($d['preco_venda'] ?? ''));
        if ($precoVenda <= 0) {
            throw new \InvalidArgumentException(
                "Item {$item->numero_item} ({$item->descricao}): informe o preço de venda do produto novo."
            );
        }

        $unidade = strtoupper((string) ($d['unidade_medida'] ?? ''));
        if (! in_array($unidade, self::UNIDADES, true)) {
            $unidade = $this->unidadeDoXml((string) $item->unidade_comercial, (float) $item->fator_conversao);
        }

        $regime = Empresa::withoutGlobalScopes()->where('id', $empresaId)->value('regime_tributario');
        $fiscal = FiscalAutoConfig::defaults((string) (($regime instanceof \BackedEnum ? $regime->value : $regime) ?: 'simples_nacional'));

        // Mesma numeração do cadastro manual (ProdutoController::store)
        $ultimo = Produto::withoutGlobalScopes()->where('empresa_id', $empresaId)->lockForUpdate()->max('codigo_interno');
        $codigo = str_pad((string) ($ultimo ? intval($ultimo) + 1 : 1), 6, '0', STR_PAD_LEFT);

        $ean = $item->ean;
        if ($ean && Produto::withoutGlobalScopes()->where('empresa_id', $empresaId)->where('codigo_barras', $ean)->whereNull('deleted_at')->exists()) {
            $ean = null; // código de barras já é de outro produto: não duplica
        }

        return Produto::create([
            'empresa_id'     => $empresaId,
            'codigo_interno' => $codigo,
            'sku'            => 'SKU-' . $codigo,
            'codigo_barras'  => $ean ?: $this->eanInterno($empresaId, $codigo),
            'descricao'      => $item->descricao,
            'unidade_medida' => $unidade,
            'fornecedor_id'  => $fornecedor?->id,
            'ncm'            => $item->ncm,
            'preco_custo'    => $item->custoUnitarioEntrada(),
            'preco_venda'    => round($precoVenda, 2),
            'cst_csosn'      => $fiscal['cst_csosn'],
            'cfop'           => $fiscal['cfop_venda_interna'],
            'icms_aliquota'  => $fiscal['icms_aliquota'],
            'pis_aliquota'   => $fiscal['pis_aliquota'],
            'cofins_aliquota' => $fiscal['cofins_aliquota'],
            'origem'         => $fiscal['origem'],
            'status'         => 'ativo',
        ]);
    }

    /** EAN-13 de uso interno (prefixo 2), igual ao do cadastro manual. */
    private function eanInterno(int $empresaId, string $codigo): string
    {
        $base = '2' . str_pad((string) ($empresaId % 1000), 3, '0', STR_PAD_LEFT) . str_pad($codigo, 8, '0', STR_PAD_LEFT);
        $soma = 0;
        foreach (str_split($base) as $i => $digito) {
            $soma += (int) $digito * ($i % 2 === 0 ? 1 : 3);
        }

        return $base . ((10 - ($soma % 10)) % 10);
    }

    /** "UND", "PC", "PÇ"... → unidade do cadastro. Com conversão (caixa → peças) vira UN. */
    private function unidadeDoXml(string $uCom, float $fator): string
    {
        if ($fator != 1.0) {
            return 'UN';
        }
        $u = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $uCom));
        $mapa = ['UND' => 'UN', 'UNID' => 'UN', 'UNIDADE' => 'UN', 'PC' => 'UN', 'PCS' => 'UN', 'PECA' => 'UN',
            'CAIXA' => 'CX', 'PACOTE' => 'PCT', 'PT' => 'PCT', 'L' => 'LT', 'M' => 'MT', 'KIT' => 'JG', 'JOGO' => 'JG', 'PR' => 'PAR'];
        $u = $mapa[$u] ?? $u;

        return in_array($u, self::UNIDADES, true) ? $u : 'UN';
    }

    /** Uma conta por duplicata da nota; sem duplicata, uma só no vencimento escolhido. */
    private function gerarContasPagar(NFeEntrada $entrada, ?Fornecedor $fornecedor, ?string $vencimento): int
    {
        $parcelas = $entrada->duplicatas ?: [];
        if ($parcelas === []) {
            if (! $vencimento) {
                throw new \InvalidArgumentException('A nota não traz as parcelas: informe o vencimento da conta a pagar.');
            }
            $parcelas = [['numero' => null, 'vencimento' => $vencimento, 'valor' => (float) $entrada->valor_total]];
        }

        $total = count($parcelas);
        foreach (array_values($parcelas) as $i => $p) {
            ContaPagar::create([
                'empresa_id'     => $entrada->empresa_id,
                'unidade_id'     => $entrada->unidade_id,
                'fornecedor_id'  => $fornecedor?->id,
                'descricao'      => mb_substr("NF-e {$entrada->numero} — {$entrada->nome_emitente}", 0, 255),
                'valor'          => $p['valor'],
                'vencimento'     => Carbon::parse($p['vencimento'] ?: now()->addDays(30))->toDateString(),
                'categoria'      => 'Fornecedor',
                'parcela'        => $i + 1,
                'total_parcelas' => $total,
                'status'         => 'pendente',
                'observacoes'    => "Entrada por NF-e · chave {$entrada->chave_acesso}"
                    . (! empty($p['numero']) ? " · duplicata {$p['numero']}" : ''),
            ]);
        }

        return $total;
    }
}
