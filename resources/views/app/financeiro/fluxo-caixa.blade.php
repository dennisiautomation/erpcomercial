@extends('layouts.app')

@section('title', 'Fluxo de Caixa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-graph-up me-2"></i>Fluxo de Caixa</h4>
        <p class="text-muted mb-0 small">Analise as entradas e saidas do periodo</p>
    </div>
</div>

{{-- Date Filter --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-muted">Data Inicio</label>
                <input type="date" name="data_inicio" class="form-control" value="{{ $dataInicio->format('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-muted">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="{{ $dataFim->format('Y-m-d') }}">
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
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
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-arrow-up-circle fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Entradas Realizadas</div>
                        <div class="fs-4 fw-bold text-success">R$ {{ number_format($totalEntradas, 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-danger bg-opacity-10 p-3 me-3">
                        <i class="bi bi-arrow-down-circle fs-4 text-danger"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Saidas Realizadas</div>
                        <div class="fs-4 fw-bold text-danger">R$ {{ number_format($totalSaidas, 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 {{ $saldoFinal >= 0 ? 'bg-primary' : 'bg-danger' }} bg-opacity-10 p-3 me-3">
                        <i class="bi bi-wallet2 fs-4 {{ $saldoFinal >= 0 ? 'text-primary' : 'text-danger' }}"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Saldo do Periodo</div>
                        <div class="fs-4 fw-bold {{ $saldoFinal >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format($saldoFinal, 2, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-info bg-opacity-10 p-3 me-3">
                        <i class="bi bi-calendar-check fs-4 text-info"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Previsto (Pendente)</div>
                        <div class="small">
                            <span class="text-success fw-semibold">+R$ {{ number_format($previstaReceber, 2, ',', '.') }}</span>
                            <span class="mx-1">/</span>
                            <span class="text-danger fw-semibold">-R$ {{ number_format($previstaPagar, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Chart --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up me-1"></i> Fluxo Diario</h6>
            <div class="btn-group btn-group-sm" role="group" id="chart-toggle">
                <button type="button" class="btn btn-outline-primary active" data-mode="bars">Barras</button>
                <button type="button" class="btn btn-outline-primary" data-mode="line">Linha</button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <canvas id="fluxoCaixaChart" height="80"></canvas>
    </div>
</div>

{{-- Daily Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-bold"><i class="bi bi-table me-1"></i> Detalhamento Diario</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="bg-light">
                    <th class="ps-3">Data</th>
                    <th>Descricao</th>
                    <th>Categoria</th>
                    <th class="text-end">Entrada (+)</th>
                    <th class="text-end">Saida (-)</th>
                    <th class="text-end pe-3">Saldo Acumulado</th>
                </tr>
            </thead>
            <tbody>
                @php $hasData = false; @endphp
                @foreach($fluxoDiario as $dia)
                    @if(count($dia['itens']) > 0)
                        @php $hasData = true; @endphp
                        @foreach($dia['itens'] as $index => $item)
                        <tr>
                            @if($index === 0)
                            <td rowspan="{{ count($dia['itens']) }}" class="align-middle ps-3">
                                <div class="fw-bold">{{ \Carbon\Carbon::parse($dia['data'])->format('d/m') }}</div>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($dia['data'])->translatedFormat('D') }}</small>
                            </td>
                            @endif
                            <td>{{ $item['descricao'] }}</td>
                            <td>
                                <span class="badge {{ $item['tipo'] === 'entrada' ? 'bg-success' : 'bg-danger' }} bg-opacity-10 {{ $item['tipo'] === 'entrada' ? 'text-success' : 'text-danger' }} rounded-pill">
                                    {{ $item['categoria'] }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if($item['tipo'] === 'entrada')
                                    <span class="text-success fw-bold">
                                        <i class="bi bi-arrow-up-short"></i>R$ {{ number_format($item['valor'], 2, ',', '.') }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($item['tipo'] === 'saida')
                                    <span class="text-danger fw-bold">
                                        <i class="bi bi-arrow-down-short"></i>R$ {{ number_format($item['valor'], 2, ',', '.') }}
                                    </span>
                                @endif
                            </td>
                            @if($index === 0)
                            <td rowspan="{{ count($dia['itens']) }}" class="text-end align-middle pe-3">
                                <span class="fw-bold {{ $dia['saldo'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    R$ {{ number_format($dia['saldo'], 2, ',', '.') }}
                                </span>
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    @endif
                @endforeach
                @if(!$hasData)
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-graph-up fs-1 d-block mb-2 opacity-25"></i>
                            Nenhuma movimentacao financeira no periodo.
                        </div>
                    </td>
                </tr>
                @endif
            </tbody>
            @if($hasData)
            <tfoot>
                <tr class="bg-light fw-bold">
                    <td class="ps-3" colspan="3">TOTAL DO PERIODO</td>
                    <td class="text-end text-success">
                        <i class="bi bi-arrow-up-short"></i>R$ {{ number_format($totalEntradas, 2, ',', '.') }}
                    </td>
                    <td class="text-end text-danger">
                        <i class="bi bi-arrow-down-short"></i>R$ {{ number_format($totalSaidas, 2, ',', '.') }}
                    </td>
                    <td class="text-end pe-3 {{ $saldoFinal >= 0 ? 'text-success' : 'text-danger' }}">
                        R$ {{ number_format($saldoFinal, 2, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const labels = @json(array_map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'), $chartLabels));
    const entradasData = @json(array_values($chartEntradas));
    const saidasData = @json(array_values($chartSaidas));
    const saldoData = @json(array_values($chartSaldo));

    const ctx = document.getElementById('fluxoCaixaChart').getContext('2d');

    let chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Entradas',
                    data: entradasData,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: '#198754',
                    borderWidth: 1,
                    borderRadius: 4,
                    order: 2,
                },
                {
                    label: 'Saidas',
                    data: saidasData,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: '#dc3545',
                    borderWidth: 1,
                    borderRadius: 4,
                    order: 2,
                },
                {
                    label: 'Saldo Acumulado',
                    data: saldoData,
                    type: 'line',
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.05)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#0d6efd',
                    fill: true,
                    tension: 0.3,
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { usePointStyle: true, padding: 20 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 0});
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // Chart toggle
    document.getElementById('chart-toggle').addEventListener('click', function(e) {
        const btn = e.target.closest('button');
        if (!btn) return;

        this.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const mode = btn.dataset.mode;
        if (mode === 'line') {
            chart.config.type = 'line';
            chart.data.datasets[0].type = 'line';
            chart.data.datasets[0].fill = true;
            chart.data.datasets[0].backgroundColor = 'rgba(25, 135, 84, 0.1)';
            chart.data.datasets[0].tension = 0.3;
            chart.data.datasets[0].pointRadius = 3;
            chart.data.datasets[0].pointBackgroundColor = '#198754';
            chart.data.datasets[1].type = 'line';
            chart.data.datasets[1].fill = true;
            chart.data.datasets[1].backgroundColor = 'rgba(220, 53, 69, 0.1)';
            chart.data.datasets[1].tension = 0.3;
            chart.data.datasets[1].pointRadius = 3;
            chart.data.datasets[1].pointBackgroundColor = '#dc3545';
        } else {
            chart.data.datasets[0].type = 'bar';
            chart.data.datasets[0].fill = undefined;
            chart.data.datasets[0].backgroundColor = 'rgba(25, 135, 84, 0.7)';
            chart.data.datasets[0].tension = undefined;
            chart.data.datasets[0].pointRadius = undefined;
            chart.data.datasets[0].pointBackgroundColor = undefined;
            chart.data.datasets[1].type = 'bar';
            chart.data.datasets[1].fill = undefined;
            chart.data.datasets[1].backgroundColor = 'rgba(220, 53, 69, 0.7)';
            chart.data.datasets[1].tension = undefined;
            chart.data.datasets[1].pointRadius = undefined;
            chart.data.datasets[1].pointBackgroundColor = undefined;
        }
        chart.update();
    });
</script>
@endpush
