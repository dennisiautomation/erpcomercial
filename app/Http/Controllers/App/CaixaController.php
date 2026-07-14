<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusCaixa;
use App\Enums\TipoMovimentacaoCaixa;
use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Models\MovimentacaoCaixa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CaixaController extends Controller
{
    /**
     * Resumo financeiro do caixa separado por forma de pagamento.
     * Movimentações de venda antigas (sem forma_pagamento) são tratadas
     * como dinheiro para não quebrar a conferência de caixas legados.
     */
    private function resumoCaixa(Caixa $caixa): array
    {
        $movs = $caixa->movimentacoes;

        $vendas = $movs->where('tipo', TipoMovimentacaoCaixa::Venda);

        $porForma = $vendas
            ->groupBy(fn ($mov) => $mov->forma_pagamento ?? 'dinheiro')
            ->map(fn ($grupo) => (float) $grupo->sum('valor'))
            ->sortDesc();

        $vendasDinheiro = (float) ($porForma['dinheiro'] ?? 0);
        $suprimentos = (float) $movs->where('tipo', TipoMovimentacaoCaixa::Suprimento)->sum('valor');
        $sangrias = (float) $movs->where('tipo', TipoMovimentacaoCaixa::Sangria)->sum('valor');
        $abertura = (float) $caixa->valor_abertura;

        return [
            'abertura'          => $abertura,
            'vendas'            => (float) $vendas->sum('valor'),
            'vendas_dinheiro'   => $vendasDinheiro,
            'vendas_por_forma'  => $porForma,
            'suprimentos'       => $suprimentos,
            'sangrias'          => $sangrias,
            'esperado_dinheiro' => round($abertura + $vendasDinheiro + $suprimentos - $sangrias, 2),
        ];
    }

    /** Histórico de caixas da unidade (dono/admin/gerente veem todos; demais só os seus). */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Caixa::with(['operador', 'unidade'])
            ->withCount('movimentacoes')
            ->orderByDesc('aberto_em');

        // Operador comum só enxerga os próprios caixas
        $veTodos = $user->is_admin
            || in_array($user->perfil->value, ['admin', 'dono', 'gerente', 'financeiro'], true);

        if (! $veTodos) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('aberto_em', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('aberto_em', '<=', $request->data_fim);
        }

        $caixas = $query->paginate(20)->withQueryString();

        return view('app.caixa.index', compact('caixas', 'veTodos'));
    }

    /** Detalhe de um caixa: resumo por forma de pagamento + extrato de movimentações. */
    public function show(Caixa $caixa)
    {
        $user = auth()->user();

        $veTodos = $user->is_admin
            || in_array($user->perfil->value, ['admin', 'dono', 'gerente', 'financeiro'], true);

        if (! $veTodos && $caixa->user_id !== $user->id) {
            abort(403, 'Você só pode ver os próprios caixas.');
        }

        $caixa->load(['operador', 'unidade', 'movimentacoes.user']);

        $resumo = $this->resumoCaixa($caixa);

        return view('app.caixa.show', compact('caixa', 'resumo'));
    }

    public function abrir(Request $request)
    {
        if ($request->isMethod('get')) {
            // Check if already has open caixa
            $caixaAberto = Caixa::where('unidade_id', session('unidade_id'))
                ->where('user_id', auth()->id())
                ->where('status', StatusCaixa::Aberto)
                ->first();

            if ($caixaAberto) {
                session(['caixa_id' => $caixaAberto->id]);
                return redirect()->route('app.pdv.index')
                    ->with('info', 'Voce ja possui um caixa aberto.');
            }

            return view('app.caixa.abrir');
        }

        $request->validate([
            'numero_caixa'   => 'required|integer|min:1',
            'valor_abertura' => 'required|numeric|min:0',
        ]);

        // Check if there's already an open caixa for this user
        $caixaAberto = Caixa::where('unidade_id', session('unidade_id'))
            ->where('user_id', auth()->id())
            ->where('status', StatusCaixa::Aberto)
            ->first();

        if ($caixaAberto) {
            session(['caixa_id' => $caixaAberto->id]);
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Voce ja possui um caixa aberto.'], 422);
            }
            return back()->with('error', 'Voce ja possui um caixa aberto.');
        }

        // Check if the caixa number is already in use (open) at this unidade
        $caixaNumeroEmUso = Caixa::where('unidade_id', session('unidade_id'))
            ->where('numero_caixa', $request->numero_caixa)
            ->where('status', StatusCaixa::Aberto)
            ->exists();

        if ($caixaNumeroEmUso) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Este numero de caixa ja esta em uso.'], 422);
            }
            return back()->with('error', 'Este numero de caixa ja esta em uso por outro operador.');
        }

        $caixa = DB::transaction(function () use ($request) {
            $caixa = Caixa::create([
                'empresa_id'     => session('empresa_id'),
                'unidade_id'     => session('unidade_id'),
                'user_id'        => auth()->id(),
                'numero_caixa'   => $request->numero_caixa,
                'valor_abertura' => $request->valor_abertura,
                'status'         => StatusCaixa::Aberto,
                'aberto_em'      => now(),
            ]);

            MovimentacaoCaixa::create([
                'empresa_id' => session('empresa_id'),
                'unidade_id' => session('unidade_id'),
                'caixa_id'   => $caixa->id,
                'tipo'       => TipoMovimentacaoCaixa::Abertura,
                'valor'      => $request->valor_abertura,
                'descricao'  => 'Abertura de caixa',
                'user_id'    => auth()->id(),
            ]);

            return $caixa;
        });

        session(['caixa_id' => $caixa->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Caixa aberto com sucesso!',
                'caixa'   => $caixa,
            ]);
        }

        return redirect()->route('app.pdv.index')
            ->with('success', 'Caixa aberto com sucesso!');
    }

    public function fechar(Request $request)
    {
        $caixaId = session('caixa_id');
        if (!$caixaId) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Nenhum caixa aberto.'], 422);
            }
            return redirect()->route('app.pdv.index')
                ->with('error', 'Nenhum caixa aberto.');
        }

        $caixa = Caixa::with('movimentacoes')->find($caixaId);
        if (!$caixa) {
            session()->forget('caixa_id');
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Caixa nao encontrado.'], 422);
            }
            return redirect()->route('app.pdv.index')
                ->with('error', 'Caixa nao encontrado.');
        }

        if ($request->isMethod('get')) {
            $resumo = $this->resumoCaixa($caixa);

            return view('app.caixa.fechar', [
                'caixa'         => $caixa,
                'resumo'        => $resumo,
                // Só o que fica fisicamente na gaveta precisa bater:
                // abertura + vendas em dinheiro + suprimentos - sangrias.
                'valorEsperado' => $resumo['esperado_dinheiro'],
            ]);
        }

        $request->validate([
            'valor_contado' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $caixa) {
            $valorEsperado = $this->resumoCaixa($caixa)['esperado_dinheiro'];

            $caixa->update([
                'status'           => StatusCaixa::Fechado,
                'valor_fechamento' => $request->valor_contado,
                'valor_esperado'   => $valorEsperado,
                'fechado_em'       => now(),
                'observacoes'      => $request->observacoes,
            ]);

            MovimentacaoCaixa::create([
                'empresa_id' => $caixa->empresa_id,
                'unidade_id' => $caixa->unidade_id,
                'caixa_id'   => $caixa->id,
                'tipo'       => TipoMovimentacaoCaixa::Fechamento,
                'valor'      => $request->valor_contado,
                'descricao'  => 'Fechamento de caixa',
                'user_id'    => auth()->id(),
            ]);
        });

        session()->forget('caixa_id');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Caixa fechado com sucesso!',
            ]);
        }

        return redirect()->route('app.pdv.index')
            ->with('success', 'Caixa fechado com sucesso!');
    }

    public function sangria(Request $request)
    {
        $request->validate([
            'valor'     => 'required|numeric|min:0.01',
            'descricao' => 'required|string|max:500',
        ]);

        $caixaId = session('caixa_id');
        if (!$caixaId) {
            return response()->json(['error' => 'Nenhum caixa aberto.'], 422);
        }

        $caixa = Caixa::find($caixaId);
        if (!$caixa || $caixa->status->value !== 'aberto') {
            session()->forget('caixa_id');
            return response()->json(['error' => 'Caixa nao esta aberto.'], 422);
        }

        MovimentacaoCaixa::create([
            'empresa_id' => session('empresa_id'),
            'unidade_id' => session('unidade_id'),
            'caixa_id'   => $caixaId,
            'tipo'       => TipoMovimentacaoCaixa::Sangria,
            'valor'      => $request->valor,
            'descricao'  => $request->descricao,
            'user_id'    => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Sangria registrada com sucesso!']);
    }

    public function suprimento(Request $request)
    {
        $request->validate([
            'valor'     => 'required|numeric|min:0.01',
            'descricao' => 'required|string|max:500',
        ]);

        $caixaId = session('caixa_id');
        if (!$caixaId) {
            return response()->json(['error' => 'Nenhum caixa aberto.'], 422);
        }

        $caixa = Caixa::find($caixaId);
        if (!$caixa || $caixa->status->value !== 'aberto') {
            session()->forget('caixa_id');
            return response()->json(['error' => 'Caixa nao esta aberto.'], 422);
        }

        MovimentacaoCaixa::create([
            'empresa_id' => session('empresa_id'),
            'unidade_id' => session('unidade_id'),
            'caixa_id'   => $caixaId,
            'tipo'       => TipoMovimentacaoCaixa::Suprimento,
            'valor'      => $request->valor,
            'descricao'  => $request->descricao,
            'user_id'    => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Suprimento registrado com sucesso!']);
    }
}
