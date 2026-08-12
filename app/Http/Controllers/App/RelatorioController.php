<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Produto;
use Illuminate\Support\Str;
use App\Support\Planilha;
use App\Models\Unidade;
use App\Models\Estoque;
use App\Models\Categoria;
use App\Services\SaldoEstoque;
use App\Models\Venda;
use App\Models\VendaItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    public function vendas(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)
            : now()->startOfMonth();

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)
            : now()->endOfMonth();

        $query = Venda::with(['cliente', 'vendedor'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$dataInicio, $dataFim->endOfDay()]);

        // Origem: vendas diretas (PDV/balcão) × pedidos faturados
        if ($request->origem === 'pedidos') {
            $query->where('tipo', 'pedido');
        } elseif ($request->origem === 'vendas') {
            $query->where(fn ($q) => $q->where('tipo', '!=', 'pedido')->orWhereNull('tipo'));
        }

        if ($request->filled('vendedor_id')) {
            $query->where('vendedor_id', $request->vendedor_id);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        $vendas = $query->orderByDesc('created_at')->get();

        $totalVendas = $vendas->count();
        $faturamento = $vendas->sum('total');
        $ticketMedio = $totalVendas > 0 ? $faturamento / $totalVendas : 0;

        // Top 10 produtos
        $topProdutos = VendaItem::select(
                'produto_id',
                DB::raw('SUM(quantidade) as qtd_vendida'),
                DB::raw('SUM(total) as faturamento')
            )
            ->whereHas('venda', function ($q) use ($empresaId, $dataInicio, $dataFim) {
                $q->where('empresa_id', $empresaId)
                  ->whereBetween('created_at', [$dataInicio, $dataFim->endOfDay()]);
            })
            ->groupBy('produto_id')
            ->with('produto:id,descricao')
            ->orderByDesc('faturamento')
            ->limit(10)
            ->get();

        // Top 10 clientes
        $topClientes = Venda::select(
                'cliente_id',
                DB::raw('COUNT(*) as total_vendas'),
                DB::raw('SUM(total) as faturamento')
            )
            ->where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$dataInicio, $dataFim->endOfDay()])
            ->whereNotNull('cliente_id')
            ->groupBy('cliente_id')
            ->with('cliente:id,nome_razao_social')
            ->orderByDesc('faturamento')
            ->limit(10)
            ->get();

        return view('app.relatorios.vendas', compact(
            'vendas', 'totalVendas', 'faturamento', 'ticketMedio',
            'topProdutos', 'topClientes', 'dataInicio', 'dataFim'
        ));
    }

    public function estoque(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        // Get all products with their last movimentacao to calculate current stock
        $produtos = Produto::where('empresa_id', $empresaId)
            ->with('categoria')
            ->orderBy('descricao')
            ->get();

        // Saldo atual = soma do saldo de CADA estoque (última movimentação por
        // produto+estoque). Pegar só a última da empresa misturava unidades e
        // mostrava saldo errado em empresas multi-loja; desde 12/08 a chave é
        // o estoque, então uma loja com depósito também soma certo.
        $saldosPorProduto = SaldoEstoque::porProdutoDaEmpresa($empresaId);

        $produtos->each(function ($produto) use ($saldosPorProduto) {
            $produto->estoque_atual = (float) ($saldosPorProduto[$produto->id] ?? 0);
            $produto->estoque_status = 'ok';

            if ($produto->estoque_minimo && $produto->estoque_atual <= 0) {
                $produto->estoque_status = 'critico';
            } elseif ($produto->estoque_minimo && $produto->estoque_atual <= $produto->estoque_minimo) {
                $produto->estoque_status = 'baixo';
            }
        });

        // Curva ABC
        $produtosComVendas = VendaItem::select(
                'produto_id',
                DB::raw('SUM(total) as faturamento')
            )
            ->whereHas('venda', function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId);
            })
            ->groupBy('produto_id')
            ->orderByDesc('faturamento')
            ->get();

        $totalFaturamento = $produtosComVendas->sum('faturamento');
        $acumulado = 0;
        $curvaABC = [];

        foreach ($produtosComVendas as $item) {
            $acumulado += (float) $item->faturamento;
            $percentual = $totalFaturamento > 0 ? ($acumulado / $totalFaturamento) * 100 : 0;

            $curva = 'C';
            if ($percentual <= 80) {
                $curva = 'A';
            } elseif ($percentual <= 95) {
                $curva = 'B';
            }

            $curvaABC[$item->produto_id] = $curva;
        }

        return view('app.relatorios.estoque', compact('produtos', 'curvaABC'));
    }

    /**
     * Contagem cega: folha para conferir estoque físico SEM mostrar o saldo do
     * sistema — quem conta não pode ser induzido pelo número esperado.
     *
     * Uma coluna em branco por estoque, mais SKU e código de barras para achar
     * o produto na prateleira.
     */
    public function estoqueCego(Request $request)
    {
        // O admin da plataforma tem empresa_id NULL (armadilha 25): sem este
        // fallback a lista de lojas vinha vazia e a folha saía sem coluna
        // nenhuma. A empresa da sessão é quem manda.
        $empresaId = auth()->user()->empresa_id ?? session('empresa_id');

        $lojas = Unidade::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('status', 'ativa')
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $lojaId = (int) ($request->input('unidade_id') ?: session('unidade_id'));
        if (! $lojas->contains('id', $lojaId)) {
            $lojaId = (int) ($lojas->first()->id ?? 0);
        }

        if (! $lojaId) {
            return redirect()->route('app.dashboard')
                ->with('error', 'Escolha uma loja no topo da tela para montar a folha de contagem.');
        }

        $estoques = Estoque::withoutGlobalScopes()
            ->where('unidade_id', $lojaId)
            ->where('status', 'ativo')
            ->orderByDesc('is_padrao')
            ->orderBy('nome')
            ->get(['id', 'nome']);

        // Filtro de estoques: por padrão todos os da loja
        $selecionados = collect($request->input('estoques', []))->map(fn ($v) => (int) $v)->filter();
        $colunas = $selecionados->isNotEmpty()
            ? $estoques->whereIn('id', $selecionados->all())->values()
            : $estoques;

        $produtos = Produto::where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->when($request->filled('categoria_id'), fn ($q) => $q->where('categoria_id', $request->categoria_id))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = $request->busca;
                $q->where(fn ($s) => $s->where('descricao', 'like', "%{$busca}%")
                    ->orWhere('sku', 'like', "%{$busca}%")
                    ->orWhere('codigo_interno', 'like', "%{$busca}%")
                    ->orWhere('codigo_barras', 'like', "%{$busca}%"));
            })
            ->with('categoria:id,nome')
            ->orderBy('descricao')
            ->get(['id', 'codigo_interno', 'sku', 'codigo_barras', 'descricao', 'categoria_id', 'unidade_medida']);

        // "Só o que tem saldo" evita imprimir catálogo inteiro numa contagem
        // cíclica — mas o saldo NÃO vai para a folha, só decide a linha entrar.
        if ($request->boolean('somente_com_saldo')) {
            $saldos = SaldoEstoque::porProdutoDaEmpresa($empresaId);
            $produtos = $produtos->filter(fn ($p) => ($saldos[$p->id] ?? 0) > 0)->values();
        }

        $categorias = Categoria::where('empresa_id', $empresaId)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $loja = $lojas->firstWhere('id', $lojaId);

        if ($request->input('formato') === 'xlsx') {
            return $this->estoqueCegoXlsx($produtos, $colunas, $loja);
        }

        return view('app.relatorios.estoque-cego', compact(
            'produtos', 'colunas', 'estoques', 'lojas', 'lojaId', 'loja', 'categorias', 'selecionados'
        ));
    }

    /** Mesma folha em .xlsx — nunca CSV (armadilha 26: Excel come zero à esquerda). */
    private function estoqueCegoXlsx($produtos, $colunas, $loja)
    {
        $cabecalhos = ['SKU', 'Código', 'Código de barras', 'Produto', 'Categoria', 'Un'];
        foreach ($colunas as $coluna) {
            $cabecalhos[] = 'Qtd. contada — ' . $coluna->nome;
        }

        $linhas = $produtos->map(function ($p) use ($colunas) {
            $linha = [
                (string) ($p->sku ?? ''),
                (string) ($p->codigo_interno ?? ''),
                (string) ($p->codigo_barras ?? ''),
                $p->descricao,
                $p->categoria->nome ?? '',
                $p->unidade_medida ?? 'UN',
            ];
            // Colunas de contagem saem VAZIAS — é o ponto do cego
            foreach ($colunas as $_) {
                $linha[] = '';
            }

            return $linha;
        })->all();

        $nome = 'contagem-cega-' . Str::slug($loja->nome ?? 'loja') . '-' . now()->format('Y-m-d');

        return Planilha::download($cabecalhos, $linhas, $nome, 'Contagem');
    }

    public function financeiro(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)
            : now()->startOfMonth();

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)
            : now()->endOfMonth();

        // Receitas
        $receitas = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereBetween('pago_em', [$dataInicio, $dataFim])
            ->sum('valor_pago');

        // Despesas
        $despesas = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereBetween('pago_em', [$dataInicio, $dataFim])
            ->sum('valor_pago');

        // Custos (categorias de custo)
        $custos = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereBetween('pago_em', [$dataInicio, $dataFim])
            ->whereIn('categoria', ['custo', 'CMV', 'custo_mercadoria'])
            ->sum('valor_pago');

        $despesasOperacionais = $despesas - $custos;
        $lucroBruto = $receitas - $custos;
        $resultado = $receitas - $despesas;

        // Contas a receber por vencimento
        $contasReceber = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->select(
                DB::raw("CASE
                    WHEN vencimento < CURDATE() THEN 'Vencido'
                    WHEN vencimento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Proximos 7 dias'
                    WHEN vencimento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Proximos 30 dias'
                    ELSE 'Acima de 30 dias'
                END as faixa"),
                DB::raw('SUM(valor) as total'),
                DB::raw('COUNT(*) as quantidade')
            )
            ->groupBy('faixa')
            ->get();

        // Contas a pagar por vencimento
        $contasPagar = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->select(
                DB::raw("CASE
                    WHEN vencimento < CURDATE() THEN 'Vencido'
                    WHEN vencimento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Proximos 7 dias'
                    WHEN vencimento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Proximos 30 dias'
                    ELSE 'Acima de 30 dias'
                END as faixa"),
                DB::raw('SUM(valor) as total'),
                DB::raw('COUNT(*) as quantidade')
            )
            ->groupBy('faixa')
            ->get();

        return view('app.relatorios.financeiro', compact(
            'receitas', 'despesas', 'custos', 'despesasOperacionais',
            'lucroBruto', 'resultado', 'contasReceber', 'contasPagar',
            'dataInicio', 'dataFim'
        ));
    }
}
