<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\Venda;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function clientes()
    {
        return $this->export(
            'clientes',
            Cliente::where('empresa_id', auth()->user()->empresa_id)->get(),
            ['cpf_cnpj', 'nome_razao_social', 'nome_fantasia', 'telefone', 'email', 'cidade', 'uf', 'status']
        );
    }

    public function produtos()
    {
        return $this->export(
            'produtos',
            Produto::where('empresa_id', auth()->user()->empresa_id)->get(),
            ['codigo_interno', 'codigo_barras', 'descricao', 'unidade_medida', 'preco_custo', 'preco_venda', 'ncm', 'cfop', 'status']
        );
    }

    public function fornecedores()
    {
        return $this->export(
            'fornecedores',
            Fornecedor::where('empresa_id', auth()->user()->empresa_id)->get(),
            ['cpf_cnpj', 'razao_social', 'telefone', 'email', 'cidade', 'uf']
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
            ['numero', 'status', 'forma_pagamento', 'subtotal', 'desconto_valor', 'total', 'created_at']
        );
    }

    public function contasReceber()
    {
        return $this->export(
            'contas_receber',
            ContaReceber::where('empresa_id', auth()->user()->empresa_id)->get(),
            ['descricao', 'valor', 'valor_pago', 'vencimento', 'pago_em', 'forma_pagamento', 'parcela', 'total_parcelas', 'status']
        );
    }

    public function contasPagar()
    {
        return $this->export(
            'contas_pagar',
            ContaPagar::where('empresa_id', auth()->user()->empresa_id)->get(),
            ['descricao', 'valor', 'valor_pago', 'vencimento', 'pago_em', 'categoria', 'forma_pagamento', 'parcela', 'total_parcelas', 'status']
        );
    }

    private function export(string $nome, $items, array $columns)
    {
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel compatibility
        $csv .= implode(';', $columns) . "\n";

        foreach ($items as $item) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = str_replace(';', ',', (string) ($item->$col ?? ''));
            }
            $csv .= implode(';', $row) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$nome}_" . date('Y-m-d') . ".csv",
        ]);
    }
}
