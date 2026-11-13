@extends('layouts.app')

@section('title', 'Entradas e saídas do estoque')

@push('styles')
<style>
    /* Entradas e saídas do estoque (13/11/2026) — mesma linguagem do Relatório de Produtos vendidos */
    .rel-print-header { display: none; }
    .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
    @media (max-width: 991.98px) { .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .kpi { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; padding: .85rem 1rem; }
    .kpi-label { font-size: .78rem; color: #6b7280; margin-bottom: .15rem; }
    .kpi-value { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
    .kpi-sub { font-size: .75rem; color: #6b7280; margin-top: .15rem; }
    .kpi-ico { float: right; opacity: .55; font-size: 1.25rem; }

    .grupo-pilulas .btn { transition: none; }
    .grupo-pilulas .btn-check:checked + .btn,
    .grupo-pilulas .btn-check:checked + .btn:hover,
    .grupo-pilulas .btn-check:checked + .btn:focus-visible { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; opacity: 1; }
    .grupo-pilulas .btn-check:not(:checked) + .btn:hover { background: rgba(79, 70, 229, .08); color: var(--bs-primary); }
    .grupo-pilulas .rotulo-grupo { min-width: 7.5rem; }
    .obs-mov { max-width: 16rem; }
    #tabela-itens th { white-space: nowrap; }
    #tabela-itens .col-produto { min-width: 15rem; }

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
        .card-header { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding: 4px 8px !important; break-after: avoid; }
        .table { font-size: 8.5pt; margin-bottom: 0; }
        .table th, .table td { padding: 2px 5px !important; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; } /* total só no fim, não em toda página */
        #tabela-itens .col-produto { min-width: 0; }
        tr { page-break-inside: avoid; }
        .row.g-3 { display: block !important; margin: 0 !important; }
        .row.g-3::after { content: ""; display: block; clear: both; }
        .row.g-3 > [class*="col-"] { float: left; width: 50%; padding: 0 3px; box-sizing: border-box; }
        .h-100 { height: auto !important; }
        .table-responsive { overflow: visible !important; }
        .text-success, .text-danger, .text-primary { color: #000 !important; }
        .badge { border: 1px solid #999; color: #000 !important; background: #fff !important; }
        .obs-mov { max-width: none; }
        @page { size: A4 landscape; margin: 10mm; }
    }
</style>
@endpush

@section('content')
@php
    $qtd = fn ($v) => number_format((float) $v, (abs(fmod((float) $v, 1)) < 0.0005 ? 0 : 3), ',', '.');
    $sel = $lojasSelecionadas->all();
    $selTipos = $tiposSelecionados->all();
    $url = fn (array $extra) => route('app.relatorios.movimentacoes-estoque', array_merge(request()->except(['page', 'imprimir', 'planilha']), $extra));
    $sentidoLabel = ['entrada' => 'Só o que entrou', 'saida' => 'Só o que saiu'][$sentido] ?? 'Entradas e saídas';
    $tiposLabel = $tiposSelecionados->isEmpty() ? 'Todos' : $tiposSelecionados->map(fn ($t) => $tiposValidos[$t])->implode(', ');
    $usuarioNome = $usuarioId ? ($usuarios->firstWhere('id', $usuarioId)->name ?? '-') : 'Todos';
    $categoriaNome = $categoriaId ? ($categorias->firstWhere('id', $categoriaId)->nome ?? '-') : 'Todas';
    $saldoPeriodo = (float) $totais->entrou - (float) $totais->saiu;
    $badgeTipo = fn ($t) => match ($t) {
        'entrada', 'devolucao' => 'bg-success',
        'saida', 'perda' => 'bg-danger',
        'ajuste' => 'bg-warning text-dark',
        'bonificacao' => 'bg-info text-dark',
        'transferencia' => 'bg-primary',
        default => 'bg-secondary',
    };
@endphp

{{-- Cabeçalho do impresso (só no papel / PDF) --}}
<div class="rel-print-header">
    <div class="d-flex justify-content-between align-items-end">
        <div>
            <h2>{{ $empresa?->nome_fantasia ?: ($empresa?->razao_social ?? 'ERP Comercial') }}</h2>
            <div class="meta"><strong>Entradas e saídas do estoque</strong> · {{ $ver === 'itens' ? 'Movimento a movimento' : 'Resumo por produto' }} · Lojas: {{ $lojaNome }}</div>
        </div>
        <div class="meta text-end">
            Período: <strong>{{ $dataInicio->format('d/m/Y') }} a {{ $dataFim->format('d/m/Y') }}</strong> · {{ $sentidoLabel }}<br>
            Tipo: {{ $tiposLabel }} · Lançado por: {{ $origem ? $origens[$origem] : 'Todos' }} · Categoria: {{ $categoriaNome }}
            @if($produtoFiltro) · Produto: {{ $produtoFiltro->descricao }}@endif
            · Usuário: {{ $usuarioNome }}<br>
            Gerado em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 d-print-none flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-arrow-left-right me-2"></i>Entradas e saídas do estoque</h4>
        <p class="text-muted mb-0 small">{{ $lojaNome }} · {{ $dataInicio->format('d/m/Y') }} a {{ $dataFim->format('d/m/Y') }} · {{ $sentidoLabel }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ $url(['planilha' => 1]) }}" class="btn btn-outline-success btn-sm" id="btn-excel" data-no-spinner
           title="Baixa em Excel a aba aberta, inteira">
            <i class="bi bi-file-earmark-excel me-1"></i> Excel
        </a>
        <a href="{{ $url(['imprimir' => 1]) }}" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm" id="btn-pdf" data-no-spinner
           title="Abre a aba aberta, inteira, pronta para salvar em PDF ou imprimir">
            <i class="bi bi-file-earmark-pdf me-1"></i> PDF / Imprimir
        </a>
    </div>
</div>

@if($imprimir)
<div class="alert alert-info d-print-none small">
    <i class="bi bi-printer me-1"></i> Na janela de impressão, escolha <strong>Salvar como PDF</strong> em "Destino" para gerar o arquivo.
    <button type="button" class="btn btn-sm btn-primary ms-2" onclick="window.print()">Abrir a impressão de novo</button>
</div>
@endif
@if($cortado)
<div class="alert alert-warning small">
    <i class="bi bi-exclamation-triangle me-1"></i> O PDF mostra as primeiras {{ number_format($tetoImpressao, 0, ',', '.') }} linhas. Para o relatório inteiro, use o botão <strong>Excel</strong> ou diminua o período.
</div>
@endif

{{-- Filtros --}}
<div class="card shadow-sm mb-4 d-print-none">
    <div class="card-body py-3">
        <form method="GET" class="row g-3 align-items-end" id="form-mov-estoque">
            <input type="hidden" name="ver" value="{{ $ver }}">
            <input type="hidden" name="ordem" value="{{ $ordem }}">
            <div class="col-6 col-md-2">
                <label for="campo-data_inicio" class="form-label small fw-semibold">De</label>
                <input id="campo-data_inicio" type="date" name="data_inicio" class="form-control form-control-sm" value="{{ $dataInicio->format('Y-m-d') }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="campo-data_fim" class="form-label small fw-semibold">Até</label>
                <input id="campo-data_fim" type="date" name="data_fim" class="form-control form-control-sm" value="{{ $dataFim->format('Y-m-d') }}">
            </div>
            <div class="col-12 col-md-4">
                <span class="form-label small fw-semibold d-block">Período rápido</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Período rápido">
                    <button type="button" class="btn btn-outline-secondary js-periodo" data-periodo="hoje">Hoje</button>
                    <button type="button" class="btn btn-outline-secondary js-periodo" data-periodo="7">7 dias</button>
                    <button type="button" class="btn btn-outline-secondary js-periodo" data-periodo="30">30 dias</button>
                    <button type="button" class="btn btn-outline-secondary js-periodo" data-periodo="mes">Este mês</button>
                    <button type="button" class="btn btn-outline-secondary js-periodo" data-periodo="mes-passado">Mês passado</button>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <span class="form-label small fw-semibold d-block">Mostrar</span>
                <div class="btn-group btn-group-sm w-100 grupo-pilulas" role="group" aria-label="Entradas ou saídas">
                    <input type="radio" class="btn-check" name="sentido" id="sentido-tudo" value="" autocomplete="off" @checked(! $sentido)>
                    <label class="btn btn-outline-primary" for="sentido-tudo">Tudo</label>
                    <input type="radio" class="btn-check" name="sentido" id="sentido-entrada" value="entrada" autocomplete="off" @checked($sentido === 'entrada')>
                    <label class="btn btn-outline-primary" for="sentido-entrada"><i class="bi bi-box-arrow-in-down me-1"></i>Só entradas</label>
                    <input type="radio" class="btn-check" name="sentido" id="sentido-saida" value="saida" autocomplete="off" @checked($sentido === 'saida')>
                    <label class="btn btn-outline-primary" for="sentido-saida"><i class="bi bi-box-arrow-up me-1"></i>Só saídas</label>
                </div>
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
            <div class="col-6 col-md-2">
                <label for="campo-categoria_id" class="form-label small fw-semibold">Categoria</label>
                <select id="campo-categoria_id" name="categoria_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($categorias as $c)
                        <option value="{{ $c->id }}" @selected($categoriaId === $c->id)>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="campo-origem" class="form-label small fw-semibold">Lançado por</label>
                <select id="campo-origem" name="origem" class="form-select form-select-sm">
                    <option value="">Tudo</option>
                    @foreach($origens as $chave => $rotulo)
                        <option value="{{ $chave }}" @selected($origem === $chave)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="campo-usuario_id" class="form-label small fw-semibold">Usuário</label>
                <select id="campo-usuario_id" name="usuario_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}" @selected($usuarioId === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1" id="btn-filtrar">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.relatorios.movimentacoes-estoque') }}" class="btn btn-outline-secondary btn-sm" title="Limpar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>

            <div class="col-12 grupo-pilulas" id="filtro-tipos">
                <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                    <span class="small fw-semibold me-1 rotulo-grupo"><i class="bi bi-tags me-1"></i>Tipo</span>
                    @foreach($tiposValidos as $valor => $rotulo)
                        <input class="btn-check js-marca" type="checkbox" name="tipos[]" value="{{ $valor }}"
                               id="tipo-{{ $valor }}" autocomplete="off" @checked(in_array($valor, $selTipos))>
                        <label class="btn btn-outline-primary btn-sm rounded-pill" for="tipo-{{ $valor }}">{{ $rotulo }}</label>
                    @endforeach
                    <span class="text-muted small">·</span>
                    <button type="button" class="btn btn-link btn-sm p-0 js-limpar">Limpar</button>
                    <span class="small text-muted ms-1 js-contador" data-vazio="Nenhum marcado: vale todos os tipos." data-todas="(todos marcados)"></span>
                </div>
            </div>

            @if($lojas->count() > 1)
            <div class="col-12 grupo-pilulas" id="filtro-lojas">
                <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                    <span class="small fw-semibold me-1 rotulo-grupo"><i class="bi bi-shop me-1"></i>Lojas</span>
                    @foreach($lojas as $loja)
                        <input class="btn-check js-marca" type="checkbox" name="lojas[]" value="{{ $loja->id }}"
                               id="loja-{{ $loja->id }}" autocomplete="off" @checked(in_array($loja->id, $sel))>
                        <label class="btn btn-outline-primary btn-sm rounded-pill" for="loja-{{ $loja->id }}">{{ $loja->nome }}</label>
                    @endforeach
                    <span class="text-muted small">·</span>
                    <button type="button" class="btn btn-link btn-sm p-0 js-todas">Marcar todas</button>
                    <button type="button" class="btn btn-link btn-sm p-0 js-limpar">Limpar</button>
                    <span class="small text-muted ms-1 js-contador" data-vazio="Nenhuma marcada: vale a loja atual." data-todas="(todas marcadas)"></span>
                </div>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- Totais --}}
<div class="kpi-grid mb-4">
    <div class="kpi">
        <i class="bi bi-box-arrow-in-down kpi-ico text-success"></i>
        <div class="kpi-label">Entrou no estoque</div>
        <div class="kpi-value text-success" id="kpi-entrou">{{ $qtd($totais->entrou) }}</div>
        <div class="kpi-sub">{{ number_format($totais->mov_entrada, 0, ',', '.') }} movimentação(ões) para cima</div>
    </div>
    <div class="kpi">
        <i class="bi bi-box-arrow-up kpi-ico text-danger"></i>
        <div class="kpi-label">Saiu do estoque</div>
        <div class="kpi-value text-danger" id="kpi-saiu">{{ $qtd($totais->saiu) }}</div>
        <div class="kpi-sub">{{ number_format($totais->mov_saida, 0, ',', '.') }} movimentação(ões) para baixo</div>
    </div>
    <div class="kpi">
        <i class="bi bi-plus-slash-minus kpi-ico"></i>
        <div class="kpi-label">Saldo do período (entrou − saiu)</div>
        <div class="kpi-value" id="kpi-saldo">{{ $saldoPeriodo > 0 ? '+' : '' }}{{ $qtd($saldoPeriodo) }}</div>
        <div class="kpi-sub">só das movimentações filtradas</div>
    </div>
    <div class="kpi">
        <i class="bi bi-list-check kpi-ico text-primary"></i>
        <div class="kpi-label">Movimentações</div>
        <div class="kpi-value" id="kpi-movimentos">{{ number_format($totais->movimentos, 0, ',', '.') }}</div>
        <div class="kpi-sub">{{ number_format($totais->produtos, 0, ',', '.') }} produto(s) diferente(s)</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><h6 class="mb-0 fw-semibold"><i class="bi bi-tags me-1"></i>Por tipo</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="tabela-tipos">
                    <thead class="table-light"><tr><th>Tipo</th><th class="text-end">Movimentações</th><th class="text-end">Entrou</th><th class="text-end">Saiu</th></tr></thead>
                    <tbody>
                        @forelse($porTipo as $t)
                        <tr>
                            <td>{{ $t->label }}</td>
                            <td class="text-end text-muted">{{ number_format($t->movimentos, 0, ',', '.') }}</td>
                            <td class="text-end text-success">{{ $qtd($t->entrou) }}</td>
                            <td class="text-end text-danger">{{ $qtd($t->saiu) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Sem movimentações no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">Ajuste e transferência podem ser para cima ou para baixo: o relatório olha o saldo antes e depois.</div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white"><h6 class="mb-0 fw-semibold"><i class="bi bi-shop me-1"></i>Por loja</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="tabela-lojas">
                    <thead class="table-light"><tr><th>Loja</th><th class="text-end">Movimentações</th><th class="text-end">Entrou</th><th class="text-end">Saiu</th></tr></thead>
                    <tbody>
                        @forelse($porLoja as $l)
                        <tr>
                            <td>{{ $l->loja }}</td>
                            <td class="text-end text-muted">{{ number_format($l->movimentos, 0, ',', '.') }}</td>
                            <td class="text-end text-success">{{ $qtd($l->entrou) }}</td>
                            <td class="text-end text-danger">{{ $qtd($l->saiu) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Sem movimentações no período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Abas: resumo por produto × movimento a movimento --}}
<ul class="nav nav-tabs mb-0 d-print-none">
    <li class="nav-item"><a class="nav-link {{ $ver === 'resumo' ? 'active' : '' }}" href="{{ $url(['ver' => 'resumo']) }}"><i class="bi bi-list-ol me-1"></i>Resumo por produto <span class="badge bg-light text-dark border">{{ number_format($totais->produtos, 0, ',', '.') }}</span></a></li>
    <li class="nav-item"><a class="nav-link {{ $ver === 'itens' ? 'active' : '' }}" href="{{ $url(['ver' => 'itens']) }}"><i class="bi bi-list-ul me-1"></i>Movimento a movimento <span class="badge bg-light text-dark border">{{ number_format($totais->movimentos, 0, ',', '.') }}</span></a></li>
</ul>

@if($ver === 'resumo')
<div class="card shadow-sm mb-4" style="border-top-left-radius: 0;">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0 fw-semibold">Resumo por produto</h6>
        <div class="small d-print-none">Ordenar:
            <a href="{{ $url(['ordem' => 'saiu']) }}" class="{{ $ordem === 'saiu' ? 'fw-bold' : '' }}">mais saiu</a> ·
            <a href="{{ $url(['ordem' => 'entrou']) }}" class="{{ $ordem === 'entrou' ? 'fw-bold' : '' }}">mais entrou</a> ·
            <a href="{{ $url(['ordem' => 'saldo']) }}" class="{{ $ordem === 'saldo' ? 'fw-bold' : '' }}">saldo</a> ·
            <a href="{{ $url(['ordem' => 'nome']) }}" class="{{ $ordem === 'nome' ? 'fw-bold' : '' }}">nome</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="tabela-resumo">
            <thead class="table-light">
                <tr>
                    <th>Código</th><th>Produto</th><th>Categoria</th>
                    <th class="text-end">Entrou</th><th class="text-end">Saiu</th>
                    <th class="text-end">Saldo do período</th><th class="text-end">Movimentações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resumo as $r)
                <tr>
                    <td class="text-muted small">{{ $r->codigo }}</td>
                    <td><a href="{{ $url(['ver' => 'itens', 'produto_id' => $r->id]) }}" title="Ver as movimentações deste produto">{{ $r->produto }}</a></td>
                    <td class="small text-muted">{{ $r->categoria }}</td>
                    <td class="text-end text-success">{{ $qtd($r->entrou) }}</td>
                    <td class="text-end text-danger">{{ $qtd($r->saiu) }}</td>
                    <td class="text-end fw-semibold">{{ $r->saldo > 0 ? '+' : '' }}{{ $qtd($r->saldo) }}</td>
                    <td class="text-end text-muted">{{ number_format($r->movimentos, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-5">Nenhuma movimentação com esses filtros.</td></tr>
                @endforelse
            </tbody>
            @if($resumo->isNotEmpty())
            <tfoot class="table-light">
                <tr>
                    <td colspan="3" class="fw-semibold">Total do relatório</td>
                    <td class="text-end fw-semibold">{{ $qtd($totais->entrou) }}</td>
                    <td class="text-end fw-semibold">{{ $qtd($totais->saiu) }}</td>
                    <td class="text-end fw-bold">{{ $saldoPeriodo > 0 ? '+' : '' }}{{ $qtd($saldoPeriodo) }}</td>
                    <td class="text-end">{{ number_format($totais->movimentos, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    @if($resumo->hasPages())
    <div class="card-footer bg-white d-print-none">{{ $resumo->links() }}</div>
    @endif
</div>
@else
<div class="card shadow-sm mb-4" style="border-top-left-radius: 0;">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">Movimento a movimento</h6>
        <span class="small text-muted d-print-none">100 por página · o Excel e o PDF trazem todos</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="tabela-itens">
            <thead class="table-light">
                <tr>
                    <th>Data</th><th>Loja</th><th>Produto</th><th>Tipo</th>
                    <th class="text-end">Entrou</th><th class="text-end">Saiu</th>
                    <th class="text-end">Saldo antes → depois</th><th>Usuário</th><th>Lançado por</th><th>Observações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($itens as $l)
                <tr>
                    <td class="text-nowrap small">{{ $l->data->format('d/m/Y H:i') }}</td>
                    <td class="small">{{ $l->loja }}@if($l->local && ! $l->local_padrao)<br><span class="text-muted">{{ $l->local }}</span>@endif</td>
                    <td class="col-produto">{{ $l->produto }} @if($l->codigo)<span class="text-muted small">({{ $l->codigo }})</span>@endif</td>
                    <td><span class="badge {{ $badgeTipo($l->tipo) }} rounded-pill">{{ $l->tipo_label }}</span></td>
                    <td class="text-end text-success fw-semibold">{{ $l->entrou > 0 ? $qtd($l->entrou) : '' }}</td>
                    <td class="text-end text-danger fw-semibold">{{ $l->saiu > 0 ? $qtd($l->saiu) : '' }}</td>
                    <td class="text-end text-nowrap small"><span class="text-muted">{{ $qtd($l->antes) }}</span> → <strong>{{ $qtd($l->depois) }}</strong></td>
                    <td class="small">{{ $l->usuario ?? '-' }}</td>
                    <td class="small text-nowrap">{{ $l->origem_label }}</td>
                    <td class="small text-muted obs-mov">{{ \Illuminate\Support\Str::limit((string) $l->observacoes, $imprimir ? 200 : 80) }}</td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-5">Nenhuma movimentação com esses filtros.</td></tr>
                @endforelse
            </tbody>
            @if($itens->isNotEmpty())
            <tfoot class="table-light">
                <tr>
                    <td colspan="4" class="fw-semibold">Total do relatório</td>
                    <td class="text-end fw-semibold">{{ $qtd($totais->entrou) }}</td>
                    <td class="text-end fw-semibold">{{ $qtd($totais->saiu) }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    @if($itens->hasPages())
    <div class="card-footer bg-white d-print-none">{{ $itens->links() }}</div>
    @endif
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    // Pílulas de tipo e de loja: cada fileira tem os próprios botões e contador
    document.querySelectorAll('#form-mov-estoque .grupo-pilulas[id]').forEach(function (grupo) {
        const marcas = [...grupo.querySelectorAll('.js-marca')];
        const contador = grupo.querySelector('.js-contador');
        const atualizar = () => {
            const n = marcas.filter(m => m.checked).length;
            contador.textContent = n === marcas.length ? contador.dataset.todas
                : n === 0 ? contador.dataset.vazio
                : '(' + n + ' de ' + marcas.length + ')';
        };
        marcas.forEach(m => m.addEventListener('change', atualizar));
        const todas = grupo.querySelector('.js-todas');
        if (todas) todas.addEventListener('click', () => { marcas.forEach(m => m.checked = true); atualizar(); });
        grupo.querySelector('.js-limpar').addEventListener('click', () => { marcas.forEach(m => m.checked = false); atualizar(); });
        atualizar();
    });

    // Período rápido: preenche as duas datas (o Filtrar continua sendo quem busca)
    const ini = document.getElementById('campo-data_inicio');
    const fim = document.getElementById('campo-data_fim');
    const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    document.querySelectorAll('.js-periodo').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const hoje = new Date();
            let de = new Date(hoje), ate = new Date(hoje);
            const p = botao.dataset.periodo;
            if (p === '7') de.setDate(hoje.getDate() - 6);
            else if (p === '30') de.setDate(hoje.getDate() - 29);
            else if (p === 'mes') de = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
            else if (p === 'mes-passado') { de = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1); ate = new Date(hoje.getFullYear(), hoje.getMonth(), 0); }
            ini.value = iso(de);
            fim.value = iso(ate);
        });
    });

    // Apagar o texto do autocomplete limpa o filtro (senão o id antigo fica no hidden)
    const campo = document.getElementById('produtoFiltroBusca');
    if (campo) campo.addEventListener('input', () => { if (!campo.value.trim()) document.getElementById('produtoFiltroId').value = ''; });

    @if($imprimir)
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
    @endif
})();
</script>
@endpush
