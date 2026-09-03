<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusVenda;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoLoja;
use App\Models\Devolucao;
use App\Models\Unidade;
use App\Models\Vale;
use App\Models\Venda;
use App\Scopes\UnidadeScope;
use App\Services\TrocaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Trocas, devoluções e vales fora do PDV (03/09/2026).
 *
 * A troca com venda nova na hora acontece no PDV (F6). Aqui: a lista do que
 * já foi trocado, a devolução "sem levar nada" (gera vale ou dinheiro), os
 * vales emitidos e a reimpressão dos comprovantes.
 */
class TrocaController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = (int) session('empresa_id');
        $lojas = $this->lojasParaFiltro($request->user());

        $query = Devolucao::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $empresaId)
            ->with(['venda:id,numero,cliente_id,unidade_id', 'venda.cliente:id,nome_razao_social', 'vale:id,codigo,saldo,status', 'user:id,name', 'unidade:id,nome'])
            ->withCount('itens');

        if ($lojas->count() > 1 && $request->filled('loja') && $request->loja !== 'todas') {
            $query->where('unidade_id', (int) $request->loja);
        } elseif ($lojas->count() <= 1) {
            $query->where('unidade_id', session('unidade_id'));
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }
        if ($request->filled('busca')) {
            $b = $request->busca;
            $query->where(function ($w) use ($b) {
                $w->whereHas('venda', fn ($v) => $v->withoutGlobalScopes()->where('numero', 'like', "%{$b}%"))
                  ->orWhereHas('venda.cliente', fn ($c) => $c->where('nome_razao_social', 'like', "%{$b}%"))
                  ->orWhereHas('vale', fn ($v) => $v->where('codigo', 'like', "%{$b}%"));
            });
        }

        $devolucoes = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $statsBase = Devolucao::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $empresaId)
            ->where('status', '!=', 'cancelada');
        $stats = [
            'mes_qtd'    => (clone $statsBase)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'mes_valor'  => (clone $statsBase)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('valor_estornado'),
            'vales_ativos' => Vale::withoutGlobalScopes()->where('empresa_id', $empresaId)->where('status', 'ativo')->count(),
            'vales_saldo'  => Vale::withoutGlobalScopes()->where('empresa_id', $empresaId)->where('status', 'ativo')->sum('saldo'),
        ];

        return view('app.trocas.index', compact('devolucoes', 'stats', 'lojas'));
    }

    /** Devolução fora do PDV: busca a venda e monta o formulário. */
    public function create(Request $request, TrocaService $trocas)
    {
        $config = ConfiguracaoLoja::daUnidade();
        $situacao = null;
        $venda = null;
        $resultados = collect();

        if ($request->filled('venda')) {
            $venda = Venda::withoutGlobalScope(UnidadeScope::class)
                ->where('empresa_id', session('empresa_id'))
                ->find((int) $request->venda);
            if ($venda) {
                $situacao = $trocas->situacao($venda, $config, $request->user());
            }
        } elseif ($request->filled('busca')) {
            $q = trim($request->busca);
            $resultados = Venda::withoutGlobalScope(UnidadeScope::class)
                ->where('empresa_id', session('empresa_id'))
                ->where('status', StatusVenda::Concluida)
                ->with(['cliente:id,nome_razao_social', 'unidade:id,nome'])
                ->when(preg_match('/^V(\d+)$/i', $q, $m), fn ($qq) => $qq->where('id', (int) $m[1]))
                ->when(ctype_digit($q), fn ($qq) => $qq->where(fn ($w) => $w->where('numero', (int) $q)->orWhere('id', (int) $q)))
                ->when(! ctype_digit($q) && ! preg_match('/^V\d+$/i', $q), fn ($qq) => $qq->whereHas('cliente', fn ($c) => $c->where('nome_razao_social', 'like', "%{$q}%")))
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        $estoques = \App\Models\Estoque::withoutGlobalScopes()
            ->where('unidade_id', session('unidade_id'))
            ->where('status', 'ativo')
            ->orderByDesc('permite_venda')
            ->get(['id', 'nome', 'permite_venda']);

        return view('app.trocas.create', compact('venda', 'situacao', 'resultados', 'config', 'estoques'));
    }

    public function store(Request $request, TrocaService $trocas)
    {
        $request->validate([
            'venda_id'                => 'required|integer',
            'itens'                   => 'required|array',
            'itens.*.venda_item_id'   => 'required|integer',
            'itens.*.quantidade'      => 'nullable|numeric|min:0',
            'itens.*.retorna_estoque' => 'nullable|boolean',
            'itens.*.estoque_id'      => 'nullable|integer',
            'motivo'                  => 'required|string|max:40',
            'motivo_texto'            => 'nullable|string|max:500',
            'sobra_destino'           => 'required|in:vale,dinheiro',
            'gerente_email'           => 'nullable|string|max:255',
            'gerente_senha'           => 'nullable|string|max:255',
            'observacoes'             => 'nullable|string|max:1000',
        ]);

        $venda = Venda::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', session('empresa_id'))
            ->findOrFail((int) $request->venda_id);

        $dados = $request->all();
        $dados['tipo'] = 'devolucao';
        $dados['itens'] = collect($dados['itens'])->filter(fn ($i) => (float) ($i['quantidade'] ?? 0) > 0)->values()->all();

        try {
            $devolucao = $trocas->registrar(
                $venda,
                $dados,
                $request->user(),
                ConfiguracaoLoja::daUnidade(),
                (int) session('unidade_id'),
                session('caixa_id') ? (int) session('caixa_id') : null
            );
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[Trocas] Erro ao registrar devolução.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Erro ao registrar a devolução: ' . $e->getMessage());
        }

        $msg = 'Devolução registrada.';
        if ($devolucao->vale) {
            $msg .= ' Vale ' . $devolucao->vale->codigo . ' de R$ ' . number_format((float) $devolucao->vale->valor, 2, ',', '.') . ' emitido.';
        } elseif ($devolucao->forma_sobra === 'dinheiro') {
            $msg .= ' R$ ' . number_format((float) $devolucao->valor_sobra, 2, ',', '.') . ' devolvidos em dinheiro pelo caixa.';
        } elseif ($devolucao->forma_sobra === 'parcelas') {
            $msg .= ' Valor abatido das parcelas em aberto.';
        }

        return redirect()->route('app.trocas.show', $devolucao)->with('success', $msg);
    }

    public function show(Devolucao $devolucao)
    {
        $devolucao->load(['itens.produto', 'itens.estoque', 'venda.cliente', 'venda.unidade', 'vendaNova', 'vale.usos.venda', 'user', 'aprovador', 'unidade', 'caixa']);

        return view('app.trocas.show', compact('devolucao'));
    }

    public function comprovante(Devolucao $devolucao)
    {
        $devolucao->load(['itens.produto', 'venda.cliente', 'venda.unidade', 'vale', 'user', 'aprovador', 'unidade.empresa']);

        return view('app.trocas.comprovante', ['devolucao' => $devolucao, 'autoPrint' => request()->boolean('print')]);
    }

    public function vales(Request $request)
    {
        $empresaId = (int) session('empresa_id');

        $query = Vale::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->with(['cliente:id,nome_razao_social', 'unidade:id,nome', 'devolucao:id,venda_id,tipo', 'devolucao.venda:id,numero'])
            ->withSum('usos', 'valor');

        $status = $request->input('status', 'ativo');
        if ($status === 'ativo') {
            $query->where('status', 'ativo');
        } elseif ($status !== 'todos') {
            $query->where('status', $status);
        }
        if ($request->filled('busca')) {
            $b = $request->busca;
            $query->where(function ($w) use ($b) {
                $w->where('codigo', 'like', "%{$b}%")
                  ->orWhereHas('cliente', fn ($c) => $c->where('nome_razao_social', 'like', "%{$b}%"));
            });
        }

        $vales = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return view('app.trocas.vales', compact('vales', 'status'));
    }

    public function imprimirVale(Vale $vale)
    {
        $vale->load(['cliente', 'unidade.empresa', 'devolucao.venda', 'usos.venda']);

        return view('app.trocas.vale-print', ['vale' => $vale, 'autoPrint' => request()->boolean('print')]);
    }

    public function cancelarVale(Request $request, Vale $vale)
    {
        if ($vale->status !== 'ativo') {
            return back()->with('error', 'Só um vale ativo pode ser cancelado.');
        }

        $vale->update([
            'status'      => 'cancelado',
            'observacoes' => trim(($vale->observacoes ? $vale->observacoes . "\n" : '')
                . 'Cancelado por ' . $request->user()->name . ' em ' . now()->format('d/m/Y H:i')
                . ($request->filled('motivo') ? ' — ' . $request->motivo : '')),
        ]);

        return back()->with('success', 'Vale ' . $vale->codigo . ' cancelado. O saldo de R$ ' . number_format((float) $vale->saldo, 2, ',', '.') . ' deixa de valer.');
    }

    /** Mesmo critério de VendaController::lojasParaFiltro. */
    private function lojasParaFiltro($user): \Illuminate\Support\Collection
    {
        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;

        if (! $user->is_admin && ! in_array($perfil, ['admin', 'dono', 'gerente'])) {
            return collect();
        }

        return Unidade::withoutGlobalScopes()
            ->where('empresa_id', session('empresa_id'))
            ->where('status', 'ativa')
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }
}
