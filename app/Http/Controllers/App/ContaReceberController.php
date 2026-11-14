<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ContaReceber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContaReceberController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $query = ContaReceber::with('cliente')
            ->where('empresa_id', $empresaId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Forma de pagamento (14/11/2026): mesma linha de forma dos relatórios —
        // forma criada pela chave; do sistema pelo código, sem as criadas do mesmo tipo
        if ($request->filled('forma')) {
            $f = (string) $request->forma;
            str_starts_with($f, 'fp_')
                ? $query->where('forma_chave', $f)
                : $query->where('forma_pagamento', $f)->where(fn ($q) => $q->whereNull('forma_chave')->orWhere('forma_chave', 'not like', 'fp\\_%'));
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

        $contas = $query->orderBy('vencimento')->paginate(20)->withQueryString();

        // Totals
        $totalPendente = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->sum('valor');

        $totalVencido = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', now())
            ->sum('valor');

        $recebidoMes = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereMonth('pago_em', now()->month)
            ->whereYear('pago_em', now()->year)
            ->sum('valor_pago');

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->orderBy('nome_razao_social')
            ->get(['id', 'nome_razao_social']);

        $formasFiltro = app(\App\Services\FormaPagamentoService::class)->opcoesFiltro((int) $empresaId);

        return view('app.financeiro.contas-receber.index', compact(
            'contas', 'totalPendente', 'totalVencido', 'recebidoMes', 'clientes', 'formasFiltro'
        ));
    }

    public function create()
    {
        $clientes = Cliente::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativo')
            ->orderBy('nome_razao_social')
            ->get(['id', 'nome_razao_social']);

        // Formas da empresa (Configurações → Formas de pagamento, 14/11/2026)
        $formasPagamento = app(\App\Services\FormaPagamentoService::class)
            ->ativas((int) (session('empresa_id') ?? auth()->user()->empresa_id));

        return view('app.financeiro.contas-receber.create', compact('clientes', 'formasPagamento'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id'         => 'required|exists:clientes,id',
            'descricao'          => 'required|string|max:255',
            'valor'              => 'required|numeric|min:0.01',
            'parcelas'           => 'required|integer|min:1|max:48',
            'primeiro_vencimento' => 'required|date',
            'forma_pagamento'    => 'nullable|string|max:50',
            'observacoes'        => 'nullable|string|max:500',
        ]);

        // O botão manda a chave da forma configurada; grava o código + chave + nome (14/11/2026)
        $fp = ! empty($validated['forma_pagamento'])
            ? app(\App\Services\FormaPagamentoService::class)->resolver((int) (session('empresa_id') ?? auth()->user()->empresa_id), $validated['forma_pagamento'])
            : null;
        if (! empty($validated['forma_pagamento']) && ! $fp) {
            return back()->withInput()->withErrors(['forma_pagamento' => 'Esta forma de pagamento está desligada. Escolha outra.']);
        }

        DB::transaction(function () use ($validated, $fp) {
            $valorParcela = round($validated['valor'] / $validated['parcelas'], 2);
            $vencimento = Carbon::parse($validated['primeiro_vencimento']);

            for ($i = 1; $i <= $validated['parcelas']; $i++) {
                // Adjust last parcela for rounding
                $valor = ($i === $validated['parcelas'])
                    ? $validated['valor'] - ($valorParcela * ($validated['parcelas'] - 1))
                    : $valorParcela;

                ContaReceber::create([
                    'empresa_id'      => auth()->user()->empresa_id,
                    'unidade_id'      => session('unidade_id'),
                    'cliente_id'      => $validated['cliente_id'],
                    'descricao'       => $validated['descricao'],
                    'valor'           => $valor,
                    'vencimento'      => $vencimento->copy(),
                    'forma_pagamento' => $fp?->codigoGravado(),
                    'forma_chave'     => $fp?->chave,
                    'forma_nome'      => $fp?->nome,
                    'parcela'         => $i,
                    'total_parcelas'  => $validated['parcelas'],
                    'status'          => 'pendente',
                    'observacoes'     => $validated['observacoes'] ?? null,
                ]);

                $vencimento->addMonth();
            }
        });

        return redirect()->route('app.contas-receber.index')
            ->with('success', 'Conta a receber cadastrada com sucesso!');
    }

    public function show(ContaReceber $contaReceber)
    {
        $contaReceber->load(['cliente', 'venda']);

        // Load sibling parcelas
        $parcelas = ContaReceber::where('empresa_id', $contaReceber->empresa_id)
            ->where('cliente_id', $contaReceber->cliente_id)
            ->where('descricao', $contaReceber->descricao)
            ->where('total_parcelas', $contaReceber->total_parcelas)
            ->orderBy('parcela')
            ->get();

        return view('app.financeiro.contas-receber.show', compact('contaReceber', 'parcelas'));
    }

    public function baixar(ContaReceber $contaReceber)
    {
        $contaReceber->update([
            'valor_pago' => $contaReceber->valor,
            'pago_em'    => now(),
            'status'     => 'paga',
        ]);

        return back()->with('success', 'Conta marcada como paga!');
    }

    public function destroy(ContaReceber $contaReceber)
    {
        $contaReceber->delete();

        return redirect()->route('app.contas-receber.index')
            ->with('success', 'Conta excluida com sucesso!');
    }
}
