<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusVenda;
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
use App\Models\User;
use App\Models\Cliente;
use App\Models\Venda;
use App\Models\VendaItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    public function vendas(Request $request)
    {
        // Admin da plataforma não tem empresa (armadilha 25): a empresa vem da sessão —
        // e a sessão dele também nasce com empresa_id NULL, então o último recurso é a LOJA
        // escolhida (11/09/2026: antes o admin via o relatório zerado, sem erro nenhum).
        $empresaId = auth()->user()->empresa_id
            ?? session('empresa_id')
            ?? Unidade::withoutGlobalScopes()->whereKey(session('unidade_id'))->value('empresa_id');

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)
            : now()->startOfMonth();

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)
            : now()->endOfMonth();

        // Filtro por loja (03/09/2026): admin/dono/gerente escolhem; os demais
        // seguem presos à loja da sessão pelo UnidadeScope. Padrão = loja atual.
        // Várias lojas de uma vez (06/11/2026, STILO): `lojas[]` com quantas o perfil
        // pode ver, sem trocar a loja da sessão. `loja=todas` / `loja=ID` (links antigos)
        // continuam valendo. Id fora da lista do perfil é ignorado.
        $lojas = $this->lojasParaFiltro($request->user());
        $lojasSelecionadas = $this->lojasSelecionadas($request, $lojas);
        // Uma loja só = $lojaFiltro (como antes); várias = null e o recorte é a lista.
        $lojaFiltro = $lojasSelecionadas->count() === 1 ? $lojasSelecionadas->first() : null;
        $escopoLoja = function ($q) use ($lojaFiltro, $lojas, $lojasSelecionadas) {
            if ($lojaFiltro) {
                $q->where('unidade_id', $lojaFiltro);
            } elseif ($lojas->isNotEmpty()) {
                $q->whereIn('unidade_id', $lojasSelecionadas);
            } else {
                // Perfil sem filtro (vendedor/caixa/...): preso à loja da sessão,
                // como o UnidadeScope fazia — ele sai da consulta logo abaixo.
                $q->where('unidade_id', (int) session('unidade_id'));
            }
        };

        // Vendedor e cliente viram lista (03/09/2026): a tela pedia o ID numérico.
        // Os dois são validados contra a empresa — id de outro tenant é ignorado.
        $vendedores = User::where('empresa_id', $empresaId)
            ->whereIn('perfil', ['dono', 'gerente', 'vendedor', 'caixa'])
            ->where('status', 'ativo')
            ->orderBy('name')
            ->get(['id', 'name']);
        $vendedorId = $request->filled('vendedor_id') && $vendedores->contains('id', (int) $request->vendedor_id)
            ? (int) $request->vendedor_id : null;
        $clienteFiltro = $request->filled('cliente_id')
            ? Cliente::where('empresa_id', $empresaId)->find((int) $request->cliente_id)
            : null;

        // Canceladas ficam fora do faturamento (antes entravam — 03/09/2026)
        // Sem o UnidadeScope (04/11/2026): ele prende todo perfil que não é
        // admin/dono à loja da SESSÃO, por cima do filtro — o gerente escolhia
        // outra loja vinculada ou "Todas" e via sempre só a loja atual (STILO).
        // Quem recorta agora é $escopoLoja, com a lista do lojasParaFiltro.
        $query = Venda::withoutGlobalScope(\App\Scopes\UnidadeScope::class)
            ->with(['cliente', 'vendedor', 'unidade:id,nome'])
            ->where('empresa_id', $empresaId)
            ->where('status', '!=', StatusVenda::Cancelada)
            ->tap($escopoLoja)
            ->whereBetween('created_at', [$dataInicio, $dataFim->endOfDay()]);

        // Origem (06/11/2026): Loja / WhatsApp / Online / Histórico — Venda::scopeDaOrigem
        // (aceita ainda `vendas`/`pedidos` dos links antigos)
        $query->daOrigem($request->origem);

        if ($vendedorId) {
            $query->where('vendedor_id', $vendedorId);
        }

        if ($clienteFiltro) {
            $query->where('cliente_id', $clienteFiltro->id);
        }

        // Itens vêm junto: é deles que sai a quantidade de PEÇAS (11/09/2026)
        $vendas = $query->with('itens:id,venda_id,produto_id,servico_id,quantidade')
            ->orderByDesc('created_at')
            ->get();

        // Peça = item de produto. Serviço não é peça e conta à parte.
        $pecasDaVenda = fn (Venda $v) => (float) $v->itens->whereNull('servico_id')->sum('quantidade');

        $totalVendas = $vendas->count();
        $faturamento = (float) $vendas->sum('total');
        $ticketMedio = $totalVendas > 0 ? $faturamento / $totalVendas : 0;
        $pecas = (float) $vendas->sum($pecasDaVenda);
        $servicosQtd = (float) $vendas->sum(fn ($v) => $v->itens->whereNotNull('servico_id')->sum('quantidade'));
        $pecasPorVenda = $totalVendas > 0 ? $pecas / $totalVendas : 0;
        $precoMedioPeca = $pecas > 0 ? $faturamento / $pecas : 0;
        $subtotalBruto = (float) $vendas->sum('subtotal');
        $descontos = (float) $vendas->sum('desconto_valor');
        // Juros de parcelamento + acréscimo do cartão (acessor lê o JSON de pagamento)
        $acrescimos = (float) $vendas->sum(fn ($v) => $v->outras_despesas);

        // Por forma de pagamento: o split mora em `pagamento_detalhes`; venda sem
        // o JSON (importada/antiga) cai na `forma_pagamento` com o total. O troco
        // abate só o dinheiro — é a mesma regra do caixa (PdvController).
        $porForma = [];
        foreach ($vendas as $v) {
            $dets = is_array($v->pagamento_detalhes) && count($v->pagamento_detalhes) > 0
                ? $v->pagamento_detalhes
                : [['forma' => $v->forma_pagamento ?: 'outros', 'valor' => (float) $v->total]];
            $trocoRestante = (float) ($v->troco ?? 0);
            $contadas = [];
            foreach ($dets as $pg) {
                $forma = (string) ($pg['forma'] ?? 'outros');
                $valor = (float) ($pg['valor'] ?? 0);
                if ($forma === 'dinheiro' && $trocoRestante > 0) {
                    $abate = min($valor, $trocoRestante);
                    $valor -= $abate;
                    $trocoRestante -= $abate;
                }
                $porForma[$forma] ??= ['forma' => $forma, 'label' => \App\Models\MovimentacaoCaixa::FORMAS_LABELS[$forma] ?? ucfirst(str_replace('_', ' ', $forma)), 'vendas' => 0, 'valor' => 0.0];
                $porForma[$forma]['valor'] += $valor;
                if (! isset($contadas[$forma])) {
                    $porForma[$forma]['vendas']++;
                    $contadas[$forma] = true;
                }
            }
        }
        $porForma = collect($porForma)->sortByDesc('valor')->values();
        $totalPorForma = (float) $porForma->sum('valor');

        // Por vendedor e por loja: mesmo agrupamento, chave diferente
        $agrupar = function (string $chave, callable $nome) use ($vendas, $pecasDaVenda) {
            return $vendas->groupBy($chave)->map(function ($grupo) use ($nome, $pecasDaVenda) {
                $n = $grupo->count();
                $fat = (float) $grupo->sum('total');
                return [
                    'nome' => $nome($grupo->first()),
                    'vendas' => $n,
                    'pecas' => (float) $grupo->sum($pecasDaVenda),
                    'faturamento' => $fat,
                    'ticket' => $n > 0 ? $fat / $n : 0,
                ];
            })->sortByDesc('faturamento')->values();
        };
        $porVendedor = $agrupar('vendedor_id', fn ($v) => $v->vendedor->name ?? 'Sem vendedor');
        $porLoja = $lojasSelecionadas->count() > 1
            ? $agrupar('unidade_id', fn ($v) => $v->unidade->nome ?? '-')
            : collect();

        // Trocas/devoluções do período (03/09/2026): o que saiu do faturamento
        $devolucoesQuery = \App\Models\Devolucao::withoutGlobalScope(\App\Scopes\UnidadeScope::class)
            ->where('empresa_id', $empresaId)
            ->where('status', '!=', 'cancelada')
            ->tap($escopoLoja)
            ->whereBetween('created_at', [$dataInicio, $dataFim->endOfDay()]);
        $devolucoesQtd = (clone $devolucoesQuery)->count();
        $devolucoesValor = (float) (clone $devolucoesQuery)->sum('valor_estornado');
        $devolucoesPecas = (float) DB::table('devolucao_itens')
            ->whereIn('devolucao_id', (clone $devolucoesQuery)->pluck('id'))
            ->whereNull('deleted_at')
            ->sum('quantidade');
        $faturamentoLiquido = round($faturamento - $devolucoesValor, 2);

        // Top 10 produtos e clientes SÓ das vendas filtradas (11/09/2026): antes
        // somavam a empresa inteira, canceladas inclusas, com o topo dizendo o nome
        // da loja e do vendedor filtrados.
        $vendaIds = $vendas->pluck('id');
        $topProdutos = VendaItem::select(
                'produto_id',
                DB::raw('SUM(quantidade) as qtd_vendida'),
                DB::raw('SUM(total) as faturamento')
            )
            ->whereIn('venda_id', $vendaIds)
            ->whereNotNull('produto_id')
            ->groupBy('produto_id')
            ->with('produto:id,descricao,codigo_interno')
            ->orderByDesc('faturamento')
            ->limit(10)
            ->get();

        $topClientes = $vendas->whereNotNull('cliente_id')
            ->groupBy('cliente_id')
            ->map(fn ($grupo) => (object) [
                'cliente' => $grupo->first()->cliente,
                'total_vendas' => $grupo->count(),
                'faturamento' => (float) $grupo->sum('total'),
            ])
            ->sortByDesc('faturamento')
            ->take(10)
            ->values();

        // Cabeçalho do impresso: empresa, loja e quem gerou
        $empresa = \App\Models\Empresa::find($empresaId);
        if ($lojas->count() > 1 && $lojasSelecionadas->count() === $lojas->count()) {
            $lojaNome = 'Todas as lojas';
        } elseif ($lojasSelecionadas->count() > 1) {
            $lojaNome = $lojas->whereIn('id', $lojasSelecionadas->all())->pluck('nome')->implode(', ');
        } else {
            $lojaNome = \App\Models\Unidade::withoutGlobalScopes()->find($lojaFiltro)?->nome ?? '-';
        }

        return view('app.relatorios.vendas', compact(
            'vendas', 'totalVendas', 'faturamento', 'ticketMedio',
            'pecas', 'servicosQtd', 'pecasPorVenda', 'precoMedioPeca',
            'subtotalBruto', 'descontos', 'acrescimos',
            'porForma', 'totalPorForma', 'porVendedor', 'porLoja',
            'topProdutos', 'topClientes', 'dataInicio', 'dataFim',
            'lojas', 'lojaFiltro', 'lojasSelecionadas', 'vendedores', 'vendedorId', 'clienteFiltro',
            'devolucoesQtd', 'devolucoesValor', 'devolucoesPecas', 'faturamentoLiquido',
            'empresa', 'lojaNome'
        ));
    }

    /** Mesmo critério de VendaController::lojasParaFiltro (+ gerente, que vê as suas). */
    /**
     * Produtos vendidos (06/11/2026, pedido STILO): item a item e resumo por produto,
     * com forma de pagamento, loja da venda e loja de onde a peça saiu.
     *
     * Valor do item = parte dele no total da venda (rateio pelo total do item):
     * assim desconto/acréscimo no total da venda caem nos itens e a soma bate com o
     * faturamento do Relatório de Vendas. Venda paga em mais de uma forma: o item é
     * repartido entre as formas na proporção do que foi pago (regra do troco = a do
     * Relatório de Vendas: troco abate só o dinheiro).
     */
    public function produtos(Request $request)
    {
        $empresaId = auth()->user()->empresa_id
            ?? session('empresa_id')
            ?? Unidade::withoutGlobalScopes()->whereKey(session('unidade_id'))->value('empresa_id');

        $dataInicio = $request->filled('data_inicio') ? Carbon::parse($request->data_inicio) : now()->startOfMonth();
        $dataFim = $request->filled('data_fim') ? Carbon::parse($request->data_fim) : now()->endOfMonth();

        // Lojas da VENDA: mesma regra do Relatório de Vendas
        $lojas = $this->lojasParaFiltro($request->user());
        $lojasSelecionadas = $this->lojasSelecionadas($request, $lojas);
        if ($lojas->isEmpty()) {
            $lojasSelecionadas = collect([(int) session('unidade_id')]);
        }

        // Loja de onde SAIU: qualquer loja ativa da empresa (a peça vendida aqui pode
        // sair de outra). Padrão = todas marcadas = sem filtro. Item sem loja de saída
        // gravada saiu da própria loja da venda.
        $lojasSaida = Unidade::withoutGlobalScopes()->where('empresa_id', $empresaId)
            ->where('status', 'ativa')->orderBy('nome')->get(['id', 'nome']);
        $saidaSelecionadas = $lojasSaida->pluck('id')->map(fn ($id) => (int) $id);
        if (is_array($request->input('lojas_saida'))) {
            $marcadas = collect($request->input('lojas_saida'))->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $lojasSaida->contains('id', $id))->unique()->values();
            if ($marcadas->isNotEmpty()) {
                $saidaSelecionadas = $marcadas;
            }
        }
        $filtraSaida = $saidaSelecionadas->count() < $lojasSaida->count();

        $vendedores = User::where('empresa_id', $empresaId)
            ->whereIn('perfil', ['dono', 'gerente', 'vendedor', 'caixa'])
            ->where('status', 'ativo')->orderBy('name')->get(['id', 'name']);
        $vendedorId = $request->filled('vendedor_id') && $vendedores->contains('id', (int) $request->vendedor_id)
            ? (int) $request->vendedor_id : null;
        $clienteFiltro = $request->filled('cliente_id')
            ? Cliente::where('empresa_id', $empresaId)->find((int) $request->cliente_id) : null;
        $produtoFiltro = $request->filled('produto_id')
            ? Produto::withoutGlobalScopes()->where('empresa_id', $empresaId)->find((int) $request->produto_id) : null;
        $categorias = Categoria::withoutGlobalScopes()->where('empresa_id', $empresaId)->orderBy('nome')->get(['id', 'nome']);
        $categoriaId = $request->filled('categoria_id') && $categorias->contains('id', (int) $request->categoria_id)
            ? (int) $request->categoria_id : null;
        $formas = \App\Models\MovimentacaoCaixa::FORMAS_LABELS;
        $formaFiltro = array_key_exists((string) $request->forma, $formas) ? (string) $request->forma : null;

        $query = Venda::withoutGlobalScope(\App\Scopes\UnidadeScope::class)
            ->with(['cliente:id,nome_razao_social', 'vendedor:id,name'])
            ->where('empresa_id', $empresaId)
            ->where('status', '!=', StatusVenda::Cancelada)
            ->whereIn('unidade_id', $lojasSelecionadas)
            ->whereBetween('created_at', [$dataInicio, $dataFim->copy()->endOfDay()]);
        $query->daOrigem($request->origem);
        if ($vendedorId) {
            $query->where('vendedor_id', $vendedorId);
        }
        if ($clienteFiltro) {
            $query->where('cliente_id', $clienteFiltro->id);
        }
        $vendas = $query->orderBy('created_at')->get()->keyBy('id');

        $itensTodos = VendaItem::query()
            ->whereIn('venda_id', $vendas->keys())
            ->with(['produto' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'codigo_interno', 'descricao', 'categoria_id')])
            ->get(['id', 'venda_id', 'produto_id', 'servico_id', 'unidade_origem_id', 'descricao', 'quantidade', 'preco_unitario', 'desconto_valor', 'total']);
        $somaItensPorVenda = $itensTodos->groupBy('venda_id')->map(fn ($g) => (float) $g->sum('total'));
        $nomesLoja = Unidade::withoutGlobalScopes()->where('empresa_id', $empresaId)->pluck('nome', 'id');
        $nomesCategoria = $categorias->pluck('nome', 'id');

        // Quanto cada forma pagou de cada venda (fração do total)
        $fracoesForma = function (Venda $v) use ($formas) {
            $dets = is_array($v->pagamento_detalhes) && count($v->pagamento_detalhes) > 0
                ? $v->pagamento_detalhes
                : [['forma' => $v->forma_pagamento ?: 'outros', 'valor' => (float) $v->total]];
            $troco = (float) ($v->troco ?? 0);
            $valores = [];
            foreach ($dets as $pg) {
                $forma = (string) ($pg['forma'] ?? 'outros');
                $valor = (float) ($pg['valor'] ?? 0);
                if ($forma === 'dinheiro' && $troco > 0) {
                    $abate = min($valor, $troco);
                    $valor -= $abate;
                    $troco -= $abate;
                }
                $valores[$forma] = ($valores[$forma] ?? 0) + $valor;
            }
            $soma = array_sum($valores);
            if ($soma <= 0) {
                return [array_key_first($valores) ?? 'outros' => 1.0];
            }

            return array_map(fn ($x) => $x / $soma, array_filter($valores, fn ($x) => $x > 0));
        };

        $linhas = collect();
        foreach ($itensTodos as $item) {
            $v = $vendas[$item->venda_id];
            $saida = (int) ($item->unidade_origem_id ?: $v->unidade_id);
            if ($filtraSaida && ! $saidaSelecionadas->contains($saida)) {
                continue;
            }
            if ($produtoFiltro && (int) $item->produto_id !== $produtoFiltro->id) {
                continue;
            }
            if ($categoriaId && (int) ($item->produto->categoria_id ?? 0) !== $categoriaId) {
                continue;
            }
            $somaItens = $somaItensPorVenda[$item->venda_id] ?? 0;
            $valorItem = $somaItens > 0 ? (float) $v->total * (float) $item->total / $somaItens : 0.0;
            $fracoes = $fracoesForma($v);
            if ($formaFiltro) {
                if (! isset($fracoes[$formaFiltro])) {
                    continue;
                }
                $fracoes = [$formaFiltro => $fracoes[$formaFiltro]];
            }
            $fatia = array_sum($fracoes);
            $qtd = (float) $item->quantidade;
            $bruto = round((float) $item->preco_unitario * $qtd, 2);
            $linhas->push((object) [
                'data' => $v->created_at,
                'venda_id' => $v->id,
                'venda_numero' => $v->numero ?? $v->id,
                'tipo' => $v->tipo,
                'loja_venda' => $nomesLoja[$v->unidade_id] ?? '-',
                'loja_saida_id' => $saida,
                'loja_saida' => $nomesLoja[$saida] ?? '-',
                'chave' => $item->produto_id ? 'p' . $item->produto_id : ($item->servico_id ? 's' . $item->servico_id : 'd' . md5((string) $item->descricao)),
                'codigo' => $item->produto->codigo_interno ?? '',
                'produto' => $item->produto->descricao ?? $item->descricao,
                'categoria' => $nomesCategoria[$item->produto->categoria_id ?? 0] ?? '',
                'servico' => (bool) $item->servico_id,
                // Com filtro de forma, só a parte paga naquela forma (quantidade proporcional)
                'quantidade' => $qtd * $fatia,
                'preco_unitario' => (float) $item->preco_unitario,
                'bruto' => $bruto * $fatia,
                'total' => $valorItem * $fatia,
                'formas' => array_map(fn ($f) => $valorItem * $f, $fracoes),
                'formas_texto' => implode(' + ', array_map(fn ($f) => $formas[$f] ?? ucfirst(str_replace('_', ' ', $f)), array_keys($fracoes))),
                'vendedor' => $v->vendedor->name ?? '-',
                'cliente' => $v->cliente->nome_razao_social ?? '',
            ]);
        }

        $total = (float) $linhas->sum('total');
        $totalBruto = (float) $linhas->sum('bruto');
        $pecas = (float) $linhas->where('servico', false)->sum('quantidade');
        $vendasCom = $linhas->pluck('venda_id')->unique()->count();

        $ordem = $request->ordem === 'qtd' ? 'quantidade' : ($request->ordem === 'nome' ? 'produto' : 'total');
        $resumo = $linhas->groupBy('chave')->map(fn ($g) => (object) [
            'codigo' => $g->first()->codigo,
            'produto' => $g->first()->produto,
            'categoria' => $g->first()->categoria,
            'quantidade' => (float) $g->sum('quantidade'),
            'bruto' => (float) $g->sum('bruto'),
            'total' => (float) $g->sum('total'),
            'vendas' => $g->pluck('venda_id')->unique()->count(),
        ]);
        $resumo = $ordem === 'produto'
            ? $resumo->sortBy(fn ($r) => mb_strtolower($r->produto))->values()
            : $resumo->sortByDesc($ordem)->values();

        $porForma = [];
        foreach ($linhas as $l) {
            foreach ($l->formas as $f => $valor) {
                $porForma[$f] ??= ['label' => $formas[$f] ?? ucfirst(str_replace('_', ' ', $f)), 'valor' => 0.0];
                $porForma[$f]['valor'] += $valor;
            }
        }
        $porForma = collect($porForma)->sortByDesc('valor')->values();
        $porSaida = $linhas->groupBy('loja_saida_id')->map(fn ($g) => (object) [
            'loja' => $g->first()->loja_saida,
            'quantidade' => (float) $g->where('servico', false)->sum('quantidade'),
            'total' => (float) $g->sum('total'),
        ])->sortByDesc('total')->values();

        $lojaNome = $lojas->count() > 1 && $lojasSelecionadas->count() === $lojas->count()
            ? 'Todas as lojas'
            : $lojasSelecionadas->map(fn ($id) => $nomesLoja[$id] ?? '-')->implode(', ');
        $saidaNome = $filtraSaida
            ? $saidaSelecionadas->map(fn ($id) => $nomesLoja[$id] ?? '-')->implode(', ')
            : 'Todas';

        // Planilha (.xlsx) com tudo, item a item
        if ($request->boolean('planilha')) {
            $cab = ['Data', 'Venda', 'Origem', 'Loja da venda', 'Saiu de', 'Código', 'Produto', 'Categoria', 'Qtd', 'Preço unit.', 'Bruto', 'Total', 'Forma de pagamento', 'Vendedor', 'Cliente'];
            $dados = $linhas->map(fn ($l) => [
                $l->data->format('d/m/Y H:i'), $l->venda_numero, $l->tipo === 'pedido' ? 'Pedido' : 'Venda',
                $l->loja_venda, $l->loja_saida, $l->codigo, $l->produto, $l->categoria,
                round($l->quantidade, 3), round($l->preco_unitario, 2), round($l->bruto, 2), round($l->total, 2),
                $l->formas_texto, $l->vendedor, $l->cliente,
            ])->all();

            return \App\Support\Planilha::download($cab, $dados,
                'produtos-vendidos-' . $dataInicio->format('Y-m-d') . '-a-' . $dataFim->format('Y-m-d'), 'Produtos vendidos');
        }

        $ver = $request->ver === 'itens' ? 'itens' : 'resumo';
        $itensPagina = new \Illuminate\Pagination\LengthAwarePaginator(
            $linhas->sortByDesc('data')->values()->forPage(\Illuminate\Pagination\Paginator::resolveCurrentPage(), 100),
            $linhas->count(), 100, null,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $empresa = \App\Models\Empresa::find($empresaId);

        return view('app.relatorios.produtos', compact(
            'linhas', 'resumo', 'porForma', 'porSaida', 'itensPagina', 'ver', 'ordem',
            'total', 'totalBruto', 'pecas', 'vendasCom', 'dataInicio', 'dataFim',
            'lojas', 'lojasSelecionadas', 'lojasSaida', 'saidaSelecionadas', 'filtraSaida',
            'vendedores', 'vendedorId', 'clienteFiltro', 'produtoFiltro', 'categorias', 'categoriaId',
            'formas', 'formaFiltro', 'lojaNome', 'saidaNome', 'empresa'
        ));
    }

    /**
     * Lojas marcadas no filtro (06/11/2026): `lojas[]` com quantas o perfil pode ver;
     * `loja=todas` / `loja=ID` (links antigos) continuam. Id fora da lista é ignorado;
     * nada válido = loja da sessão. Perfil sem lista (vendedor/caixa) = loja da sessão.
     */
    private function lojasSelecionadas(Request $request, \Illuminate\Support\Collection $lojas): \Illuminate\Support\Collection
    {
        $permitidas = $lojas->pluck('id')->map(fn ($id) => (int) $id);
        $selecionadas = collect([(int) session('unidade_id')]);
        if ($lojas->count() > 1) {
            if (is_array($request->input('lojas'))) {
                $marcadas = collect($request->input('lojas'))->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $permitidas->contains($id))->unique()->values();
                if ($marcadas->isNotEmpty()) {
                    $selecionadas = $marcadas;
                }
            } elseif ($request->filled('loja')) {
                if ($request->loja === 'todas') {
                    $selecionadas = $permitidas;
                } elseif ($permitidas->contains((int) $request->loja)) {
                    $selecionadas = collect([(int) $request->loja]);
                }
            }
        }

        return $selecionadas;
    }

    private function lojasParaFiltro($user): \Illuminate\Support\Collection
    {
        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;

        if (! $user->is_admin && ! in_array($perfil, ['admin', 'dono', 'gerente'])) {
            return collect();
        }

        $empresaId = session('empresa_id')
            ?? \App\Models\Unidade::withoutGlobalScopes()->whereKey(session('unidade_id'))->value('empresa_id');
        $query = \App\Models\Unidade::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('status', 'ativa');

        // Gerente: só as lojas vinculadas (mesmo recorte do MultilojaController)
        if (! $user->is_admin && $perfil === 'gerente') {
            $vinculadas = \Illuminate\Support\Facades\DB::table('unidade_user')->where('user_id', $user->id)->pluck('unidade_id');
            if ($vinculadas->isNotEmpty()) {
                $query->whereIn('id', $vinculadas);
            } else {
                $query->where('id', session('unidade_id'));
            }
        }

        return $query->orderBy('nome')->get(['id', 'nome']);
    }

    /** Origens das movimentações de estoque: chave do filtro => [rótulo, valores de `origem_tipo`]. */
    private const ORIGENS_MOVIMENTACAO = [
        'venda' => ['Venda', [\App\Models\Venda::class]],
        'pedido' => ['Pedido', [\App\Models\Pedido::class]],
        'troca' => ['Troca / devolução', [\App\Models\Devolucao::class]],
        'transferencia' => ['Transferência entre lojas', [\App\Models\TransferenciaEstoque::class]],
        'nfe' => ['Entrada por NF-e', [\App\Models\NFeEntrada::class]],
        'os' => ['Ordem de serviço', [\App\Models\OrdemServico::class]],
        'importacao' => ['Importação de planilha', ['importacao_saldo_inicial']],
        'comodato' => ['Bonificação que volta', ['comodato']],
        'manual' => ['Lançamento manual', []],
    ];

    /**
     * Movimentações de estoque (13/11/2026): o que ENTROU e o que SAIU no período.
     * Só lê `estoque_movimentacoes`. O sentido vem do saldo (depois − antes), não do
     * tipo: ajuste e transferência podem ser para cima ou para baixo.
     */
    public function movimentacoesEstoque(Request $request)
    {
        $empresaId = auth()->user()->empresa_id
            ?? session('empresa_id')
            ?? Unidade::withoutGlobalScopes()->whereKey(session('unidade_id'))->value('empresa_id');

        $data = function ($valor, Carbon $padrao) {
            try {
                return filled($valor) ? Carbon::parse($valor) : $padrao;
            } catch (\Throwable $e) {
                return $padrao;
            }
        };
        $dataInicio = $data($request->data_inicio, now()->startOfMonth())->startOfDay();
        $dataFim = $data($request->data_fim, now())->startOfDay();
        if ($dataFim->lt($dataInicio)) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }

        $lojas = $this->lojasParaFiltro($request->user());
        $lojasSelecionadas = $this->lojasSelecionadas($request, $lojas);
        if ($lojas->isEmpty()) {
            $lojasSelecionadas = collect([(int) session('unidade_id')]);
        }

        $tiposValidos = collect(\App\Enums\TipoMovimentacaoEstoque::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]);
        $tiposSelecionados = collect((array) $request->input('tipos'))->map(fn ($t) => (string) $t)
            ->filter(fn ($t) => $tiposValidos->has($t))->unique()->values();
        $sentido = in_array($request->sentido, ['entrada', 'saida'], true) ? $request->sentido : null;
        $origens = collect(self::ORIGENS_MOVIMENTACAO)->map(fn ($o) => $o[0]);
        $origem = $origens->has((string) $request->origem) ? (string) $request->origem : null;

        $categorias = Categoria::withoutGlobalScopes()->where('empresa_id', $empresaId)->orderBy('nome')->get(['id', 'nome']);
        $categoriaId = $request->filled('categoria_id') && $categorias->contains('id', (int) $request->categoria_id)
            ? (int) $request->categoria_id : null;
        $produtoFiltro = $request->filled('produto_id')
            ? Produto::withoutGlobalScopes()->where('empresa_id', $empresaId)->find((int) $request->produto_id) : null;
        $usuarios = User::withoutGlobalScopes()
            ->whereIn('id', DB::table('estoque_movimentacoes')->where('empresa_id', $empresaId)->select('user_id')->distinct())
            ->orderBy('name')->get(['id', 'name']);
        $usuarioId = $request->filled('usuario_id') && $usuarios->contains('id', (int) $request->usuario_id)
            ? (int) $request->usuario_id : null;

        // Quanto entrou / saiu em cada linha. Linha antiga sem saldo gravado (antes = depois
        // com quantidade > 0) cai no sentido do tipo.
        $sqlEntrou = "CASE WHEN m.quantidade_posterior > m.quantidade_anterior THEN m.quantidade_posterior - m.quantidade_anterior
            WHEN m.quantidade_posterior = m.quantidade_anterior AND m.tipo IN ('entrada','devolucao') THEN m.quantidade ELSE 0 END";
        $sqlSaiu = "CASE WHEN m.quantidade_posterior < m.quantidade_anterior THEN m.quantidade_anterior - m.quantidade_posterior
            WHEN m.quantidade_posterior = m.quantidade_anterior AND m.tipo IN ('saida','perda','bonificacao') THEN m.quantidade ELSE 0 END";

        $base = function () use ($empresaId, $lojasSelecionadas, $dataInicio, $dataFim, $tiposSelecionados, $sentido, $origem,
            $categoriaId, $produtoFiltro, $usuarioId, $sqlEntrou, $sqlSaiu) {
            $q = DB::table('estoque_movimentacoes as m')
                ->join('produtos as p', 'p.id', '=', 'm.produto_id')
                ->where('m.empresa_id', $empresaId)
                ->whereIn('m.unidade_id', $lojasSelecionadas->all())
                ->whereBetween('m.created_at', [$dataInicio, $dataFim->copy()->endOfDay()]);
            if ($tiposSelecionados->isNotEmpty()) {
                $q->whereIn('m.tipo', $tiposSelecionados->all());
            }
            if ($sentido === 'entrada') {
                $q->whereRaw("($sqlEntrou) > 0");
            } elseif ($sentido === 'saida') {
                $q->whereRaw("($sqlSaiu) > 0");
            }
            if ($origem === 'manual') {
                $q->whereNull('m.origem_tipo');
            } elseif ($origem) {
                $q->whereIn('m.origem_tipo', self::ORIGENS_MOVIMENTACAO[$origem][1]);
            }
            if ($categoriaId) {
                $q->where('p.categoria_id', $categoriaId);
            }
            if ($produtoFiltro) {
                $q->where('m.produto_id', $produtoFiltro->id);
            }
            if ($usuarioId) {
                $q->where('m.user_id', $usuarioId);
            }

            return $q;
        };

        $totais = $base()->selectRaw("COUNT(*) as movimentos, COUNT(DISTINCT m.produto_id) as produtos,
            COALESCE(SUM($sqlEntrou), 0) as entrou, COALESCE(SUM($sqlSaiu), 0) as saiu,
            COALESCE(SUM(CASE WHEN ($sqlEntrou) > 0 THEN 1 ELSE 0 END), 0) as mov_entrada,
            COALESCE(SUM(CASE WHEN ($sqlSaiu) > 0 THEN 1 ELSE 0 END), 0) as mov_saida")->first();

        $porTipo = $base()->groupBy('m.tipo')
            ->selectRaw("m.tipo, COUNT(*) as movimentos, SUM($sqlEntrou) as entrou, SUM($sqlSaiu) as saiu")
            ->get()->map(function ($r) use ($tiposValidos) {
                $r->label = $tiposValidos[$r->tipo] ?? ucfirst($r->tipo);

                return $r;
            })->sortByDesc('movimentos')->values();

        $porLoja = $base()->join('unidades as un', 'un.id', '=', 'm.unidade_id')->groupBy('m.unidade_id', 'un.nome')
            ->selectRaw("un.nome as loja, COUNT(*) as movimentos, SUM($sqlEntrou) as entrou, SUM($sqlSaiu) as saiu")
            ->orderBy('un.nome')->get();

        $ordens = ['saiu' => 'saiu DESC', 'entrou' => 'entrou DESC', 'saldo' => 'saldo DESC', 'nome' => 'p.descricao ASC'];
        $ordem = array_key_exists((string) $request->ordem, $ordens) ? (string) $request->ordem : 'saiu';
        $resumoQuery = $base()->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->groupBy('p.id', 'p.codigo_interno', 'p.descricao', 'c.nome')
            ->selectRaw("p.id, p.codigo_interno as codigo, p.descricao as produto, c.nome as categoria, COUNT(*) as movimentos,
                SUM($sqlEntrou) as entrou, SUM($sqlSaiu) as saiu, SUM($sqlEntrou) - SUM($sqlSaiu) as saldo")
            ->orderByRaw($ordens[$ordem])->orderBy('p.descricao')->orderBy('p.id');

        $itensQuery = $base()
            ->join('unidades as un', 'un.id', '=', 'm.unidade_id')
            ->leftJoin('estoques as e', 'e.id', '=', 'm.estoque_id')
            ->leftJoin('users as us', 'us.id', '=', 'm.user_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('vendas as v', function ($j) {
                $j->on('v.id', '=', 'm.origem_id')->where('m.origem_tipo', '=', \App\Models\Venda::class);
            })
            ->selectRaw("m.id, m.created_at, m.tipo, m.quantidade_anterior as antes, m.quantidade_posterior as depois,
                m.origem_tipo, m.origem_id, m.observacoes, ($sqlEntrou) as entrou, ($sqlSaiu) as saiu,
                p.codigo_interno as codigo, p.descricao as produto, c.nome as categoria, un.nome as loja,
                e.nome as local, e.is_padrao as local_padrao, us.name as usuario, v.numero as venda_numero")
            ->orderByDesc('m.created_at')->orderByDesc('m.id');

        $rotuloOrigem = function ($l) {
            if (! $l->origem_tipo) {
                return 'Lançamento manual';
            }
            foreach (self::ORIGENS_MOVIMENTACAO as [$rotulo, $valores]) {
                if (in_array($l->origem_tipo, $valores, true)) {
                    return $l->origem_id ? $rotulo . ' #' . ($l->venda_numero ?: $l->origem_id) : $rotulo;
                }
            }

            return class_basename($l->origem_tipo) . ($l->origem_id ? ' #' . $l->origem_id : '');
        };
        $prepararItem = function ($l) use ($rotuloOrigem, $tiposValidos) {
            $l->data = Carbon::parse($l->created_at);
            $l->tipo_label = $tiposValidos[$l->tipo] ?? ucfirst($l->tipo);
            $l->origem_label = $rotuloOrigem($l);

            return $l;
        };

        $ver = $request->ver === 'itens' ? 'itens' : 'resumo';
        $num = fn ($v) => round((float) $v, 3);
        $periodoArquivo = $dataInicio->format('Y-m-d') . '-a-' . $dataFim->format('Y-m-d');

        // Planilha (.xlsx): a aba que está aberta, inteira (sem paginação)
        if ($request->boolean('planilha')) {
            if ($ver === 'itens') {
                $cab = ['Data', 'Loja', 'Estoque', 'Código', 'Produto', 'Categoria', 'Tipo', 'Entrou', 'Saiu', 'Saldo antes', 'Saldo depois', 'Usuário', 'Lançado por', 'Observações'];
                $dados = $itensQuery->limit(50000)->get()->map($prepararItem)->map(fn ($l) => [
                    $l->data->format('d/m/Y H:i'), $l->loja, $l->local ?? '', $l->codigo, $l->produto, $l->categoria ?? '',
                    $l->tipo_label, $num($l->entrou), $num($l->saiu), $num($l->antes), $num($l->depois),
                    $l->usuario ?? '', $l->origem_label, (string) $l->observacoes,
                ])->all();

                return Planilha::download($cab, $dados, 'movimentacoes-estoque-' . $periodoArquivo, 'Movimentações');
            }
            $cab = ['Código', 'Produto', 'Categoria', 'Entrou', 'Saiu', 'Saldo do período', 'Movimentações'];
            $dados = $resumoQuery->get()->map(fn ($r) => [
                $r->codigo, $r->produto, $r->categoria ?? '', $num($r->entrou), $num($r->saiu), $num($r->saldo), (int) $r->movimentos,
            ])->all();

            return Planilha::download($cab, $dados, 'entradas-e-saidas-por-produto-' . $periodoArquivo, 'Por produto');
        }

        // PDF / impressão: a aba aberta inteira, até o teto (acima disso, a planilha)
        $imprimir = $request->boolean('imprimir');
        $tetoImpressao = 3000;
        $cortado = false;
        if ($imprimir) {
            $consulta = $ver === 'itens' ? $itensQuery : $resumoQuery;
            $todas = $consulta->limit($tetoImpressao + 1)->get();
            $cortado = $todas->count() > $tetoImpressao;
            $todas = $todas->take($tetoImpressao);
            $pagina = new \Illuminate\Pagination\LengthAwarePaginator($todas, $todas->count(), max(1, $todas->count()), 1);
        } else {
            $pagina = ($ver === 'itens' ? $itensQuery->paginate(100, ['*'], 'page', null, (int) $totais->movimentos)
                : $resumoQuery->paginate(100, ['*'], 'page', null, (int) $totais->produtos))->withQueryString();
        }
        $resumo = $ver === 'resumo' ? $pagina : null;
        $itens = $ver === 'itens' ? $pagina->through($prepararItem) : null;

        $lojaNome = $lojas->count() > 1 && $lojasSelecionadas->count() === $lojas->count()
            ? 'Todas as lojas'
            : Unidade::withoutGlobalScopes()->whereIn('id', $lojasSelecionadas->all())->orderBy('nome')->pluck('nome')->implode(', ');
        $empresa = \App\Models\Empresa::find($empresaId);

        return view('app.relatorios.movimentacoes-estoque', compact(
            'totais', 'porTipo', 'porLoja', 'resumo', 'itens', 'ver', 'ordem', 'imprimir', 'cortado', 'tetoImpressao',
            'dataInicio', 'dataFim', 'lojas', 'lojasSelecionadas', 'lojaNome', 'tiposValidos', 'tiposSelecionados',
            'sentido', 'origens', 'origem', 'categorias', 'categoriaId', 'produtoFiltro', 'usuarios', 'usuarioId', 'empresa'
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
