<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Unidade;
use App\Models\Venda;
use App\Models\Produto;
use App\Services\SaldoEstoque;
use App\Models\ContaReceber;
use App\Models\Caixa;
use App\Models\EstoqueMovimentacao;
use App\Enums\TipoMovimentacaoEstoque;
use App\Services\EstoqueMultiUnidadeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MultilojaController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $empresaId = $user->empresa_id;

        $unidades = $this->unidadesVisiveis();

        $mesAtual = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();

        $dadosUnidades = [];
        $faturamentoTotal = 0;
        $vendasTotal = 0;

        foreach ($unidades as $unidade) {
            $vendasMes = Venda::where('unidade_id', $unidade->id)
                ->where('empresa_id', $empresaId)
                ->whereBetween('created_at', [$mesAtual, $fimMes])
                ->whereNotIn('status', ['cancelada']);

            $faturamento = (clone $vendasMes)->sum('total');
            $totalVendas = (clone $vendasMes)->count();
            $ticketMedio = $totalVendas > 0 ? $faturamento / $totalVendas : 0;

            $estoqueCritico = Produto::where('empresa_id', $empresaId)
                ->whereHas('estoqueMovimentacoes', function ($q) use ($unidade) {
                    $q->where('unidade_id', $unidade->id);
                })
                ->whereRaw('estoque_minimo > 0')
                ->get()
                ->filter(function ($produto) use ($unidade) {
                    // Soma dos estoques da loja, não a última movimentação solta
                    $saldo = SaldoEstoque::naUnidade($unidade->id, $produto->id);
                    return $saldo <= $produto->estoque_minimo;
                })
                ->count();

            $faturamentoTotal += $faturamento;
            $vendasTotal += $totalVendas;

            $dadosUnidades[] = [
                'unidade' => $unidade,
                'faturamento' => $faturamento,
                'total_vendas' => $totalVendas,
                'ticket_medio' => $ticketMedio,
                'estoque_critico' => $estoqueCritico,
            ];
        }

        // Sort by faturamento desc for ranking
        usort($dadosUnidades, fn ($a, $b) => $b['faturamento'] <=> $a['faturamento']);

        $ticketMedioGlobal = $vendasTotal > 0 ? $faturamentoTotal / $vendasTotal : 0;

        // Alerts
        $alertas = [];

        // Estoque baixo across unidades
        $totalEstoqueCritico = collect($dadosUnidades)->sum('estoque_critico');
        if ($totalEstoqueCritico > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'icone' => 'bi-exclamation-triangle',
                'mensagem' => "{$totalEstoqueCritico} produto(s) com estoque critico nas unidades.",
            ];
        }

        // Contas vencidas
        $contasVencidas = ContaReceber::where('empresa_id', $empresaId)
            ->whereNull('pago_em')
            ->where('vencimento', '<', now())
            ->count();

        if ($contasVencidas > 0) {
            $alertas[] = [
                'tipo' => 'danger',
                'icone' => 'bi-clock-history',
                'mensagem' => "{$contasVencidas} conta(s) a receber vencida(s).",
            ];
        }

        // Caixas abertos
        $caixasAbertos = Caixa::where('empresa_id', $empresaId)
            ->whereNull('fechado_em')
            ->count();

        if ($caixasAbertos > 0) {
            $alertas[] = [
                'tipo' => 'info',
                'icone' => 'bi-cash-coin',
                'mensagem' => "{$caixasAbertos} caixa(s) aberto(s) no momento.",
            ];
        }

        return view('app.multilojas.index', compact(
            'dadosUnidades',
            'faturamentoTotal',
            'vendasTotal',
            'ticketMedioGlobal',
            'unidades',
            'alertas'
        ));
    }

    public function comparar(Request $request)
    {
        $user = auth()->user();
        $empresaId = $user->empresa_id;

        $unidades = $this->unidadesVisiveis();

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)->startOfDay()
            : Carbon::now()->startOfMonth();

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)->endOfDay()
            : Carbon::now()->endOfDay();

        $unidadesSelecionadas = $request->input('unidades', []);

        $comparacao = [];

        if (!empty($unidadesSelecionadas)) {
            foreach ($unidadesSelecionadas as $unidadeId) {
                $unidade = $unidades->find($unidadeId);
                if (!$unidade) continue;

                $vendasQuery = Venda::where('unidade_id', $unidade->id)
                    ->where('empresa_id', $empresaId)
                    ->whereBetween('created_at', [$dataInicio, $dataFim])
                    ->whereNotIn('status', ['cancelada']);

                $faturamento = (clone $vendasQuery)->sum('total');
                $totalVendas = (clone $vendasQuery)->count();
                $ticketMedio = $totalVendas > 0 ? $faturamento / $totalVendas : 0;
                $devolucoes = Venda::where('unidade_id', $unidade->id)
                    ->where('empresa_id', $empresaId)
                    ->whereBetween('created_at', [$dataInicio, $dataFim])
                    ->where('status', 'cancelada')
                    ->count();

                $comparacao[] = [
                    'unidade' => $unidade,
                    'faturamento' => $faturamento,
                    'total_vendas' => $totalVendas,
                    'ticket_medio' => $ticketMedio,
                    'devolucoes' => $devolucoes,
                ];
            }
        }

        return view('app.multilojas.comparar', compact(
            'unidades',
            'comparacao',
            'dataInicio',
            'dataFim',
            'unidadesSelecionadas'
        ));
    }

    /**
     * Estoque consolidado: matriz produto x loja com saldo por unidade.
     * Permite editar (ajustar) o saldo de cada loja direto na tela.
     */
    public function estoque(Request $request, EstoqueMultiUnidadeService $svc)
    {
        $user = auth()->user();
        $empresaId = $user->empresa_id;

        $unidades = $this->unidadesVisiveis();

        $busca = trim((string) $request->input('q', ''));

        $produtos = Produto::where('empresa_id', $empresaId)
            ->when($busca !== '', function ($query) use ($busca) {
                $query->where(function ($w) use ($busca) {
                    $w->where('descricao', 'like', "%{$busca}%")
                        ->orWhere('codigo_interno', 'like', "%{$busca}%")
                        ->orWhere('codigo_barras', 'like', "%{$busca}%")
                        ->orWhere('sku', 'like', "%{$busca}%");
                });
            })
            ->orderBy('descricao')
            ->limit(300)
            ->get();

        $matriz = [];
        foreach ($produtos as $produto) {
            $saldos = collect($svc->saldoPorUnidade($empresaId, $produto->id))
                ->keyBy('unidade_id');

            $linha = ['produto' => $produto, 'saldos' => [], 'total' => 0.0];
            foreach ($unidades as $unidade) {
                $saldo = (float) ($saldos[$unidade->id]['saldo'] ?? 0);
                $linha['saldos'][$unidade->id] = $saldo;
                $linha['total'] += $saldo;
            }
            $matriz[] = $linha;
        }

        return view('app.multilojas.estoque', compact('unidades', 'matriz', 'busca'));
    }

    /**
     * Aplica ajustes manuais de estoque em lote: recebe a matriz inteira
     * saldos[produto_id][unidade_id] e cria uma movimentacao tipo Ajuste
     * apenas para as celulas cujo saldo mudou.
     */
    public function ajustarEstoque(Request $request)
    {
        $user = auth()->user();
        $empresaId = $user->empresa_id;

        $validated = $request->validate([
            'saldos'     => ['required', 'array'],
            'saldos.*'   => ['array'],
            'saldos.*.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $produtos = Produto::where('empresa_id', $empresaId)
            ->whereIn('id', array_keys($validated['saldos']))
            ->get()
            ->keyBy('id');

        // Ajuste só nas lojas que ESTE usuário enxerga — a mesma lista da tela.
        // Sem isso, um POST forjado gravaria em loja fora do alcance do gerente.
        $unidadesValidas = $this->unidadesVisiveis()->pluck('id')->all();

        $ajustes = 0;
        $produtosAlterados = [];

        DB::transaction(function () use ($validated, $produtos, $empresaId, $unidadesValidas, &$ajustes, &$produtosAlterados) {
            foreach ($validated['saldos'] as $produtoId => $porUnidade) {
                $produto = $produtos[(int) $produtoId] ?? null;

                if (! $produto || ! is_array($porUnidade)) {
                    continue;
                }

                foreach ($porUnidade as $unidadeId => $novoSaldo) {
                    $unidadeId = (int) $unidadeId;

                    if (! in_array($unidadeId, $unidadesValidas, true)
                        || $novoSaldo === null || $novoSaldo === '') {
                        continue;
                    }

                    $novoSaldo = (float) $novoSaldo;

                    // A tela é POR LOJA: o ajuste cai no estoque de venda dela.
                    // O saldo comparado também é o da loja inteira, senão o
                    // ajuste brigaria com o que a célula mostra.
                    $estoqueId = SaldoEstoque::estoqueDeVendaId($unidadeId);
                    $anterior = SaldoEstoque::naUnidade($unidadeId, $produto->id);

                    if (abs($novoSaldo - $anterior) < 0.0001) {
                        continue; // nada mudou nesta celula
                    }

                    SaldoEstoque::registrar(
                        $empresaId,
                        $unidadeId,
                        $estoqueId,
                        $produto->id,
                        TipoMovimentacaoEstoque::Ajuste->value,
                        $novoSaldo - $anterior,
                        [
                            'custo_unitario' => $produto->preco_custo ?? 0,
                            'observacoes'    => 'Ajuste manual (Estoque por Loja)',
                        ]
                    );

                    $ajustes++;
                    $produtosAlterados[$produto->id] = true;
                }
            }
        });

        if ($ajustes === 0) {
            return back()->with('success', 'Nenhuma alteração detectada — estoque já estava atualizado.');
        }

        $nProdutos = count($produtosAlterados);

        return back()->with('success', "Estoque atualizado — {$ajustes} ajuste(s) em {$nProdutos} produto(s).");
    }

    /**
     * As lojas que o usuário logado enxerga nas telas de multiloja.
     *
     * Admin/Dono veem a empresa inteira. Os demais perfis (hoje o gerente,
     * liberado em 02/09/2026) veem só as lojas às quais estão VINCULADOS em
     * `unidade_user` — mesmo critério do LojaController::podeEditar, com o
     * mesmo fallback para a loja da sessão quando não há vínculo nenhum.
     *
     * É aqui que mora o escopo: as rotas de multiloja passam pela matriz
     * (`permission:multilojas`), que diz QUEM entra; este método diz o que
     * cada um vê depois de entrar. O `ajustarEstoque` usa a MESMA lista, senão
     * um POST forjado gravaria em loja fora do alcance.
     *
     * ⚠️ O UnidadeScope prende os não-dono à unidade da SESSÃO — uma loja só.
     * A tela de multiloja quebra isso de propósito, então o recorte tem que ser
     * explícito. Não trocar por `Unidade::where('empresa_id', ...)` sem filtro.
     */
    private function unidadesVisiveis()
    {
        $user = auth()->user();
        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;

        $query = Unidade::where('empresa_id', $user->empresa_id)
            ->where('status', 'ativa')
            ->orderBy('nome');

        if ($user->is_admin || in_array($perfil, ['admin', 'dono'], true)) {
            return $query->get();
        }

        $vinculadas = $user->unidades()->pluck('unidades.id');

        if ($vinculadas->isEmpty()) {
            $vinculadas = collect(array_filter([session('unidade_id')]));
        }

        return $query->whereIn('id', $vinculadas)->get();
    }
}
