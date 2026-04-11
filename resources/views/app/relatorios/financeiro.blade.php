@extends('layouts.app')

@section('title', 'Relatorio Financeiro')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Relatorio Financeiro</h4>
</div>

{{-- Filters --}}
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
                <a href="{{ route('app.relatorios.financeiro') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- DRE Card --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-1"></i>DRE Simplificado</h6>
    </div>
    <div class="card-body">
        <table class="table table-borderless mb-0">
            <tbody>
                <tr>
                    <td class="fw-bold fs-5">Receitas</td>
                    <td class="text-end fw-bold fs-5 text-success">R$ {{ number_format($receitas, 2, ',', '.') }}</td>
                </tr>
                <tr class="border-bottom">
                    <td class="ps-4 text-muted">(-) Custos (CMV)</td>
                    <td class="text-end text-danger">R$ {{ number_format($custos, 2, ',', '.') }}</td>
                </tr>
                <tr class="border-bottom">
                    <td class="fw-bold fs-6">=  Lucro Bruto</td>
                    <td class="text-end fw-bold fs-6 {{ $lucroBruto >= 0 ? 'text-success' : 'text-danger' }}">
                        R$ {{ number_format($lucroBruto, 2, ',', '.') }}
                    </td>
                </tr>
                <tr class="border-bottom">
                    <td class="ps-4 text-muted">(-) Despesas Operacionais</td>
                    <td class="text-end text-danger">R$ {{ number_format($despesasOperacionais, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="fw-bold fs-5">=  Resultado</td>
                    <td class="text-end fw-bold fs-5 {{ $resultado >= 0 ? 'text-success' : 'text-danger' }}">
                        R$ {{ number_format($resultado, 2, ',', '.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Chart --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-pie-chart me-1"></i>Receitas vs Despesas</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <canvas id="receitaDespesaChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Contas a Receber --}}
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-arrow-down-circle text-success me-1"></i>Contas a Receber por Vencimento</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Faixa</th>
                            <th class="text-center">Quantidade</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contasReceber as $faixa)
                        <tr class="{{ $faixa->faixa === 'Vencido' ? 'table-danger' : '' }}">
                            <td>{{ $faixa->faixa }}</td>
                            <td class="text-center">{{ $faixa->quantidade }}</td>
                            <td class="text-end fw-bold">R$ {{ number_format($faixa->total, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Nenhuma conta pendente.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Contas a Pagar --}}
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-arrow-up-circle text-danger me-1"></i>Contas a Pagar por Vencimento</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Faixa</th>
                            <th class="text-center">Quantidade</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contasPagar as $faixa)
                        <tr class="{{ $faixa->faixa === 'Vencido' ? 'table-danger' : '' }}">
                            <td>{{ $faixa->faixa }}</td>
                            <td class="text-center">{{ $faixa->quantidade }}</td>
                            <td class="text-end fw-bold">R$ {{ number_format($faixa->total, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Nenhuma conta pendente.</td>
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
    const ctx = document.getElementById('receitaDespesaChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Receitas', 'Custos', 'Despesas Operacionais'],
            datasets: [{
                data: [{{ $receitas }}, {{ $custos }}, {{ $despesasOperacionais }}],
                backgroundColor: ['#198754', '#dc3545', '#ffc107'],
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
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
</script>
@endpush
