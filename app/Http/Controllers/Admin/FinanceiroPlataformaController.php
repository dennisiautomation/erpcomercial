<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\PlataformaFatura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Financeiro da PLATAFORMA: faturas da cobrança direta (cliente paga a
 * IA365 sem gateway). Acesso restrito a admins com pode_ver_financeiro.
 */
class FinanceiroPlataformaController extends Controller
{
    private function autorizar(Request $request): void
    {
        abort_unless($request->user()->podeVerFinanceiro(), 403,
            'Você não tem permissão para ver o financeiro da plataforma.');
    }

    public function index(Request $request): View
    {
        $this->autorizar($request);

        $hoje = Carbon::today();

        // Cards de resumo
        $aReceberMes = PlataformaFatura::pendentes()
            ->whereBetween('vencimento', [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()])
            ->sum('valor');
        $emAtraso = PlataformaFatura::pendentes()
            ->where('vencimento', '<', $hoje)
            ->sum('valor');
        $recebidoMes = PlataformaFatura::where('status', 'paga')
            ->whereBetween('pago_em', [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()])
            ->sum('valor');

        // Receita recorrente: mensais + anuais/12
        $contratos = Empresa::withoutGlobalScopes()
            ->whereNotNull('cobranca_periodicidade')
            ->whereNotNull('cobranca_valor')
            ->get();
        $mrr = $contratos->sum(fn ($e) => $e->cobranca_periodicidade === 'mensal'
            ? (float) $e->cobranca_valor
            : (float) $e->cobranca_valor / 12);

        // Lista de faturas com filtros
        $query = PlataformaFatura::with('empresa', 'marcadaPor')->orderByDesc('vencimento');

        if ($status = $request->input('status')) {
            if ($status === 'atrasada') {
                $query->pendentes()->where('vencimento', '<', $hoje);
            } else {
                $query->where('status', $status);
            }
        }
        if ($empresaId = $request->input('empresa_id')) {
            $query->where('empresa_id', $empresaId);
        }

        $faturas = $query->paginate(25)->withQueryString();

        $empresas = Empresa::withoutGlobalScopes()->orderBy('razao_social')->get();
        $suspensas = Empresa::withoutGlobalScopes()->whereNotNull('cobranca_suspensa_em')->get();

        return view('admin.financeiro.index', compact(
            'faturas', 'empresas', 'contratos', 'suspensas',
            'aReceberMes', 'emAtraso', 'recebidoMes', 'mrr'
        ));
    }

    /** Gera uma fatura manual para a empresa. */
    public function store(Request $request): RedirectResponse
    {
        $this->autorizar($request);

        $validated = $request->validate([
            'empresa_id'  => ['required', 'exists:empresas,id'],
            'valor'       => ['required', 'numeric', 'min:0.01'],
            'vencimento'  => ['required', 'date'],
            'competencia' => ['nullable', 'string', 'max:7'],
            'descricao'   => ['nullable', 'string', 'max:255'],
        ]);

        $vencimento = Carbon::parse($validated['vencimento']);

        PlataformaFatura::create([
            'empresa_id'  => $validated['empresa_id'],
            'competencia' => $validated['competencia'] ?: $vencimento->format('Y-m'),
            'descricao'   => $validated['descricao'] ?: null,
            'valor'       => $validated['valor'],
            'vencimento'  => $vencimento,
            'status'      => 'pendente',
        ]);

        return back()->with('success', 'Fatura gerada.');
    }

    /** Marca a fatura como paga e reativa a empresa se estava suspensa. */
    public function marcarPaga(Request $request, PlataformaFatura $fatura): RedirectResponse
    {
        $this->autorizar($request);

        abort_unless($fatura->isPendente(), 422, 'Só faturas pendentes podem ser marcadas como pagas.');

        $validated = $request->validate([
            'pago_em'         => ['nullable', 'date'],
            'forma_pagamento' => ['nullable', 'string', 'max:30'],
            'observacao'      => ['nullable', 'string', 'max:500'],
        ]);

        $fatura->update([
            'status'          => 'paga',
            'pago_em'         => $validated['pago_em'] ?? Carbon::today(),
            'forma_pagamento' => $validated['forma_pagamento'] ?? null,
            'observacao'      => $validated['observacao'] ?? $fatura->observacao,
            'marcada_por'     => $request->user()->id,
        ]);

        $empresa = $fatura->empresa;

        // Anual paga → agenda a próxima renovação (+1 ano sobre o vencimento)
        if ($empresa->cobranca_periodicidade === 'anual'
            && $empresa->cobranca_proxima_renovacao
            && $fatura->vencimento->isSameDay($empresa->cobranca_proxima_renovacao)) {
            $empresa->cobranca_proxima_renovacao = $fatura->vencimento->copy()->addYear();
        }

        // Reativa se não sobrou NENHUMA pendente estourada
        if ($empresa->estaSuspensa()) {
            $aindaEstourada = $empresa->plataformaFaturas()->pendentes()->get()
                ->contains(fn ($f) => $f->passouDaTolerancia());
            if (! $aindaEstourada) {
                $empresa->cobranca_suspensa_em = null;
            }
        }

        $empresa->save();

        return back()->with('success', 'Fatura marcada como paga.'
            . ($empresa->estaSuspensa() ? '' : ' Acesso da empresa liberado.'));
    }

    public function cancelar(Request $request, PlataformaFatura $fatura): RedirectResponse
    {
        $this->autorizar($request);

        abort_unless($fatura->isPendente(), 422, 'Só faturas pendentes podem ser canceladas.');

        $fatura->update(['status' => 'cancelada', 'marcada_por' => $request->user()->id]);

        return back()->with('success', 'Fatura cancelada.');
    }

    /** Suspensão manual (independente do bloqueio automático). */
    public function suspender(Request $request, Empresa $empresa): RedirectResponse
    {
        $this->autorizar($request);

        $empresa->forceFill(['cobranca_suspensa_em' => now()])->save();

        return back()->with('success', "Acesso da {$empresa->razao_social} suspenso.");
    }

    public function reativar(Request $request, Empresa $empresa): RedirectResponse
    {
        $this->autorizar($request);

        $empresa->forceFill(['cobranca_suspensa_em' => null])->save();

        return back()->with('success', "Acesso da {$empresa->razao_social} reativado.");
    }
}
