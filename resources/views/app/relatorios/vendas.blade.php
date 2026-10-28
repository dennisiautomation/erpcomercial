@extends('layouts.app')

@section('title', 'Relatório de Vendas')

@push('styles')
<style>
    /* Cabeçalho que só existe no papel */
    .rel-print-header { display: none; }

    .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
    @media (max-width: 991.98px) { .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .kpi { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; padding: .85rem 1rem; }
    .kpi-label { font-size: .78rem; color: #6b7280; margin-bottom: .15rem; }
    .kpi-value { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
    .kpi-sub { font-size: .75rem; color: #6b7280; margin-top: .15rem; }
    .kpi-ico { float: right; opacity: .55; font-size: 1.25rem; }

    @media print {
        /* Some com a moldura do sistema: só o relatório vai para o papel */
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
        .kpi-label { font-size: 8pt; color: #333; }
        .kpi-value { font-size: 12pt; color: #000 !important; }
        .kpi-sub { font-size: 7.5pt; color: #333; }
        .kpi-ico { display: none; }

        .card { border: 1px solid #999 !important; box-shadow: none !important; border-radius: 4px !important; break-inside: avoid; margin-bottom: 8px !important; }
        .card-header { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding: 4px 8px !important; }
        .card-header h6 { font-size: 10pt; }
        .table { font-size: 8.5pt; margin-bottom: 0; }
        .table th, .table td { padding: 2px 5px !important; }
        .table-light, thead { background: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .badge { border: 1px solid #777; background: #fff !important; color: #000 !important; font-weight: 600; }
        .text-success, .text-info, .text-warning, .text-primary, .text-danger { color: #000 !important; }
        /* Flex não pagina no Chrome: linhas de um card lado a lado somem do papel.
           Na impressão as colunas viram float, que quebra de página normalmente. */
        .row.g-3, .row.g-4 { display: block !important; }
        .row.g-3::after, .row.g-4::after { content: ""; display: block; clear: both; }
        .row.g-3 > [class*="col-"], .row.g-4 > [class*="col-"] { float: left; width: 50%; padding: 0 3px; box-sizing: border-box; }
        .row.g-3 > .col-lg-5 { width: 42%; }
        .row.g-3 > .col-lg-7 { width: 58%; }
        .card.h-100 { height: auto !important; }
        .table-responsive { overflow: visible !important; }
        /* A lista de vendas atravessa páginas: sem moldura no card (a caixa vazia
           que sobra no pé da página anterior é feia); a tabela leva a própria borda. */
        .rel-vendas-tabela { clear: both; page-break-before: auto; border: none !important; }
        .rel-vendas-tabela .card-header { border: 1px solid #999 !important; border-bottom: none !important; }
        .rel-vendas-tabela .table { border: 1px solid #999; }
        .rel-vendas-tabela, .rel-top { break-inside: auto; }
        /* Título do quadro não fica sozinho no pé da página */
        .card-header, thead { break-after: avoid; page-break-after: avoid; }
        .card-header + .table-responsive tbody tr:first-child { break-before: avoid; page-break-before: avoid; }
        .text-nowrap { white-space: nowrap !important; }

        @page { size: A4; margin: 12mm; }
    }
</style>
@endpush

@section('content')
@php
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $qtd = fn ($v) => number_format((float) $v, (fmod((float) $v, 1) == 0 ? 0 : 3), ',', '.');
    $mostraLoja = isset($lojas) && $lojas->count() > 1 && empty($lojaFiltro);
    $origemLabel = ['vendas' => 'Vendas (PDV/balcão)', 'pedidos' => 'Pedidos faturados'][request('origem')] ?? 'Todas';
    $vendedorNome = $vendedorId ? ($vendedores->firstWhere('id', $vendedorId)->name ?? '-') : 'Todos';
@endphp

{{-- Cabeçalho do impresso (só no papel) --}}
<div class="rel-print-header">
    <div class="d-flex justify-content-between align-items-end">
        <div>
            <h2>{{ $empresa?->nome_fantasia ?: ($empresa?->razao_social ?? 'ERP Comercial') }}</h2>
            <div class="meta"><strong>Relatório de Vendas</strong> · {{ $lojaNome }}</div>
        </div>
        <div class="meta text-end">
            Período: <strong>{{ $dataInicio->format('d/m/Y') }} a {{ $dataFim->format('d/m/Y') }}</strong><br>
            Origem: {{ $origemLabel }} · Vendedor: {{ $vendedorNome }} · Cliente: {{ $clienteFiltro->nome_razao_social ?? 'Todos' }}<br>
            Gerado em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
    <div>
        <h4 class="mb-1"><i class="bi bi-bar-chart-line me-2"></i>Relatório de Vendas</h4>
        <p class="text-muted mb-0 small">{{ $lojaNome }} · Período: {{ $dataInicio->format('d/m/Y') }} a {{ $dataFim->format('d/m/Y') }}</p>
    </div>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4 d-print-none">
    <div class="card-body py-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="campo-data_inicio" class="form-label small fw-semibold">Data Início</label>
                <input id="campo-data_inicio" type="date" name="data_inicio" class="form-control form-control-sm" value="{{ $dataInicio->format('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label for="campo-data_fim" class="form-label small fw-semibold">Data Fim</label>
                <input id="campo-data_fim" type="date" name="data_fim" class="form-control form-control-sm" value="{{ $dataFim->format('Y-m-d') }}">
            </div>
            @if(isset($lojas) && $lojas->count() > 1)
            <div class="col-md-2">
                <label for="campo-loja" class="form-label small fw-semibold">Loja</label>
                <select id="campo-loja" name="loja" class="form-select form-select-sm">
                    <option value="todas" @selected(empty($lojaFiltro) && request()->filled('loja'))>Todas as lojas</option>
                    @foreach($lojas as $loja)
                        <option value="{{ $loja->id }}" @selected((int) ($lojaFiltro ?? 0) === $loja->id)>{{ $loja->nome }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <label for="campo-origem" class="form-label small fw-semibold">Origem</label>
                <select id="campo-origem" name="origem" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="vendas" @selected(request('origem') === 'vendas')>Vendas (PDV/balcão)</option>
                    <option value="pedidos" @selected(request('origem') === 'pedidos')>Pedidos faturados</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="campo-vendedor_id" class="form-label small fw-semibold">Vendedor</label>
                {{-- Lista de quem vende (mesma do F3 do PDV) — antes pedia o ID numérico --}}
                <select id="campo-vendedor_id" name="vendedor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($vendedores ?? [] as $v)
                        <option value="{{ $v->id }}" @selected(($vendedorId ?? null) === $v->id)>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="clienteFiltroBusca" class="form-label small fw-semibold">Cliente</label>
                {{-- Autocomplete do erp-core (o mesmo de Pedidos); o id vai no hidden --}}
                <div class="position-relative">
                    <input type="text" class="form-control form-control-sm" id="clienteFiltroBusca"
                           placeholder="Digite o nome..." autocomplete="off"
                           value="{{ $clienteFiltro->nome_razao_social ?? '' }}"
                           data-autocomplete="{{ route('app.search.clientes') }}"
                           data-autocomplete-target="clienteFiltroId"
                           data-autocomplete-display="nome_razao_social">
                    <input type="hidden" name="cliente_id" id="clienteFiltroId" value="{{ $clienteFiltro->id ?? '' }}">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.relatorios.vendas') }}" class="btn btn-outline-secondary btn-sm" title="Limpar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Resumo do período --}}
<div class="kpi-grid mb-4">
    <div class="kpi">
        <i class="bi bi-cart-check kpi-ico text-primary"></i>
        <div class="kpi-label">Vendas</div>
        <div class="kpi-value">{{ $totalVendas }}</div>
        <div class="kpi-sub">{{ number_format($pecasPorVenda, 1, ',', '.') }} peças por venda</div>
    </div>
    <div class="kpi">
        <i class="bi bi-box-seam kpi-ico text-primary"></i>
        <div class="kpi-label">Peças vendidas</div>
        <div class="kpi-value">{{ $qtd($pecas) }}</div>
        <div class="kpi-sub">
            @if($servicosQtd > 0) + {{ $qtd($servicosQtd) }} serviço(s) @else itens de produto @endif
        </div>
    </div>
    <div class="kpi">
        <i class="bi bi-currency-dollar kpi-ico text-success"></i>
        <div class="kpi-label">Faturamento</div>
        <div class="kpi-value text-success">{{ $brl($faturamento) }}</div>
        <div class="kpi-sub">
            @if(($devolucoesValor ?? 0) > 0) líquido de trocas: <strong>{{ $brl($faturamentoLiquido) }}</strong> @else sem trocas no período @endif
        </div>
    </div>
    <div class="kpi">
        <i class="bi bi-receipt kpi-ico text-info"></i>
        <div class="kpi-label">Ticket médio por venda</div>
        <div class="kpi-value text-info">{{ $brl($ticketMedio) }}</div>
        <div class="kpi-sub">preço médio por peça: <strong>{{ $brl($precoMedioPeca) }}</strong></div>
    </div>
    <div class="kpi">
        <i class="bi bi-tag kpi-ico text-secondary"></i>
        <div class="kpi-label">Valor bruto dos itens</div>
        <div class="kpi-value">{{ $brl($subtotalBruto) }}</div>
        <div class="kpi-sub">antes de desconto e acréscimo</div>
    </div>
    <div class="kpi">
        <i class="bi bi-percent kpi-ico text-danger"></i>
        <div class="kpi-label">Descontos concedidos</div>
        <div class="kpi-value text-danger">{{ $brl($descontos) }}</div>
        <div class="kpi-sub">{{ $subtotalBruto > 0 ? number_format($descontos / $subtotalBruto * 100, 1, ',', '.') : '0,0' }}% do valor bruto</div>
    </div>
    <div class="kpi">
        <i class="bi bi-credit-card kpi-ico text-secondary"></i>
        <div class="kpi-label">Acréscimos (cartão / juros)</div>
        <div class="kpi-value">{{ $brl($acrescimos) }}</div>
        <div class="kpi-sub">cobrados do cliente no pagamento</div>
    </div>
    <div class="kpi">
        <i class="bi bi-arrow-repeat kpi-ico text-warning"></i>
        <div class="kpi-label">Trocas / Devoluções</div>
        <div class="kpi-value text-warning">{{ $brl($devolucoesValor ?? 0) }}</div>
        <div class="kpi-sub">{{ $devolucoesQtd ?? 0 }} registro(s) · {{ $qtd($devolucoesPecas ?? 0) }} peça(s) <a href="{{ route('app.trocas.index') }}" class="d-print-none">· ver trocas</a></div>
    </div>
</div>

{{-- Por forma de pagamento + por vendedor --}}
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-wallet2 me-1"></i>Por forma de pagamento</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Forma</th>
                            <th class="text-end">Vendas</th>
                            <th class="text-end">Valor</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($porForma as $f)
                        <tr>
                            <td>{{ $f['label'] }}</td>
                            <td class="text-end text-muted">{{ $f['vendas'] }}</td>
                            <td class="text-end fw-semibold">{{ $brl($f['valor']) }}</td>
                            <td class="text-end text-muted">{{ $totalPorForma > 0 ? number_format($f['valor'] / $totalPorForma * 100, 1, ',', '.') : '0,0' }}%</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Sem dados no período.</td></tr>
                        @endforelse
                    </tbody>
                    @if($porForma->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <td class="fw-semibold">Total recebido</td>
                            <td></td>
                            <td class="text-end fw-bold">{{ $brl($totalPorForma) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
            @if($porForma->isNotEmpty() && abs($totalPorForma - $faturamento) > 0.05)
                <div class="card-footer bg-white small text-muted py-1">
                    O total recebido inclui acréscimos de cartão e juros; o faturamento é o valor das vendas.
                </div>
            @endif
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-1"></i>Por vendedor</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Vendedor</th>
                            <th class="text-end">Vendas</th>
                            <th class="text-end">Peças</th>
                            <th class="text-end">Faturamento</th>
                            <th class="text-end">Ticket</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($porVendedor as $vd)
                        <tr>
                            <td>{{ $vd['nome'] }}</td>
                            <td class="text-end text-muted">{{ $vd['vendas'] }}</td>
                            <td class="text-end text-muted">{{ $qtd($vd['pecas']) }}</td>
                            <td class="text-end fw-semibold">{{ $brl($vd['faturamento']) }}</td>
                            <td class="text-end">{{ $brl($vd['ticket']) }}</td>
                            <td class="text-end text-muted">{{ $faturamento > 0 ? number_format($vd['faturamento'] / $faturamento * 100, 1, ',', '.') : '0,0' }}%</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">Sem dados no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Por loja (só quando o relatório é de todas) --}}
@if($porLoja->isNotEmpty())
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-shop me-1"></i>Por loja</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Loja</th>
                    <th class="text-end">Vendas</th>
                    <th class="text-end">Peças</th>
                    <th class="text-end">Faturamento</th>
                    <th class="text-end">Ticket</th>
                    <th class="text-end">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porLoja as $lj)
                <tr>
                    <td>{{ $lj['nome'] }}</td>
                    <td class="text-end text-muted">{{ $lj['vendas'] }}</td>
                    <td class="text-end text-muted">{{ $qtd($lj['pecas']) }}</td>
                    <td class="text-end fw-semibold">{{ $brl($lj['faturamento']) }}</td>
                    <td class="text-end">{{ $brl($lj['ticket']) }}</td>
                    <td class="text-end text-muted">{{ $faturamento > 0 ? number_format($lj['faturamento'] / $faturamento * 100, 1, ',', '.') : '0,0' }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Top 10 --}}
<div class="row g-4 mb-4 rel-top">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-trophy text-warning me-1"></i>Top 10 Produtos</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40">#</th>
                            <th>Produto</th>
                            <th class="text-end">Peças</th>
                            <th class="text-end">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProdutos as $index => $item)
                        <tr>
                            <td>
                                @if($index < 3)
                                    <span class="badge bg-{{ ['warning', 'secondary', 'danger'][$index] }} bg-opacity-{{ $index === 0 ? '100' : '75' }}">{{ $index + 1 }}</span>
                                @else
                                    <span class="text-muted">{{ $index + 1 }}</span>
                                @endif
                            </td>
                            <td>{{ $item->produto->descricao ?? '-' }} @if($item->produto?->codigo_interno)<small class="text-muted">({{ $item->produto->codigo_interno }})</small>@endif</td>
                            <td class="text-end text-muted">{{ $qtd($item->qtd_vendida) }}</td>
                            <td class="text-end fw-semibold">{{ $brl($item->faturamento) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Sem dados no período.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-people text-primary me-1"></i>Top 10 Clientes</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40">#</th>
                            <th>Cliente</th>
                            <th class="text-end">Vendas</th>
                            <th class="text-end">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topClientes as $index => $item)
                        <tr>
                            <td>
                                @if($index < 3)
                                    <span class="badge bg-{{ ['warning', 'secondary', 'danger'][$index] }} bg-opacity-{{ $index === 0 ? '100' : '75' }}">{{ $index + 1 }}</span>
                                @else
                                    <span class="text-muted">{{ $index + 1 }}</span>
                                @endif
                            </td>
                            <td>{{ $item->cliente->nome_razao_social ?? '-' }}</td>
                            <td class="text-end text-muted">{{ $item->total_vendas }}</td>
                            <td class="text-end fw-semibold">{{ $brl($item->faturamento) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Sem dados no período.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Vendas Table --}}
<div class="card shadow-sm mb-4 rel-vendas-tabela">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-1"></i>Vendas no Período</h6>
        <span class="badge bg-secondary">{{ $vendas->count() }} registro(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data</th>
                    <th>Número</th>
                    @if($mostraLoja)<th>Loja</th>@endif
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Forma Pgto</th>
                    <th class="text-end">Peças</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendas as $venda)
                <tr>
                    <td class="text-nowrap">{{ $venda->created_at->format('d/m/Y H:i') }}</td>
                    <td><strong>#{{ $venda->numero ?? $venda->id }}</strong>
                        @if($venda->status->value === 'devolvida')<span class="badge bg-warning text-dark ms-1" title="Itens devolvidos/trocados">devolvida</span>@endif
                    </td>
                    @if($mostraLoja)<td><small>{{ $venda->unidade->nome ?? '-' }}</small></td>@endif
                    <td>{{ $venda->cliente->nome_razao_social ?? 'Consumidor' }}</td>
                    <td>{{ $venda->vendedor->name ?? '-' }}</td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            {{ \App\Models\MovimentacaoCaixa::FORMAS_LABELS[$venda->forma_pagamento] ?? ucfirst(str_replace('_', ' ', $venda->forma_pagamento ?? '-')) }}
                        </span>
                    </td>
                    <td class="text-end text-muted">{{ $qtd($venda->itens->whereNull('servico_id')->sum('quantidade')) }}</td>
                    <td class="text-end fw-bold text-nowrap">{{ $brl($venda->total) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $mostraLoja ? 8 : 7 }}" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                        Nenhuma venda no período selecionado.
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($vendas->count() > 0)
            <tfoot class="table-light">
                <tr>
                    <td colspan="{{ $mostraLoja ? 6 : 5 }}" class="text-end fw-semibold">Total do Período ({{ $totalVendas }} vendas):</td>
                    <td class="text-end fw-bold">{{ $qtd($pecas) }}</td>
                    <td class="text-end fw-bold text-success text-nowrap">{{ $brl($faturamento) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
