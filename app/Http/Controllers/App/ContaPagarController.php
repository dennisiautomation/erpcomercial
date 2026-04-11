<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\Fornecedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContaPagarController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $query = ContaPagar::with('fornecedor')
            ->where('empresa_id', $empresaId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('fornecedor_id')) {
            $query->where('fornecedor_id', $request->fornecedor_id);
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('vencimento_inicio')) {
            $query->whereDate('vencimento', '>=', $request->vencimento_inicio);
        }

        if ($request->filled('vencimento_fim')) {
            $query->whereDate('vencimento', '<=', $request->vencimento_fim);
        }

        $contas = $query->orderBy('vencimento')->paginate(20)->withQueryString();

        // Totals
        $totalPendente = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->sum('valor');

        $totalVencido = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', now())
            ->sum('valor');

        $pagoMes = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereMonth('pago_em', now()->month)
            ->whereYear('pago_em', now()->year)
            ->sum('valor_pago');

        $fornecedores = Fornecedor::where('empresa_id', $empresaId)
            ->orderBy('razao_social')
            ->get(['id', 'razao_social']);

        return view('app.financeiro.contas-pagar.index', compact(
            'contas', 'totalPendente', 'totalVencido', 'pagoMes', 'fornecedores'
        ));
    }

    public function create()
    {
        $fornecedores = Fornecedor::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('razao_social')
            ->get(['id', 'razao_social']);

        return view('app.financeiro.contas-pagar.create', compact('fornecedores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fornecedor_id'      => 'required|exists:fornecedores,id',
            'descricao'          => 'required|string|max:255',
            'valor'              => 'required|numeric|min:0.01',
            'vencimento'         => 'required|date',
            'categoria'          => 'nullable|string|max:100',
            'centro_custo'       => 'nullable|string|max:100',
            'forma_pagamento'    => 'nullable|string|max:50',
            'parcelas'           => 'nullable|integer|min:1|max:48',
            'recorrente'         => 'nullable|boolean',
            'recorrencia_tipo'   => 'nullable|in:mensal,bimestral,trimestral,semestral,anual',
            'observacoes'        => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated) {
            $parcelas = $validated['parcelas'] ?? 1;
            $valorParcela = round($validated['valor'] / $parcelas, 2);
            $vencimento = Carbon::parse($validated['vencimento']);
            $recorrente = $validated['recorrente'] ?? false;

            // If recorrente, generate future parcelas (12 months by default)
            $totalGeracoes = $recorrente ? 12 : $parcelas;

            for ($i = 1; $i <= $totalGeracoes; $i++) {
                $valor = (!$recorrente && $i === $parcelas)
                    ? $validated['valor'] - ($valorParcela * ($parcelas - 1))
                    : ($recorrente ? $validated['valor'] : $valorParcela);

                ContaPagar::create([
                    'empresa_id'       => auth()->user()->empresa_id,
                    'unidade_id'       => session('unidade_id'),
                    'fornecedor_id'    => $validated['fornecedor_id'],
                    'descricao'        => $validated['descricao'],
                    'valor'            => $valor,
                    'vencimento'       => $vencimento->copy(),
                    'categoria'        => $validated['categoria'] ?? null,
                    'centro_custo'     => $validated['centro_custo'] ?? null,
                    'forma_pagamento'  => $validated['forma_pagamento'] ?? null,
                    'parcela'          => $i,
                    'total_parcelas'   => $totalGeracoes,
                    'recorrente'       => $recorrente,
                    'recorrencia_tipo' => $recorrente ? ($validated['recorrencia_tipo'] ?? 'mensal') : null,
                    'status'           => 'pendente',
                    'observacoes'      => $validated['observacoes'] ?? null,
                ]);

                // Advance date based on recurrence
                if ($recorrente) {
                    $meses = match ($validated['recorrencia_tipo'] ?? 'mensal') {
                        'bimestral'   => 2,
                        'trimestral'  => 3,
                        'semestral'   => 6,
                        'anual'       => 12,
                        default       => 1,
                    };
                    $vencimento->addMonths($meses);
                } else {
                    $vencimento->addMonth();
                }
            }
        });

        return redirect()->route('app.contas-pagar.index')
            ->with('success', 'Conta a pagar cadastrada com sucesso!');
    }

    public function show(ContaPagar $contaPagar)
    {
        $contaPagar->load('fornecedor');

        $parcelas = ContaPagar::where('empresa_id', $contaPagar->empresa_id)
            ->where('fornecedor_id', $contaPagar->fornecedor_id)
            ->where('descricao', $contaPagar->descricao)
            ->where('total_parcelas', $contaPagar->total_parcelas)
            ->orderBy('parcela')
            ->get();

        return view('app.financeiro.contas-pagar.show', compact('contaPagar', 'parcelas'));
    }

    public function baixar(ContaPagar $contaPagar)
    {
        $contaPagar->update([
            'valor_pago' => $contaPagar->valor,
            'pago_em'    => now(),
            'status'     => 'paga',
        ]);

        return back()->with('success', 'Conta marcada como paga!');
    }

    public function destroy(ContaPagar $contaPagar)
    {
        $contaPagar->delete();

        return redirect()->route('app.contas-pagar.index')
            ->with('success', 'Conta excluida com sucesso!');
    }
}
