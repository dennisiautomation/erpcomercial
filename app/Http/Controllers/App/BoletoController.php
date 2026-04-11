<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Boleto;
use App\Models\Cliente;
use App\Models\ContaReceber;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BoletoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $query = Boleto::with(['cliente', 'contaReceber'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('vencimento_inicio')) {
            $query->whereDate('vencimento', '>=', $request->vencimento_inicio);
        }

        if ($request->filled('vencimento_fim')) {
            $query->whereDate('vencimento', '<=', $request->vencimento_fim);
        }

        $boletos = $query->orderBy('vencimento')->paginate(20)->withQueryString();

        $totalPendente = Boleto::where('empresa_id', $empresaId)->where('status', 'pendente')->sum('valor');
        $totalVencido = Boleto::where('empresa_id', $empresaId)->where('status', 'vencido')->sum('valor');
        $totalPago = Boleto::where('empresa_id', $empresaId)->where('status', 'pago')
            ->whereMonth('pago_em', now()->month)
            ->whereYear('pago_em', now()->year)
            ->sum('valor_pago');

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->orderBy('nome_razao_social')
            ->get(['id', 'nome_razao_social']);

        return view('app.boletos.index', compact(
            'boletos', 'totalPendente', 'totalVencido', 'totalPago', 'clientes'
        ));
    }

    public function show(Boleto $boleto)
    {
        $boleto->load(['cliente', 'contaReceber', 'contrato']);

        return view('app.boletos.show', compact('boleto'));
    }

    public function gerar(Request $request)
    {
        $validated = $request->validate([
            'conta_receber_id' => 'required|exists:contas_receber,id',
        ]);

        $conta = ContaReceber::findOrFail($validated['conta_receber_id']);

        $boleto = Boleto::create([
            'empresa_id' => $conta->empresa_id,
            'unidade_id' => $conta->unidade_id,
            'conta_receber_id' => $conta->id,
            'cliente_id' => $conta->cliente_id,
            'valor' => $conta->valor,
            'vencimento' => $conta->vencimento,
            'status' => 'pendente',
        ]);

        return redirect()->route('app.boletos.show', $boleto)
            ->with('success', 'Boleto gerado com sucesso!');
    }

    public function gerarCarne(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'descricao' => 'required|string|max:255',
            'valor' => 'required|numeric|min:0.01',
            'parcelas' => 'required|integer|min:2|max:48',
            'primeiro_vencimento' => 'required|date',
            'contrato_id' => 'nullable|exists:contratos,id',
        ]);

        $empresaId = auth()->user()->empresa_id;
        $vencimento = Carbon::parse($validated['primeiro_vencimento']);
        $valorParcela = round($validated['valor'] / $validated['parcelas'], 2);

        for ($i = 1; $i <= $validated['parcelas']; $i++) {
            $valor = ($i === $validated['parcelas'])
                ? $validated['valor'] - ($valorParcela * ($validated['parcelas'] - 1))
                : $valorParcela;

            // Create conta a receber
            $conta = ContaReceber::create([
                'empresa_id' => $empresaId,
                'unidade_id' => session('unidade_id'),
                'cliente_id' => $validated['cliente_id'],
                'descricao' => $validated['descricao'] . " ({$i}/{$validated['parcelas']})",
                'valor' => $valor,
                'vencimento' => $vencimento->copy(),
                'forma_pagamento' => 'boleto',
                'parcela' => $i,
                'total_parcelas' => $validated['parcelas'],
                'status' => 'pendente',
            ]);

            // Create boleto
            Boleto::create([
                'empresa_id' => $empresaId,
                'unidade_id' => session('unidade_id'),
                'conta_receber_id' => $conta->id,
                'cliente_id' => $validated['cliente_id'],
                'contrato_id' => $validated['contrato_id'] ?? null,
                'valor' => $valor,
                'vencimento' => $vencimento->copy(),
                'status' => 'pendente',
            ]);

            $vencimento->addMonth();
        }

        return redirect()->route('app.boletos.index')
            ->with('success', "Carne com {$validated['parcelas']} boletos gerado com sucesso!");
    }

    public function cancelar(Boleto $boleto)
    {
        $boleto->update(['status' => 'cancelado']);

        return back()->with('success', 'Boleto cancelado com sucesso!');
    }

    public function baixar(Boleto $boleto)
    {
        $boleto->update([
            'status' => 'pago',
            'pago_em' => now(),
            'valor_pago' => $boleto->valor,
        ]);

        // Also mark the linked conta a receber as paid
        if ($boleto->conta_receber_id) {
            $boleto->contaReceber->update([
                'status' => 'paga',
                'pago_em' => now(),
                'valor_pago' => $boleto->valor,
            ]);
        }

        return back()->with('success', 'Boleto baixado com sucesso!');
    }
}
