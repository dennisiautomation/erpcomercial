@extends('layouts.app')

@section('title', 'Produtos vendidos')

@push('styles')
<style>
    /* Produtos vendidos (06/11/2026) — mesma linguagem do Relatório de Vendas */
    .rel-print-header { display: none; }
    .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
    @media (max-width: 991.98px) { .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .kpi { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; padding: .85rem 1rem; }
    .kpi-label { font-size: .78rem; color: #6b7280; margin-bottom: .15rem; }
    .kpi-value { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
    .kpi-sub { font-size: .75rem; color: #6b7280; margin-top: .15rem; }
    .kpi-ico { float: right; opacity: .55; font-size: 1.25rem; }

    /* Lojas em pílulas: marcada = cheia e legível, também com o mouse em cima */
    .grupo-lojas .btn { transition: none; }
    .grupo-lojas .btn-check:checked + .btn,
    .grupo-lojas .btn-check:checked + .btn:hover,
    .grupo-lojas .btn-check:checked + .btn:focus-visible { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; opacity: 1; }
    .grupo-lojas .btn-check:not(:checked) + .btn:hover { background: rgba(79, 70, 229, .08); color: var(--bs-primary); }
    .grupo-lojas .rotulo-grupo { min-width: 9.5rem; }

    @media print {
        .sidebar, .sidebar-backdrop, .topbar, .trial-banner, .erp-alert, .alert,
        .d-print-none, .main-wrapper > div[style*="sticky"] { display: none !important; }
        .main-wrapper, .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; }
        body { background: #fff !important; color: #000; font-size: 10pt; }
        a { color: inherit !important; text-decoration: none !important; }
        .rel-print-header { display: block !important; border-bottom: 2px solid #000; margin-bottom: 10px; padding-bottom: 6px; }
        .rel-print-header h2 { font-size: 15pt; margin: 0; }
        .rel-print-header .meta { font-size: 9pt; color: #333; }
        .kpi-grid { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; gap: 6px; }
        .kpi { border: 1px solid #999; border-radius: 4px; padding: 5px 8px; break-inside: avoid; }
        .kpi-value { font-size: 12pt; color: #000 !important; }
        .kpi-ico { display: none; }
        .card { border: 1px solid #999 !important; box-shadow: none !important; border-radius: 4px !important; margin-bottom: 8px !important; }
        .card-header { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding: 4px 8px !important; }
        .table { font-size: 8.5pt; margin-bottom: 0; }
        .table th, .table td { padding: 2px 5px !important; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .row.g-3 { display: block !important; }
        .row.g-3::after { content: ""; display: block; clear: both; }
        .row.g-3 > [class*="col-"] { float: left; width: 50%; padding: 0 3px; box-sizing: border-box; }
        .table-responsive { overflow: visible !important; }
        .text-success, .text-primary { color: #000 !important; }
        @page { size: A4; margin: 12mm; }
    }
</style>
@endpush

@section('content')
@php
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $qtd = fn ($v) => number_format((float) $v, (abs(fmod((float) $v, 1)) < 0.0005 ? 0 : 2), ',', '.');
    $origemLabel = ['vendas' => 'Vendas (PDV/balcão)', 'pedidos' => 'Pedidos faturados'][request('origem')] ?? 'Todas';
    $vendedorNome = $vendedorId ? ($vendedores->firstWhere('id', $vendedorId)->name ?? '-') : 'Todos';
    $sel = $lojasSelecionadas->all();
    $selSaida = $saidaSelecionadas->all();
    // Links das abas/ordem preservam todos os filtros
    $url = fn (array $extra) => route('app.relatorios.produtos', array_merge(request()->except('page'), $extra));
@endphp

{{-- Cabeçalho do impresso (só no papel) --}}
<div class="rel-print-header">
    <div class="d-flex justify-content-between align-items-end">
        <div>
            <h2>{{ $empresa?->nome_fantasia ?: ($empresa?->razao_social ?? 'ERP Comercial') }}</h2>
            <div class="meta"><strong>Produtos vendidos</strong> · Lojas: {{ $lojaNome }} · Saiu de: {{ $saidaNome }}</div>
        </div>
        <div class="meta text-end">
            Período: <strong>{{ $dataInicio->format('d/m/Y') }} a {{ $dataFim->format('d/m/Y') }}</strong><br>
            Origem: {{ $origemLabel }} · Vendedor: {{ $vendedorNome }} · Forma: {{ $formaFiltro ? $formas[$formaFiltro] : 'Todas' }}<br>
            Gerado em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 d-print-none flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-box-seam me-2"></i>Produtos vendidos</h4>
        <p class="text-muted mb-0 small">{{ $lojaNome }} · Saiu de: {{ $saidaNome }} · Período: {{ $dataInicio->format('d/m/Y') }} a {{ $dataFim->format('d/m/Y') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ $url(['planilha' => 1]) }}" class="btn btn-outline-success btn-sm" data-no-spinner>
            <i class="bi bi-file-earmark-excel me-1"></i> Planilha
        </a>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

{{-- Filtros --}}
<div class="card shadow-sm mb-4 d-print-none">
    <div class="card-body py-3">
        <form method="GET" class="row g-3 align-items-end" id="form-produtos">
            <input type="hidden" name="ver" value="{{ $ver }}">
            <div class="col-6 col-md-2">
                <label for="campo-data_inicio" class="form-label small fw-semibold">Data Início</label>
                <input id="campo-data_inicio" type="date" name="data_inicio" class="form-control form-control-sm" value="{{ $dataInicio->format('Y-m-d') }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="campo-data_fim" class="form-label small fw-semibold">Data Fim</label>
                <input id="campo-data_fim" type="date" name="data_fim" class="form-control form-control-sm" value="{{ $dataFim->format('Y-m-d') }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="campo-origem" class="form-label small fw-semibold">Origem</label>
                <select id="campo-origem" name="origem" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="vendas" @selected(request('origem') === 'vendas')>Vendas (PDV/balcão)</option>
                    <option value="pedidos" @selected(request('origem') === 'pedidos')>Pedidos faturados</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="campo-forma" class="form-label small fw-semibold">Forma de pagamento</label>
                <select id="campo-forma" name="forma" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($formas as $chave => $rotulo)
                        <option value="{{ $chave }}" @selected($formaFiltro === $chave)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="campo-vendedor_id" class="form-label small fw-semibold">Vendedor</label>
                <select id="campo-vendedor_id" name="vendedor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($vendedores as $v)
                        <option value="{{ $v->id }}" @selected($vendedorId === $v->id)>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="campo-categoria_id" class="form-label small fw-semibold">Categoria</label>
                <select id="campo-categoria_id" name="categoria_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($categorias as $c)
                        <option value="{{ $c->id }}" @selected($categoriaId === $c->id)>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="produtoFiltroBusca" class="form-label small fw-semibold">Produto</label>
                <div class="position-relative">
                    <input type="text" class="form-control form-control-sm" id="produtoFiltroBusca"
                           placeholder="Todos — digite o nome ou o código..." autocomplete="off"
                           value="{{ $produtoFiltro->descricao ?? '' }}"
                           data-autocomplete="{{ route('app.search.produtos') }}"
                           data-autocomplete-target="produtoFiltroId"
                           data-autocomplete-display="descricao">
                    <input type="hidden" name="produto_id" id="produtoFiltroId" value="{{ $produtoFiltro->id ?? '' }}">
                </div>
            </div>
            <div class="col-md-4">
                <label for="clienteFiltroBusca" class="form-label small fw-semibold">Cliente</label>
                <div class="position-relative">
                    <input type="text" class="form-control form-control-sm" id="clienteFiltroBusca"
                           placeholder="Todos — digite o nome..." autocomplete="off"
                           value="{{ $clienteFiltro->nome_razao_social ?? '' }}"
                           data-autocomplete="{{ route('app.search.clientes') }}"
                           data-autocomplete-target="clienteFiltroId"
                           data-autocomplete-display="nome_razao_social">
                    <input type="hidden" name="cliente_id" id="clienteFiltroId" value="{{ $clienteFiltro->id ?? '' }}">
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.relatorios.produtos') }}" class="btn btn-outline-secondary btn-sm" title="Limpar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>

            @if($lojas->count() > 1)
            <div class="col-12 grupo-lojas" id="filtro-lojas">
                <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                    <span class="small fw-semibold me-1 rotulo-grupo"><i class="bi bi-shop me-1"></i>Lojas da venda</span>
                    @foreach($lojas as $loja)
                        <input class="btn-check js-loja" type="checkbox" name="lojas[]" value="{{ $loja->id }}"
                               id="loja-{{ $loja->id }}" autocomplete="off" @checked(in_array($loja->id, $sel))>
                        <label class="btn btn-outline-primary btn-sm rounded-pill" for="loja-{{ $loja->id }}">{{ $loja->nome }}</label>
                    @endforeach
                    <span class="text-muted small">·</span>
                    <button type="button" class="btn btn-link btn-sm p-0 js-todas">Marcar todas</button>
                    <button type="button" class="btn btn-link btn-sm p-0 js-limpar">Limpar</button>
                    <span class="small text-muted ms-1 js-contador" data-vazio="Nenhuma marcada: vale a loja atual."></span>
                </div>
            </div>
            @endif

            @if($lojasSaida->count() > 1)
            <div class="col-12 grupo-lojas" id="filtro-saida">
                <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                    <span class="small fw-semibold me-1 rotulo-grupo"><i class="bi bi-box-arrow-right me-1"></i>Saiu da loja</span>
                    @foreach($lojasSaida as $loja)
                        <input class="btn-check js-loja" type="checkbox" name="lojas_saida[]" value="{{ $loja->id }}"
                               id="saida-{{ $loja->id }}" autocomplete="off" @checked(in_array($loja->id, $selSaida))>
                        <label class="btn btn-outline-primary btn-sm rounded-pill" for="saida-{{ $loja->id }}">{{ $loja->nome }}</label>
                    @endforeach
                    <span class="text-muted small">·</span>
                    <button type="button" class="btn btn-link btn-sm p-0 js-todas">Marcar todas</button>
                    <button type="button" class="btn btn-link btn-sm p-0 js-limpar">Limpar</button>
                    <span class="small text-muted ms-1 js-contador" data-vazio="Nenhuma marcada: vale todas."></span>
                </div>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- Resumo --}}
<div class="kpi-grid mb-4">
    <div class="kpi">
        <i class="bi bi-box-seam kpi-ico text-primary"></i>
        <div class="kpi-label">Peças vendidas</div>
        <div class="kpi-value" id="kpi-pecas">{{ $qtd($pecas) }}</div>
        <div class="kpi-sub">{{ $resumo->count() }} produto(s) diferente(s)</div>
    </div>
    <div class="kpi">
        <i class="bi bi-currency-dollar kpi-ico text-success"></i>
        <div class="kpi-label">Total recebido pelos itens</div>
        <div class="kpi-value text-success" id="kpi-total">{{ $brl($total) }}</div>
        <div class="kpi-sub">já com descontos e acréscimos da venda</div>
    </div>
    <div class="kpi">
        <i class="bi bi-tag kpi-ico"></i>
        <div class="kpi-label">Valor bruto (preço × qtd)</div>
        <div class="kpi-value">{{ $brl($totalBruto) }}</div>
        <div class="kpi-sub">diferença: {{ $brl($total - $totalBruto) }}</div>
    </div>
    <div class="kpi">
        <i class="bi bi-receipt kpi-ico text-info"></i>
        <div class="kpi-label">Vendas com esses itens</div>
        <div class="kpi-value">{{ $vendasCom }}</div>
        <div class="kpi-sub">preço médio por peça: <strong>{{ $brl($pecas > 0 ? $total / $pecas : 0) }}</strong></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-1"></i>Por forma de pagamento</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="tabela-formas">
                    <thead class="table-light"><tr><th>Forma</th><th class="text-end">Valor</th><th class="text-end">%</th></tr></thead>
                    <tbody>
                        @forelse($porForma as $f)
                        <tr>
                            <td>{{ $f['label'] }}</td>
                            <td class="text-end fw-semibold">{{ $brl($f['valor']) }}</td>
                            <td class="text-end text-muted">{{ $total > 0 ? number_format($f['valor'] / $total * 100, 1, ',', '.') : '0,0' }}%</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">Sem dados no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">Venda paga em mais de uma forma: cada item é dividido na proporção do que foi pago.</div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><h6 class="mb-0 fw-semibold"><i class="bi bi-box-arrow-right me-1"></i>Por loja de onde saiu</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="tabela-saida">
                    <thead class="table-light"><tr><th>Loja</th><th class="text-end">Peças</th><th class="text-end">Valor</th><th class="text-end">%</th></tr></thead>
                    <tbody>
                        @forelse($porSaida as $s)
                        <tr>
                            <td>{{ $s->loja }}</td>
                            <td class="text-end text-muted">{{ $qtd($s->quantidade) }}</td>
                            <td class="text-end fw-semibold">{{ $brl($s->total) }}</td>
                            <td class="text-end text-muted">{{ $total > 0 ? number_format($s->total / $total * 100, 1, ',', '.') : '0,0' }}%</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Sem dados no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">Item sem loja de saída gravada conta como saído da loja da venda.</div>
        </div>
    </div>
</div>

{{-- Abas: resumo por produto × item a item --}}
<ul class="nav nav-tabs mb-0 d-print-none">
    <li class="nav-item"><a class="nav-link {{ $ver === 'resumo' ? 'active' : '' }}" href="{{ $url(['ver' => 'resumo']) }}"><i class="bi bi-list-ol me-1"></i>Resumo por produto</a></li>
    <li class="nav-item"><a class="nav-link {{ $ver === 'itens' ? 'active' : '' }}" href="{{ $url(['ver' => 'itens']) }}"><i class="bi bi-list-ul me-1"></i>Item a item <span class="badge bg-light text-dark border">{{ $linhas->count() }}</span></a></li>
</ul>

@if($ver === 'resumo')
<div class="card shadow-sm mb-4" style="border-top-left-radius: 0;">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0 fw-semibold">Resumo por produto</h6>
        <div class="small d-print-none">Ordenar:
            <a href="{{ $url(['ordem' => 'total']) }}" class="{{ $ordem === 'total' ? 'fw-bold' : '' }}">valor</a> ·
            <a href="{{ $url(['ordem' => 'qtd']) }}" class="{{ $ordem === 'quantidade' ? 'fw-bold' : '' }}">quantidade</a> ·
            <a href="{{ $url(['ordem' => 'nome']) }}" class="{{ $ordem === 'produto' ? 'fw-bold' : '' }}">nome</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="tabela-resumo">
            <thead class="table-light">
                <tr>
                    <th>Código</th><th>Produto</th><th>Categoria</th>
                    <th class="text-end">Qtd</th><th class="text-end">Vendas</th>
                    <th class="text-end">Bruto</th><th class="text-end">Total</th>
                    <th class="text-end">Preço médio</th><th class="text-end">%</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resumo as $r)
                <tr>
                    <td class="text-muted small">{{ $r->codigo }}</td>
                    <td>{{ $r->produto }}</td>
                    <td class="small text-muted">{{ $r->categoria }}</td>
                    <td class="text-end">{{ $qtd($r->quantidade) }}</td>
                    <td class="text-end text-muted">{{ $r->vendas }}</td>
                    <td class="text-end text-muted">{{ $brl($r->bruto) }}</td>
                    <td class="text-end fw-semibold">{{ $brl($r->total) }}</td>
                    <td class="text-end">{{ $brl($r->quantidade > 0 ? $r->total / $r->quantidade : 0) }}</td>
                    <td class="text-end text-muted">{{ $total > 0 ? number_format($r->total / $total * 100, 1, ',', '.') : '0,0' }}%</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-5">Nenhum produto vendido com esses filtros.</td></tr>
                @endforelse
            </tbody>
            @if($resumo->isNotEmpty())
            <tfoot class="table-light">
                <tr>
                    <td colspan="3" class="fw-semibold">Total</td>
                    <td class="text-end fw-semibold">{{ $qtd($linhas->sum('quantidade')) }}</td>
                    <td class="text-end">{{ $vendasCom }}</td>
                    <td class="text-end">{{ $brl($totalBruto) }}</td>
                    <td class="text-end fw-bold">{{ $brl($total) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@else
<div class="card shadow-sm mb-4" style="border-top-left-radius: 0;">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">Item a item</h6>
        <span class="small text-muted d-print-none">100 por página · a planilha traz todos</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="tabela-itens">
            <thead class="table-light">
                <tr>
                    <th>Data</th><th>Venda</th><th>Loja da venda</th><th>Saiu de</th>
                    <th>Produto</th><th class="text-end">Qtd</th><th class="text-end">Preço</th>
                    <th class="text-end">Total</th><th>Forma</th><th>Vendedor</th><th>Cliente</th>
                </tr>
            </thead>
            <tbody>
                @forelse($itensPagina as $l)
                <tr>
                    <td class="text-nowrap small">{{ $l->data->format('d/m/Y H:i') }}</td>
                    <td class="small"><a href="{{ route('app.vendas.show', $l->venda_id) }}">#{{ $l->venda_numero }}</a>@if($l->tipo === 'pedido') <span class="badge bg-light text-dark border">pedido</span>@endif</td>
                    <td class="small">{{ $l->loja_venda }}</td>
                    <td class="small">{{ $l->loja_saida }}</td>
                    <td>{{ $l->produto }} @if($l->codigo)<span class="text-muted small">({{ $l->codigo }})</span>@endif</td>
                    <td class="text-end">{{ $qtd($l->quantidade) }}</td>
                    <td class="text-end text-muted">{{ $brl($l->preco_unitario) }}</td>
                    <td class="text-end fw-semibold">{{ $brl($l->total) }}</td>
                    <td class="small">{{ $l->formas_texto }}</td>
                    <td class="small">{{ $l->vendedor }}</td>
                    <td class="small text-muted">{{ $l->cliente }}</td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center text-muted py-5">Nenhum item vendido com esses filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($itensPagina->hasPages())
    <div class="card-footer bg-white d-print-none">{{ $itensPagina->links() }}</div>
    @endif
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    // Cada fileira de lojas (venda / saída) tem os próprios botões e contador
    document.querySelectorAll('.grupo-lojas').forEach(function (grupo) {
        const marcas = [...grupo.querySelectorAll('.js-loja')];
        const contador = grupo.querySelector('.js-contador');
        const atualizar = () => {
            const n = marcas.filter(m => m.checked).length;
            contador.textContent = n === marcas.length ? '(todas marcadas)'
                : n === 0 ? contador.dataset.vazio
                : '(' + n + (n === 1 ? ' marcada)' : ' marcadas)');
        };
        marcas.forEach(m => m.addEventListener('change', atualizar));
        grupo.querySelector('.js-todas').addEventListener('click', () => { marcas.forEach(m => m.checked = true); atualizar(); });
        grupo.querySelector('.js-limpar').addEventListener('click', () => { marcas.forEach(m => m.checked = false); atualizar(); });
        atualizar();
    });
    // Apagar o texto do autocomplete limpa o filtro (senão o id antigo fica no hidden)
    [['produtoFiltroBusca', 'produtoFiltroId'], ['clienteFiltroBusca', 'clienteFiltroId']].forEach(([txt, id]) => {
        const campo = document.getElementById(txt);
        if (campo) campo.addEventListener('input', () => { if (!campo.value.trim()) document.getElementById(id).value = ''; });
    });
})();
</script>
@endpush
