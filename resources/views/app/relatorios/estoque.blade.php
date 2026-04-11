@extends('layouts.app')

@section('title', 'Relatorio de Estoque')

@section('content')
@php
    $totalProdutos = $produtos->count();
    $estoqueBaixo = $produtos->where('estoque_status', 'baixo')->count();
    $estoqueCritico = $produtos->where('estoque_status', 'critico')->count();
    $estoqueOk = $produtos->where('estoque_status', 'ok')->count();
    $abaixoMinimo = $produtos->whereIn('estoque_status', ['baixo', 'critico']);
    $valorEstoque = $produtos->sum(fn($p) => $p->estoque_atual * ($p->preco_custo ?? 0));
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-box-seam me-2"></i>Relatorio de Estoque</h4>
        <p class="text-muted mb-0 small">Posicao atualizada em {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i> Imprimir
    </button>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total de Produtos</p>
                        <h3 class="fw-bold mb-0">{{ $totalProdutos }}</h3>
                    </div>
                    <div class="rounded-3 bg-primary bg-opacity-10 p-2">
                        <i class="bi bi-box-seam fs-4 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Estoque OK</p>
                        <h3 class="fw-bold mb-0 text-success">{{ $estoqueOk }}</h3>
                    </div>
                    <div class="rounded-3 bg-success bg-opacity-10 p-2">
                        <i class="bi bi-check-circle fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Estoque Baixo</p>
                        <h3 class="fw-bold mb-0 text-warning">{{ $estoqueBaixo }}</h3>
                    </div>
                    <div class="rounded-3 bg-warning bg-opacity-10 p-2">
                        <i class="bi bi-exclamation-triangle fs-4 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Estoque Critico</p>
                        <h3 class="fw-bold mb-0 text-danger">{{ $estoqueCritico }}</h3>
                    </div>
                    <div class="rounded-3 bg-danger bg-opacity-10 p-2">
                        <i class="bi bi-x-circle fs-4 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Alerts: Products below minimum --}}
@if($abaixoMinimo->count() > 0)
<div class="card shadow-sm mb-4 border-danger border-opacity-50">
    <div class="card-header bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold text-danger">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Produtos Abaixo do Minimo ({{ $abaixoMinimo->count() }})
        </h6>
        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAlertas">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div class="collapse show" id="collapseAlertas">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th class="text-end">Estoque Atual</th>
                        <th class="text-end">Minimo</th>
                        <th class="text-end">Falta</th>
                        <th class="text-center">Situacao</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($abaixoMinimo->sortBy('estoque_atual') as $produto)
                    <tr>
                        <td class="fw-semibold">{{ $produto->descricao }}</td>
                        <td class="text-muted">{{ $produto->categoria->nome ?? '-' }}</td>
                        <td class="text-end fw-bold text-danger">{{ number_format($produto->estoque_atual, 2, ',', '.') }}</td>
                        <td class="text-end">{{ number_format($produto->estoque_minimo, 2, ',', '.') }}</td>
                        <td class="text-end text-danger fw-bold">
                            {{ number_format($produto->estoque_minimo - $produto->estoque_atual, 2, ',', '.') }}
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $produto->estoque_status === 'critico' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                {{ $produto->estoque_status === 'critico' ? 'Critico' : 'Baixo' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Tabs: Posicao de Estoque & Curva ABC --}}
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#tabPosicao" type="button" role="tab">
                    <i class="bi bi-table me-1"></i> Posicao de Estoque
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tabCurvaABC" type="button" role="tab">
                    <i class="bi bi-graph-up me-1"></i> Curva ABC
                </button>
            </li>
        </ul>
    </div>
    <div class="tab-content">
        {{-- Tab: Posicao de Estoque --}}
        <div class="tab-pane fade show active" id="tabPosicao" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th class="text-end">Preco Custo</th>
                            <th class="text-end">Estoque Atual</th>
                            <th class="text-end">Minimo</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Curva</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produtos as $produto)
                        @php
                            $rowClass = match($produto->estoque_status) {
                                'critico' => 'table-danger',
                                'baixo' => 'table-warning',
                                default => '',
                            };
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="fw-semibold">{{ $produto->descricao }}</td>
                            <td class="text-muted">{{ $produto->categoria->nome ?? '-' }}</td>
                            <td class="text-end">R$ {{ number_format($produto->preco_custo ?? 0, 2, ',', '.') }}</td>
                            <td class="text-end fw-bold">{{ number_format($produto->estoque_atual, 2, ',', '.') }}</td>
                            <td class="text-end">{{ $produto->estoque_minimo ? number_format($produto->estoque_minimo, 2, ',', '.') : '-' }}</td>
                            <td class="text-center">
                                @php
                                    $statusBadge = match($produto->estoque_status) {
                                        'critico' => 'bg-danger',
                                        'baixo' => 'bg-warning text-dark',
                                        default => 'bg-success',
                                    };
                                    $statusLabel = match($produto->estoque_status) {
                                        'critico' => 'Critico',
                                        'baixo' => 'Baixo',
                                        default => 'OK',
                                    };
                                @endphp
                                <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="text-center">
                                @php
                                    $curva = $curvaABC[$produto->id] ?? '-';
                                    $curvaBadge = match($curva) {
                                        'A' => 'bg-success',
                                        'B' => 'bg-primary',
                                        'C' => 'bg-secondary',
                                        default => 'bg-light text-dark border',
                                    };
                                @endphp
                                <span class="badge {{ $curvaBadge }}">{{ $curva }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Nenhum produto cadastrado.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab: Curva ABC --}}
        <div class="tab-pane fade" id="tabCurvaABC" role="tabpanel">
            <div class="card-body">
                <div class="row g-4">
                    @php
                        $curvaA = collect($curvaABC)->where(fn($v) => $v === 'A')->count();
                        $curvaB = collect($curvaABC)->where(fn($v) => $v === 'B')->count();
                        $curvaC = collect($curvaABC)->where(fn($v) => $v === 'C')->count();
                    @endphp
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 text-center bg-success bg-opacity-10">
                            <div class="fw-bold fs-2 text-success">{{ $curvaA }}</div>
                            <div class="fw-semibold">Curva A</div>
                            <small class="text-muted">80% do faturamento</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 text-center bg-primary bg-opacity-10">
                            <div class="fw-bold fs-2 text-primary">{{ $curvaB }}</div>
                            <div class="fw-semibold">Curva B</div>
                            <small class="text-muted">15% do faturamento</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 text-center bg-secondary bg-opacity-10">
                            <div class="fw-bold fs-2 text-secondary">{{ $curvaC }}</div>
                            <div class="fw-semibold">Curva C</div>
                            <small class="text-muted">5% do faturamento</small>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <canvas id="curvaABCChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('curvaABCChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Curva A', 'Curva B', 'Curva C'],
                datasets: [{
                    label: 'Quantidade de Produtos',
                    data: [{{ $curvaA ?? 0 }}, {{ $curvaB ?? 0 }}, {{ $curvaC ?? 0 }}],
                    backgroundColor: ['rgba(25,135,84,0.7)', 'rgba(13,110,253,0.7)', 'rgba(108,117,125,0.7)'],
                    borderColor: ['#198754', '#0d6efd', '#6c757d'],
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
});
</script>
@endpush
