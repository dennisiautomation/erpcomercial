@extends('layouts.app')

@section('title', 'Relatorio de Vendas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Relatorio de Vendas</h4>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Data Inicio</label>
                <input type="date" name="data_inicio" class="form-control" value="{{ $dataInicio->format('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="{{ $dataFim->format('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Vendedor</label>
                <input type="text" name="vendedor_id" class="form-control" value="{{ request('vendedor_id') }}" placeholder="ID do vendedor">
            </div>
            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <input type="text" name="cliente_id" class="form-control" value="{{ request('cliente_id') }}" placeholder="ID do cliente">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.relatorios.vendas') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Stats Cards --}}
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Vendas</div>
                        <div class="fs-4 fw-bold">{{ $totalVendas }}</div>
                    </div>
                    <i class="bi bi-cart-check fs-1 text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Faturamento</div>
                        <div class="fs-4 fw-bold text-success">R$ {{ number_format($faturamento, 2, ',', '.') }}</div>
                    </div>
                    <i class="bi bi-currency-dollar fs-1 text-success opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Ticket Medio</div>
                        <div class="fs-4 fw-bold text-info">R$ {{ number_format($ticketMedio, 2, ',', '.') }}</div>
                    </div>
                    <i class="bi bi-receipt fs-1 text-info opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Vendas Table --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-list-ul me-1"></i>Vendas no Periodo</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
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
                    <td>{{ $venda->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $venda->numero ?? $venda->id }}</td>
                    <td>{{ $venda->cliente->nome_razao_social ?? 'Consumidor' }}</td>
                    <td>{{ $venda->vendedor->name ?? '-' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $venda->forma_pagamento ?? '-')) }}</td>
                    <td class="text-end fw-bold">R$ {{ number_format($venda->total, 2, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhuma venda no periodo.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row">
    {{-- Top 10 Produtos --}}
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-trophy me-1"></i>Top 10 Produtos</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Produto</th>
                            <th class="text-end">Qtd Vendida</th>
                            <th class="text-end">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProdutos as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->produto->descricao ?? '-' }}</td>
                            <td class="text-end">{{ number_format($item->qtd_vendida, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold">R$ {{ number_format($item->faturamento, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">Sem dados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Top 10 Clientes --}}
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-people me-1"></i>Top 10 Clientes</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th class="text-end">Vendas</th>
                            <th class="text-end">Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topClientes as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->cliente->nome_razao_social ?? '-' }}</td>
                            <td class="text-end">{{ $item->total_vendas }}</td>
                            <td class="text-end fw-bold">R$ {{ number_format($item->faturamento, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">Sem dados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
