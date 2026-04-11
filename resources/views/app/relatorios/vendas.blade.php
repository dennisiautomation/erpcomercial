@extends('layouts.app')

@section('title', 'Relatorio de Vendas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-bar-chart-line me-2"></i>Relatorio de Vendas</h4>
        <p class="text-muted mb-0 small">Periodo: {{ $dataInicio->format('d/m/Y') }} a {{ $dataFim->format('d/m/Y') }}</p>
    </div>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Data Inicio</label>
                <input type="date" name="data_inicio" class="form-control form-control-sm" value="{{ $dataInicio->format('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Data Fim</label>
                <input type="date" name="data_fim" class="form-control form-control-sm" value="{{ $dataFim->format('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Vendedor</label>
                <input type="text" name="vendedor_id" class="form-control form-control-sm" value="{{ request('vendedor_id') }}" placeholder="ID do vendedor">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Cliente</label>
                <input type="text" name="cliente_id" class="form-control form-control-sm" value="{{ request('cliente_id') }}" placeholder="ID do cliente">
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

{{-- Stats Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total de Vendas</p>
                        <h3 class="fw-bold mb-0">{{ $totalVendas }}</h3>
                    </div>
                    <div class="rounded-3 bg-primary bg-opacity-10 p-2">
                        <i class="bi bi-cart-check fs-4 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Faturamento</p>
                        <h3 class="fw-bold mb-0 text-success">R$ {{ number_format($faturamento, 2, ',', '.') }}</h3>
                    </div>
                    <div class="rounded-3 bg-success bg-opacity-10 p-2">
                        <i class="bi bi-currency-dollar fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Ticket Medio</p>
                        <h3 class="fw-bold mb-0 text-info">R$ {{ number_format($ticketMedio, 2, ',', '.') }}</h3>
                    </div>
                    <div class="rounded-3 bg-info bg-opacity-10 p-2">
                        <i class="bi bi-receipt fs-4 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Vendas Table --}}
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-1"></i>Vendas no Periodo</h6>
        <span class="badge bg-secondary">{{ $vendas->count() }} registro(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data</th>
                    <th>Numero</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Forma Pgto</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendas as $venda)
                <tr>
                    <td class="text-nowrap">
                        <small class="text-muted"><i class="bi bi-calendar3 me-1"></i></small>
                        {{ $venda->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td><strong>#{{ $venda->numero ?? $venda->id }}</strong></td>
                    <td>{{ $venda->cliente->nome_razao_social ?? 'Consumidor' }}</td>
                    <td>{{ $venda->vendedor->name ?? '-' }}</td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            {{ ucfirst(str_replace('_', ' ', $venda->forma_pagamento ?? '-')) }}
                        </span>
                    </td>
                    <td class="text-end fw-bold">R$ {{ number_format($venda->total, 2, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                        Nenhuma venda no periodo selecionado.
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($vendas->count() > 0)
            <tfoot class="table-light">
                <tr>
                    <td colspan="5" class="text-end fw-semibold">Total do Periodo:</td>
                    <td class="text-end fw-bold text-success">R$ {{ number_format($faturamento, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<div class="row g-4">
    {{-- Top 10 Produtos --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-trophy text-warning me-1"></i>Top 10 Produtos</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40">#</th>
                            <th>Produto</th>
                            <th class="text-end">Qtd</th>
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
                            <td>{{ $item->produto->descricao ?? '-' }}</td>
                            <td class="text-end text-muted">{{ number_format($item->qtd_vendida, 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($item->faturamento, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Sem dados no periodo.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Top 10 Clientes --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-people text-primary me-1"></i>Top 10 Clientes</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
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
                            <td class="text-end fw-semibold">R$ {{ number_format($item->faturamento, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Sem dados no periodo.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
