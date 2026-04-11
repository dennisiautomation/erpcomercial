@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
    {{-- Faturamento Mês --}}
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Faturamento Mês</p>
                        <h4 class="fw-bold text-success mb-0">R$ {{ number_format($faturamentoMes, 2, ',', '.') }}</h4>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-currency-dollar fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Vendas Mês --}}
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Vendas no Mês</p>
                        <h4 class="fw-bold text-primary mb-0">{{ $totalVendasMes }}</h4>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-cart-check fs-4 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Ticket Médio --}}
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Ticket Médio</p>
                        <h4 class="fw-bold text-info mb-0">R$ {{ number_format($ticketMedio, 2, ',', '.') }}</h4>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-receipt fs-4 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Inadimplência --}}
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Inadimplência</p>
                        <h4 class="fw-bold text-danger mb-0">R$ {{ number_format($inadimplencia, 2, ',', '.') }}</h4>
                    </div>
                    <div class="bg-danger bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-exclamation-triangle fs-4 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Area --}}
<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Vendas por Dia</h6>
            </div>
            <div class="card-body" style="min-height: 300px;">
                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                    <div class="text-center">
                        <i class="bi bi-bar-chart-line fs-1 mb-2 d-block"></i>
                        <p class="mb-0">Gráfico de vendas diárias</p>
                        <small>Integre com Chart.js para visualizar os dados</small>
                    </div>
                </div>
                <canvas id="chartVendasDia"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 5 Produtos</h6>
            </div>
            <div class="card-body" style="min-height: 300px;">
                @if($topProdutos->isEmpty())
                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                        <p>Nenhuma venda no período</p>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($topProdutos as $i => $item)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-primary rounded-pill me-2">{{ $i + 1 }}</span>
                                    {{ $item->produto->descricao ?? 'Produto removido' }}
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold">{{ number_format($item->total_quantidade, 0, ',', '.') }} un.</span>
                                    <br>
                                    <small class="text-muted">R$ {{ number_format($item->total_valor, 2, ',', '.') }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Últimas Vendas --}}
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Últimas Vendas</h6>
        <a href="{{ route('app.vendas.index') }}" class="btn btn-sm btn-outline-primary">Ver todas</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nº</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ultimasVendas as $venda)
                        <tr>
                            <td><strong>{{ $venda->numero }}</strong></td>
                            <td>{{ $venda->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $venda->cliente->nome_razao_social ?? 'Consumidor Final' }}</td>
                            <td>{{ $venda->vendedor->name ?? '-' }}</td>
                            <td class="text-end fw-bold">R$ {{ number_format($venda->total, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @php
                                    $statusColors = [
                                        'finalizada' => 'success',
                                        'pendente' => 'warning',
                                        'cancelada' => 'danger',
                                    ];
                                    $color = $statusColors[$venda->status->value ?? $venda->status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $color }}">{{ ucfirst($venda->status->value ?? $venda->status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nenhuma venda registrada</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
