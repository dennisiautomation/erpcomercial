@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .stat-card-gradient {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card-gradient:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.12) !important;
    }
    .stat-card-gradient .card-body {
        position: relative;
        z-index: 1;
    }
    .stat-card-gradient .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .stat-card-gradient .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-card-gradient .stat-label {
        font-size: 0.8rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.7;
    }
    .stat-card-gradient .stat-trend {
        font-size: 0.78rem;
        font-weight: 600;
    }
    .gradient-success {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: #fff;
    }
    .gradient-success .stat-icon { background: rgba(255,255,255,0.2); color: #fff; }
    .gradient-primary {
        background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        color: #fff;
    }
    .gradient-primary .stat-icon { background: rgba(255,255,255,0.2); color: #fff; }
    .gradient-info {
        background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
        color: #fff;
    }
    .gradient-info .stat-icon { background: rgba(255,255,255,0.2); color: #fff; }
    .gradient-warning {
        background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        color: #fff;
    }
    .gradient-warning .stat-icon { background: rgba(255,255,255,0.2); color: #fff; }
    .gradient-danger {
        background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        color: #fff;
    }
    .gradient-danger .stat-icon { background: rgba(255,255,255,0.2); color: #fff; }
    .gradient-purple {
        background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
        color: #fff;
    }
    .gradient-purple .stat-icon { background: rgba(255,255,255,0.2); color: #fff; }

    .top-produto-item {
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s;
    }
    .top-produto-item:last-child { border-bottom: none; }
    .top-produto-item:hover { background: #f8fafc; }
    .top-produto-rank {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
    }
    .rank-1 { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #fff; }
    .rank-2 { background: linear-gradient(135deg, #94a3b8, #cbd5e1); color: #475569; }
    .rank-3 { background: linear-gradient(135deg, #c2703e, #d4956b); color: #fff; }
    .rank-default { background: #f1f5f9; color: #64748b; }

    .venda-row { transition: background 0.15s; }
    .venda-row:hover { background: #f8fafc; }

    .welcome-banner {
        background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);
        border-radius: 16px;
        color: #fff;
        padding: 24px 32px;
        position: relative;
        overflow: hidden;
    }
    .welcome-banner::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
        border-radius: 50%;
    }
</style>
@endpush

@section('content')
{{-- Welcome Banner --}}
<div class="welcome-banner mb-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1">Bem-vindo, {{ explode(' ', auth()->user()->name)[0] }}!</h4>
            <p class="mb-0 opacity-75">{{ now()->translatedFormat('l, d \\d\\e F \\d\\e Y') }}</p>
        </div>
        <div class="d-none d-md-block text-end">
            <span class="badge bg-light text-dark px-3 py-2 fs-6">
                <i class="bi bi-calendar3 me-1"></i>
                {{ now()->translatedFormat('F Y') }}
            </span>
        </div>
    </div>
</div>

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card-gradient gradient-success shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    @if($variacaoFaturamento != 0)
                        <span class="stat-trend">
                            <i class="bi bi-arrow-{{ $variacaoFaturamento > 0 ? 'up' : 'down' }}"></i>
                            {{ abs($variacaoFaturamento) }}%
                        </span>
                    @endif
                </div>
                <div class="stat-value mb-1">R$ {{ number_format($faturamentoMes, 2, ',', '.') }}</div>
                <div class="stat-label">Faturamento do Mes</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card-gradient gradient-primary shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    @if($variacaoVendas != 0)
                        <span class="stat-trend">
                            <i class="bi bi-arrow-{{ $variacaoVendas > 0 ? 'up' : 'down' }}"></i>
                            {{ abs($variacaoVendas) }}%
                        </span>
                    @endif
                </div>
                <div class="stat-value mb-1">{{ $totalVendasMes }}</div>
                <div class="stat-label">Vendas no Mes</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card-gradient gradient-info shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                </div>
                <div class="stat-value mb-1">R$ {{ number_format($ticketMedio, 2, ',', '.') }}</div>
                <div class="stat-label">Ticket Medio</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card-gradient gradient-purple shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <div class="stat-value mb-1">{{ $totalClientes }}</div>
                <div class="stat-label">Clientes Ativos</div>
            </div>
        </div>
    </div>
</div>

{{-- Alert Cards --}}
@if($inadimplencia > 0 || $contasPagarVencidas > 0)
<div class="row g-3 mb-4">
    @if($inadimplencia > 0)
    <div class="col-md-6">
        <div class="card border-danger border-start border-4 shadow-sm">
            <div class="card-body d-flex align-items-center py-3">
                <div class="bg-danger bg-opacity-10 rounded-3 p-3 me-3">
                    <i class="bi bi-exclamation-triangle fs-4 text-danger"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-danger mb-0">R$ {{ number_format($inadimplencia, 2, ',', '.') }}</h6>
                    <small class="text-muted">Contas a receber vencidas</small>
                </div>
                <a href="{{ route('app.contas-receber.index') }}" class="btn btn-sm btn-outline-danger ms-auto">
                    Ver detalhes <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
    @endif
    @if($contasPagarVencidas > 0)
    <div class="col-md-6">
        <div class="card border-warning border-start border-4 shadow-sm">
            <div class="card-body d-flex align-items-center py-3">
                <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                    <i class="bi bi-clock-history fs-4 text-warning"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-warning mb-0">R$ {{ number_format($contasPagarVencidas, 2, ',', '.') }}</h6>
                    <small class="text-muted">Contas a pagar vencidas</small>
                </div>
                <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-sm btn-outline-warning ms-auto">
                    Ver detalhes <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
    @endif
</div>
@endif

{{-- Charts + Top Produtos --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100" style="border-radius: 16px;">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0" style="border-radius: 16px 16px 0 0;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-1">Vendas por Dia</h6>
                        <small class="text-muted">Faturamento diario do mes atual</small>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                        <i class="bi bi-graph-up me-1"></i> {{ now()->translatedFormat('F') }}
                    </span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <canvas id="chartVendasDia" height="280"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100" style="border-radius: 16px;">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0" style="border-radius: 16px 16px 0 0;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-1">Top 5 Produtos</h6>
                        <small class="text-muted">Mais vendidos no mes</small>
                    </div>
                    <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2">
                        <i class="bi bi-trophy me-1"></i> Ranking
                    </span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                @if($topProdutos->isEmpty())
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted py-5">
                        <i class="bi bi-box-seam fs-1 mb-3 opacity-50"></i>
                        <p class="mb-0">Nenhuma venda registrada no periodo</p>
                    </div>
                @else
                    @foreach($topProdutos as $i => $item)
                        <div class="top-produto-item d-flex align-items-center px-2">
                            <div class="top-produto-rank {{ $i < 3 ? 'rank-' . ($i + 1) : 'rank-default' }} me-3">
                                {{ $i + 1 }}
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $item->produto->descricao ?? 'Produto removido' }}</div>
                                <small class="text-muted">{{ number_format($item->total_quantidade, 0, ',', '.') }} unidades vendidas</small>
                            </div>
                            <div class="text-end ms-3">
                                <div class="fw-bold text-success">R$ {{ number_format($item->total_valor, 2, ',', '.') }}</div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Ultimas Vendas --}}
<div class="card shadow-sm border-0" style="border-radius: 16px;">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-3 d-flex justify-content-between align-items-center" style="border-radius: 16px 16px 0 0;">
        <div>
            <h6 class="fw-bold mb-1">Ultimas Vendas</h6>
            <small class="text-muted">Movimentacoes mais recentes</small>
        </div>
        <a href="{{ route('app.vendas.index') }}" class="btn btn-sm btn-primary rounded-pill px-3">
            Ver todas <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="border-bottom" style="background: #f8fafc;">
                        <th class="ps-4 py-3 fw-semibold text-muted small text-uppercase">N.</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Data</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Cliente</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Vendedor</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase text-end">Total</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase text-center pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ultimasVendas as $venda)
                        <tr class="venda-row">
                            <td class="ps-4">
                                <span class="fw-bold text-primary">#{{ $venda->id }}</span>
                            </td>
                            <td>
                                <div>{{ $venda->created_at->format('d/m/Y') }}</div>
                                <small class="text-muted">{{ $venda->created_at->format('H:i') }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $venda->cliente->nome_razao_social ?? 'Consumidor Final' }}</div>
                            </td>
                            <td class="text-muted">{{ $venda->vendedor->name ?? '-' }}</td>
                            <td class="text-end">
                                <span class="fw-bold">R$ {{ number_format($venda->total, 2, ',', '.') }}</span>
                            </td>
                            <td class="text-center pe-4">
                                @php
                                    $sv = $venda->status->value ?? $venda->status;
                                    $statusMap = [
                                        'finalizada' => ['bg' => 'success', 'icon' => 'check-circle'],
                                        'pendente'   => ['bg' => 'warning', 'icon' => 'clock'],
                                        'cancelada'  => ['bg' => 'danger', 'icon' => 'x-circle'],
                                    ];
                                    $s = $statusMap[$sv] ?? ['bg' => 'secondary', 'icon' => 'question-circle'];
                                @endphp
                                <span class="badge bg-{{ $s['bg'] }} bg-opacity-10 text-{{ $s['bg'] }} px-3 py-2 rounded-pill">
                                    <i class="bi bi-{{ $s['icon'] }} me-1"></i>{{ ucfirst($sv) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                <p class="mb-0">Nenhuma venda registrada</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('chartVendasDia');
    if (!ctx) return;

    const dados = @json($vendasPorDia);
    const labels = dados.map(d => {
        const parts = d.dia.split('-');
        return parts[2] + '/' + parts[1];
    });
    const valores = dados.map(d => parseFloat(d.total_dia));
    const quantidades = dados.map(d => parseInt(d.qtd));

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Faturamento (R$)',
                data: valores,
                backgroundColor: 'rgba(37, 99, 235, 0.15)',
                borderColor: 'rgba(37, 99, 235, 0.8)',
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
                yAxisID: 'y',
            }, {
                label: 'Qtd. Vendas',
                data: quantidades,
                type: 'line',
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                pointBackgroundColor: '#10b981',
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true,
                yAxisID: 'y1',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, padding: 20, font: { size: 12 } }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { size: 13 },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(ctx) {
                            if (ctx.datasetIndex === 0) {
                                return 'R$ ' + ctx.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                            }
                            return ctx.parsed.y + ' vendas';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#94a3b8' }
                },
                y: {
                    position: 'left',
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 11 },
                        color: '#94a3b8',
                        callback: function(v) { return 'R$ ' + v.toLocaleString('pt-BR'); }
                    }
                },
                y1: {
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: {
                        font: { size: 11 },
                        color: '#94a3b8',
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>
@endpush
