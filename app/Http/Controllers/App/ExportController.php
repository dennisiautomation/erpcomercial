<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\Venda;
use App\Support\Planilha;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExportController extends Controller
{
    public function clientes(Request $request)
    {
        return $this->export(
            'clientes',
            Cliente::where('empresa_id', auth()->user()->empresa_id)->get(),
            ['cpf_cnpj', 'nome_razao_social', 'nome_fantasia', 'telefone', 'email', 'cidade', 'uf', 'status'],
            $request
        );
    }

    public function produtos(Request $request)
    {
        return $this->export(
            'produtos',
            Produto::where('empresa_id', auth()->user()->empresa_id)->with('precos')->get(),
            ['codigo_interno', 'codigo_barras', 'descricao', 'unidade_medida', 'preco_custo', 'preco_venda', 'preco_debito', 'preco_credito', 'ncm', 'cfop', 'status'],
            $request
        );
    }

    public function fornecedores(Request $request)
    {
        return $this->export(
            'fornecedores',
            Fornecedor::where('empresa_id', auth()->user()->empresa_id)->get(),
            ['cpf_cnpj', 'razao_social', 'telefone', 'email', 'cidade', 'uf'],
            $request
        );
    }

    public function vendas(Request $request)
    {
        $query = Venda::where('empresa_id', auth()->user()->empresa_id);

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        return $this->export(
            'vendas',
            $query->get(),
            ['numero', 'status', 'forma_pagamento', 'subtotal', 'desconto_valor', 'total', 'created_at'],
            $request
        );
    }

    public function contasReceber(Request $request)
    {
        return $this->export(
            'contas_receber',
            ContaReceber::where('empresa_id', auth()->user()->empresa_id)->get(),
            ['descricao', 'valor', 'valor_pago', 'vencimento', 'pago_em', 'forma_pagamento', 'parcela', 'total_parcelas', 'status'],
            $request
        );
    }

    public function contasPagar(Request $request)
    {
        return $this->export(
            'contas_pagar',
            ContaPagar::where('empresa_id', auth()->user()->empresa_id)->get(),
            ['descricao', 'valor', 'valor_pago', 'vencimento', 'pago_em', 'categoria', 'forma_pagamento', 'parcela', 'total_parcelas', 'status'],
            $request
        );
    }

    /**
     * Exporta em .xlsx (o CSV com `;` abria em coluna única no Excel/Numbers).
     * `?formato=csv` mantém o arquivo de texto para quem integra com outro sistema.
     */
    private function export(string $nome, $items, array $columns, ?Request $request = null)
    {
        $linhas = [];

        foreach ($items as $item) {
            $linha = [];
            foreach ($columns as $col) {
                $valor = $this->valorColuna($item, $col);
                if ($valor instanceof \BackedEnum) {
                    $valor = $valor->value; // enums (ex.: StatusVenda) não castam para string
                }
                if ($valor instanceof \DateTimeInterface) {
                    $valor = $valor->format('d/m/Y H:i');
                }
                $linha[] = (string) ($valor ?? '');
            }
            $linhas[] = $linha;
        }

        $arquivo = $nome . '_' . date('Y-m-d');

        if ($request && $request->query('formato') === 'csv') {
            $csv = "\xEF\xBB\xBF" . implode(';', $columns) . "\n";
            foreach ($linhas as $linha) {
                $csv .= implode(';', array_map(fn ($v) => str_replace(';', ',', $v), $linha)) . "\n";
            }

            return response($csv, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $arquivo . '.csv"',
                'Content-Length'      => (string) strlen($csv),
            ]);
        }

        return Planilha::download($columns, $linhas, $arquivo, Str::title(str_replace('_', ' ', $nome)));
    }

    /** Colunas que não existem no model (ex.: preços por modalidade vêm da relação). */
    private function valorColuna($item, string $col)
    {
        if ($col === 'preco_debito' || $col === 'preco_credito') {
            $modalidade = $col === 'preco_debito' ? 'debito' : 'credito';

            return optional($item->precos->firstWhere('modalidade', $modalidade))->valor;
        }

        return $item->$col ?? '';
    }
}
