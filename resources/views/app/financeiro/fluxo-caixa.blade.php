@extends('layouts.app')

@section('title', 'Fluxo de Caixa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-graph-up me-2"></i>Fluxo de Caixa</h4>
</div>

{{-- Date Filter --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Data Inicio</label>
                <input type="date" name="data_inicio" class="form-control" value="{{ $dataInicio->format('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="{{ $dataFim->format('Y-m-d') }}">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.financeiro.fluxo-caixa') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Entradas</div>
                        <div class="fs-4 fw-bold text-success">R$ {{ number_format($totalEntradas, 2, ',', '.') }}</div>
                    </div>
                    <i class="bi bi-arrow-up-circle fs-1 text-success opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-danger border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Saidas</div>
                        <div class="fs-4 fw-bold text-danger">R$ {{ number_format($totalSaidas, 2, ',', '.') }}</div>
                    </div>
                    <i class="bi bi-arrow-down-circle fs-1 text-danger opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Saldo</div>
                        <div class="fs-4 fw-bold {{ $saldoFinal >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format($saldoFinal, 2, ',', '.') }}
                        </div>
                    </div>
                    <i class="bi bi-wallet2 fs-1 text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Chart --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-bar-chart me-1"></i>Entradas vs Saidas por Dia</h6>
    </div>
    <div class="card-body">
        <canvas id="fluxoCaixaChart" height="80"></canvas>
    </div>
</div>

{{-- Daily Table --}}
<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-table me-1"></i>Detalhamento Diario</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data</th>
                    <th>Descricao</th>
                    <th>Categoria</th>
                    <th class="text-end text-success">Entrada (+)</th>
                    <th class="text-end text-danger">Saida (-)</th>
                    <th class="text-end">Saldo Acumulado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($fluxoDiario as $dia)
                    @if(count($dia['itens']) > 0)
                        @foreach($dia['itens'] as $index => $item)
                        <tr>
                            @if($index === 0)
                            <td rowspan="{{ count($dia['itens']) }}" class="align-middle fw-bold">
                                {{ \Carbon\Carbon::parse($dia['data'])->format('d/m/Y') }}
                            </td>
                            @endif
                            <td>{{ $item['descricao'] }}</td>
                            <td>{{ $item['categoria'] }}</td>
                            <td class="text-end">
                                @if($item['tipo'] === 'entrada')
                                    <span class="text-success fw-bold">R$ {{ number_format($item['valor'], 2, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($item['tipo'] === 'saida')
                                    <span class="text-danger fw-bold">R$ {{ number_format($item['valor'], 2, ',', '.') }}</span>
                                @endif
                            </td>
                            @if($index === 0)
                            <td rowspan="{{ count($dia['itens']) }}" class="text-end align-middle fw-bold {{ $dia['saldo'] >= 0 ? 'text-success' : 'text-danger' }}">
                                R$ {{ number_format($dia['saldo'], 2, ',', '.') }}
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    @elseif($dia['entradas'] > 0 || $dia['saidas'] > 0)
                    <tr>
                        <td class="fw-bold">{{ \Carbon\Carbon::parse($dia['data'])->format('d/m/Y') }}</td>
                        <td class="text-muted">-</td>
                        <td class="text-muted">-</td>
                        <td class="text-end">
                            @if($dia['entradas'] > 0)
                                <span class="text-success fw-bold">R$ {{ number_format($dia['entradas'], 2, ',', '.') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($dia['saidas'] > 0)
                                <span class="text-danger fw-bold">R$ {{ number_format($dia['saidas'], 2, ',', '.') }}</span>
                            @endif
                        </td>
                        <td class="text-end fw-bold {{ $dia['saldo'] >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format($dia['saldo'], 2, ',', '.') }}
                        </td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td colspan="3">TOTAL</td>
                    <td class="text-end text-success">R$ {{ number_format($totalEntradas, 2, ',', '.') }}</td>
                    <td class="text-end text-danger">R$ {{ number_format($totalSaidas, 2, ',', '.') }}</td>
                    <td class="text-end {{ $saldoFinal >= 0 ? 'text-success' : 'text-danger' }}">
                        R$ {{ number_format($saldoFinal, 2, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('fluxoCaixaChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json(array_map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'), $chartLabels)),
            datasets: [
                {
                    label: 'Entradas',
                    data: @json(array_values($chartEntradas)),
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Saidas',
                    data: @json(array_values($chartSaidas)),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.3,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                        }
                    }
                }
            }
        }
    });
</script>
@endpush
