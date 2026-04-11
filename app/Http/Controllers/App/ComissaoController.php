<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Comissao;
use App\Models\ContaPagar;
use App\Models\Produto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComissaoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $query = Comissao::with(['vendedor', 'venda'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('vendedor_id')) {
            $query->where('user_id', $request->vendedor_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        $comissoes = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Summary
        $totalPendente = Comissao::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->sum('valor_comissao');

        $pagoMes = Comissao::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereMonth('pago_em', now()->month)
            ->whereYear('pago_em', now()->year)
            ->sum('valor_comissao');

        $totalMes = Comissao::where('empresa_id', $empresaId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('valor_comissao');

        $vendedores = User::where('empresa_id', $empresaId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('app.comissoes.index', compact(
            'comissoes', 'totalPendente', 'pagoMes', 'totalMes', 'vendedores'
        ));
    }

    public function relatorio(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $relatorio = Comissao::where('empresa_id', $empresaId)
            ->whereDate('created_at', '>=', $dataInicio)
            ->whereDate('created_at', '<=', $dataFim)
            ->with(['vendedor', 'venda.itens.produto.categoria'])
            ->get()
            ->groupBy('user_id');

        $vendedores = User::where('empresa_id', $empresaId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('app.comissoes.relatorio', compact('relatorio', 'vendedores', 'dataInicio', 'dataFim'));
    }

    public function pagar(Request $request)
    {
        $request->validate([
            'comissao_ids'   => 'required|array|min:1',
            'comissao_ids.*' => 'exists:comissoes,id',
        ]);

        $empresaId = auth()->user()->empresa_id;

        DB::transaction(function () use ($request, $empresaId) {
            $comissoes = Comissao::where('empresa_id', $empresaId)
                ->whereIn('id', $request->comissao_ids)
                ->where('status', 'pendente')
                ->get();

            foreach ($comissoes as $comissao) {
                $comissao->update([
                    'status'  => 'paga',
                    'pago_em' => now(),
                ]);
            }

            // Create conta a pagar grouped by vendedor
            $porVendedor = $comissoes->groupBy('user_id');
            foreach ($porVendedor as $userId => $comissoesVendedor) {
                $total = $comissoesVendedor->sum('valor_comissao');
                $vendedor = User::find($userId);

                ContaPagar::create([
                    'empresa_id'      => $empresaId,
                    'unidade_id'      => session('unidade_id'),
                    'descricao'       => 'Pagamento comissoes - ' . ($vendedor->name ?? 'Vendedor'),
                    'valor'           => $total,
                    'valor_pago'      => $total,
                    'vencimento'      => now(),
                    'pago_em'         => now(),
                    'categoria'       => 'Comissoes',
                    'forma_pagamento' => 'transferencia',
                    'parcela'         => 1,
                    'total_parcelas'  => 1,
                    'status'          => 'paga',
                ]);
            }
        });

        return back()->with('success', 'Comissoes pagas com sucesso!');
    }

    public function configurar()
    {
        $empresaId = auth()->user()->empresa_id;

        $vendedores = User::where('empresa_id', $empresaId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $categorias = Categoria::where('empresa_id', $empresaId)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $produtos = Produto::where('empresa_id', $empresaId)
            ->orderBy('descricao')
            ->get(['id', 'descricao']);

        // Load existing config
        $configPath = storage_path('app/comissao_config_' . $empresaId . '.json');
        $config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [
            'vendedores' => [],
            'categorias' => [],
            'produtos' => [],
        ];

        return view('app.comissoes.configurar', compact('vendedores', 'categorias', 'produtos', 'config'));
    }

    public function salvarConfiguracao(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $config = [
            'vendedores' => $request->input('vendedores', []),
            'categorias' => $request->input('categorias', []),
            'produtos'   => $request->input('produtos', []),
        ];

        $configPath = storage_path('app/comissao_config_' . $empresaId . '.json');
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

        return back()->with('success', 'Configuracao de comissoes salva com sucesso!');
    }
}
