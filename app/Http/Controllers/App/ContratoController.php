<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Boleto;
use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\Contrato;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $query = Contrato::with('cliente')
            ->where('empresa_id', $empresaId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('inicio')) {
            $query->whereDate('inicio', '>=', $request->inicio);
        }

        if ($request->filled('fim')) {
            $query->whereDate('fim', '<=', $request->fim);
        }

        $contratos = $query->orderBy('proximo_faturamento')->paginate(20)->withQueryString();

        // Summary
        $ativos = Contrato::where('empresa_id', $empresaId)->where('status', 'ativo')->count();

        $vencendo30d = Contrato::where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->whereNotNull('fim')
            ->whereBetween('fim', [now(), now()->addDays(30)])
            ->count();

        $valorRecorrenteMensal = Contrato::where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->get()
            ->sum(function ($c) {
                return match ($c->periodicidade) {
                    'mensal' => $c->valor,
                    'trimestral' => $c->valor / 3,
                    'semestral' => $c->valor / 6,
                    'anual' => $c->valor / 12,
                    default => $c->valor,
                };
            });

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->orderBy('nome_razao_social')
            ->get(['id', 'nome_razao_social']);

        return view('app.contratos.index', compact(
            'contratos', 'ativos', 'vencendo30d', 'valorRecorrenteMensal', 'clientes'
        ));
    }

    public function create()
    {
        $clientes = Cliente::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativo')
            ->orderBy('nome_razao_social')
            ->get(['id', 'nome_razao_social']);

        return view('app.contratos.create', compact('clientes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'descricao' => 'required|string|max:500',
            'valor' => 'required|numeric|min:0.01',
            'periodicidade' => 'required|in:mensal,trimestral,semestral,anual',
            'inicio' => 'required|date',
            'fim' => 'nullable|date|after:inicio',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        $validated['empresa_id'] = auth()->user()->empresa_id;
        $validated['unidade_id'] = session('unidade_id');
        $validated['status'] = 'ativo';
        $validated['proximo_faturamento'] = $validated['inicio'];

        Contrato::create($validated);

        return redirect()->route('app.contratos.index')
            ->with('success', 'Contrato criado com sucesso!');
    }

    public function show(Contrato $contrato)
    {
        $contrato->load('cliente');

        // Payment history - contas a receber linked to this contract
        $contasReceber = ContaReceber::where('empresa_id', $contrato->empresa_id)
            ->where('cliente_id', $contrato->cliente_id)
            ->where('descricao', 'like', '%Contrato #' . $contrato->id . '%')
            ->orderByDesc('vencimento')
            ->get();

        $boletos = Boleto::where('contrato_id', $contrato->id)->orderByDesc('vencimento')->get();

        return view('app.contratos.show', compact('contrato', 'contasReceber', 'boletos'));
    }

    public function edit(Contrato $contrato)
    {
        $clientes = Cliente::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativo')
            ->orderBy('nome_razao_social')
            ->get(['id', 'nome_razao_social']);

        return view('app.contratos.edit', compact('contrato', 'clientes'));
    }

    public function update(Request $request, Contrato $contrato)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'descricao' => 'required|string|max:500',
            'valor' => 'required|numeric|min:0.01',
            'periodicidade' => 'required|in:mensal,trimestral,semestral,anual',
            'inicio' => 'required|date',
            'fim' => 'nullable|date|after:inicio',
            'status' => 'required|in:ativo,vencido,cancelado,suspenso',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        $contrato->update($validated);

        return redirect()->route('app.contratos.show', $contrato)
            ->with('success', 'Contrato atualizado com sucesso!');
    }

    public function faturar(Contrato $contrato)
    {
        if ($contrato->status !== 'ativo') {
            return back()->with('error', 'Apenas contratos ativos podem ser faturados.');
        }

        $vencimento = $contrato->proximo_faturamento ?? now();

        // Create ContaReceber
        $conta = ContaReceber::create([
            'empresa_id' => $contrato->empresa_id,
            'unidade_id' => $contrato->unidade_id,
            'cliente_id' => $contrato->cliente_id,
            'descricao' => "Contrato #{$contrato->id} - {$contrato->descricao}",
            'valor' => $contrato->valor,
            'vencimento' => $vencimento,
            'forma_pagamento' => 'boleto',
            'parcela' => 1,
            'total_parcelas' => 1,
            'status' => 'pendente',
        ]);

        // Generate boleto
        Boleto::create([
            'empresa_id' => $contrato->empresa_id,
            'unidade_id' => $contrato->unidade_id,
            'conta_receber_id' => $conta->id,
            'cliente_id' => $contrato->cliente_id,
            'contrato_id' => $contrato->id,
            'valor' => $contrato->valor,
            'vencimento' => $vencimento,
            'status' => 'pendente',
        ]);

        // Advance proximo_faturamento
        $next = Carbon::parse($vencimento);
        $next = match ($contrato->periodicidade) {
            'mensal' => $next->addMonth(),
            'trimestral' => $next->addMonths(3),
            'semestral' => $next->addMonths(6),
            'anual' => $next->addYear(),
        };

        $contrato->update(['proximo_faturamento' => $next]);

        return back()->with('success', 'Faturamento gerado com sucesso!');
    }

    public function destroy(Contrato $contrato)
    {
        $contrato->delete();

        return redirect()->route('app.contratos.index')
            ->with('success', 'Contrato excluido com sucesso!');
    }
}
