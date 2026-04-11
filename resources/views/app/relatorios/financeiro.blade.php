@extends('layouts.app')

@section('title', 'Relatorio Financeiro')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-cash-coin me-2"></i>Relatorio Financeiro</h4>
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
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Data Inicio</label>
                <input type="date" name="data_inicio" class="form-control form-control-sm" value="{{ $dataInicio->format('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Data Fim</label>
                <input type="date" name="data_fim" class="form-control form-control-sm" value="{{ $dataFim->format('Y-m-d') }}">
            </div>
            <div class="col-md-3 d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.relatorios.financeiro') }}" class="btn btn-outline-secondary btn-sm" title="Limpar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Receitas</p>
                        <h4 class="fw-bold mb-0 text-success">R$ {{ number_format($receitas, 2, ',', '.') }}</h4>
                    </div>
                    <div class="rounded-3 bg-success bg-opacity-10 p-2">
                        <i class="bi bi-arrow-down-circle fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Despesas Totais</p>
                        <h4 class="fw-bold mb-0 text-danger">R$ {{ number_format($despesas, 2, ',', '.') }}</h4>
                    </div>
                    <div class="rounded-3 bg-danger bg-opacity-10 p-2">
                        <i class="bi bi-arrow-up-circle fs-4 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Lucro Bruto</p>
                        <h4 class="fw-bold mb-0 {{ $lucroBruto >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format($lucroBruto, 2, ',', '.') }}
                        </h4>
                    </div>
                    <div class="rounded-3 bg-info bg-opacity-10 p-2">
                        <i class="bi bi-graph-up fs-4 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100 {{ $resultado >= 0 ? 'border-success' : 'border-danger' }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Resultado Liquido</p>
                        <h4 class="fw-bold mb-0 {{ $resultado >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format($resultado, 2, ',', '.') }}
                        </h4>
                    </div>
                    <div class="rounded-3 {{ $resultado >= 0 ? 'bg-success' : 'bg-danger' }} bg-opacity-10 p-2">
                        <i class="bi bi-{{ $resultado >= 0 ? 'trophy' : 'exclamation-triangle' }} fs-4 {{ $resultado >= 0 ? 'text-success' : 'text-danger' }}"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- DRE Card --}}
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> DRE Simplificado
            </div>
            <div class="card-body">
                <table class="table mb-0">
                    <tbody>
                        <tr class="bg-success bg-opacity-10">
                            <td class="fw-bold fs-6">
                                <i class="bi bi-plus-circle text-success me-1"></i> Receitas
                            </td>
                            <td class="text-end fw-bold fs-6 text-success">R$ {{ number_format($receitas, 2, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="ps-4 text-muted">
                                <i class="bi bi-dash me-1"></i> Custos (CMV)
                            </td>
                            <td class="text-end text-danger">- R$ {{ number_format($custos, 2, ',', '.') }}</td>
                        </tr>
                        <tr class="border-top border-2">
                            <td class="fw-bold">
                                <i class="bi bi-arrow-right me-1"></i> Lucro Bruto
                            </td>
                            <td class="text-end fw-bold {{ $lucroBruto >= 0 ? 'text-success' : 'text-danger' }}">
                                R$ {{ number_format($lucroBruto, 2, ',', '.') }}
                            </td>
                        </tr>
                        @if($receitas > 0)
                        <tr>
                            <td class="ps-4 text-muted small">Margem Bruta</td>
                            <td class="text-end text-muted small">{{ number_format(($lucroBruto / $receitas) * 100, 1) }}%</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="ps-4 text-muted">
                                <i class="bi bi-dash me-1"></i> Despesas Operacionais
                            </td>
                            <td class="text-end text-danger">- R$ {{ number_format($despesasOperacionais, 2, ',', '.') }}</td>
                        </tr>
                        <tr class="border-top border-3 {{ $resultado >= 0 ? 'bg-success' : 'bg-danger' }} bg-opacity-10">
                            <td class="fw-bold fs-5">
                                <i class="bi bi-{{ $resultado >= 0 ? 'check-circle' : 'exclamation-circle' }} me-1"></i> Resultado
                            </td>
                            <td class="text-end fw-bold fs-5 {{ $resultado >= 0 ? 'text-success' : 'text-danger' }}">
                                R$ {{ number_format($resultado, 2, ',', '.') }}
                            </td>
                        </tr>
                        @if($receitas > 0)
                        <tr>
                            <td class="ps-4 text-muted small">Margem Liquida</td>
                            <td class="text-end text-muted small">{{ number_format(($resultado / $receitas) * 100, 1) }}%</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Chart --}}
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-pie-chart me-1"></i> Composicao Financeira
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div style="max-width: 280px; width: 100%;">
                    <canvas id="receitaDespesaChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Contas a Receber --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-arrow-down-circle text-success me-1"></i>Contas a Receber (Pendentes)
                </h6>
                <span class="badge bg-success bg-opacity-10 text-success">
                    R$ {{ number_format($contasReceber->sum('total'), 2, ',', '.') }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Faixa de Vencimento</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contasReceber as $faixa)
                        <tr class="{{ $faixa->faixa === 'Vencido' ? 'table-danger' : '' }}">
                            <td>
                                @if($faixa->faixa === 'Vencido')
                                    <i class="bi bi-exclamation-circle text-danger me-1"></i>
                                @endif
                                {{ $faixa->faixa }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $faixa->quantidade }}</span>
                            </td>
                            <td class="text-end fw-semibold">R$ {{ number_format($faixa->total, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Nenhuma conta pendente.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Contas a Pagar --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-arrow-up-circle text-danger me-1"></i>Contas a Pagar (Pendentes)
                </h6>
                <span class="badge bg-danger bg-opacity-10 text-danger">
                    R$ {{ number_format($contasPagar->sum('total'), 2, ',', '.') }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Faixa de Vencimento</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contasPagar as $faixa)
                        <tr class="{{ $faixa->faixa === 'Vencido' ? 'table-danger' : '' }}">
                            <td>
                                @if($faixa->faixa === 'Vencido')
                                    <i class="bi bi-exclamation-circle text-danger me-1"></i>
                                @endif
                                {{ $faixa->faixa }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $faixa->quantidade }}</span>
                            </td>
                            <td class="text-end fw-semibold">R$ {{ number_format($faixa->total, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Nenhuma conta pendente.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('receitaDespesaChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Receitas', 'Custos (CMV)', 'Despesas Operacionais'],
                datasets: [{
                    data: [{{ $receitas }}, {{ $custos }}, {{ $despesasOperacionais }}],
                    backgroundColor: ['rgba(25,135,84,0.8)', 'rgba(220,53,69,0.8)', 'rgba(255,193,7,0.8)'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 16, usePointStyle: true, pointStyle: 'circle' }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': R$ ' + context.parsed.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
