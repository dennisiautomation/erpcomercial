@extends('layouts.app')

@section('title', 'Multilojas - Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-shop-window me-2"></i>Dashboard Multilojas</h4>
    <a href="{{ route('app.multilojas.comparar') }}" class="btn btn-outline-primary">
        <i class="bi bi-bar-chart me-1"></i> Comparar Unidades
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Faturamento Total</p>
                        <h4 class="fw-bold text-success mb-0">R$ {{ number_format($faturamentoTotal, 2, ',', '.') }}</h4>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-currency-dollar text-success fs-4"></i>
                    </div>
                </div>
                <small class="text-muted">Mes atual</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Vendas Total</p>
                        <h4 class="fw-bold text-primary mb-0">{{ $vendasTotal }}</h4>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-receipt text-primary fs-4"></i>
                    </div>
                </div>
                <small class="text-muted">Mes atual</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Ticket Medio Global</p>
                        <h4 class="fw-bold text-info mb-0">R$ {{ number_format($ticketMedioGlobal, 2, ',', '.') }}</h4>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-graph-up-arrow text-info fs-4"></i>
                    </div>
                </div>
                <small class="text-muted">Mes atual</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Unidades Ativas</p>
                        <h4 class="fw-bold text-dark mb-0">{{ $unidades->count() }}</h4>
                    </div>
                    <div class="bg-dark bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-building text-dark fs-4"></i>
                    </div>
                </div>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
</div>

{{-- Alerts --}}
@if(count($alertas) > 0)
<div class="mb-4">
    @foreach($alertas as $alerta)
        <div class="alert alert-{{ $alerta['tipo'] }} d-flex align-items-center" role="alert">
            <i class="bi {{ $alerta['icone'] }} me-2"></i>
            {{ $alerta['mensagem'] }}
        </div>
    @endforeach
</div>
@endif

{{-- Unidades Table --}}
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-table me-2"></i>Performance por Unidade</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="50">#</th>
                        <th>Unidade</th>
                        <th class="text-end">Faturamento</th>
                        <th class="text-center">Vendas</th>
                        <th class="text-end">Ticket Medio</th>
                        <th class="text-center">Estoque Critico</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dadosUnidades as $index => $dados)
                        <tr>
                            <td>
                                @if($index === 0)
                                    <span class="badge bg-warning text-dark"><i class="bi bi-trophy"></i> 1o</span>
                                @elseif($index === 1)
                                    <span class="badge bg-secondary">2o</span>
                                @elseif($index === 2)
                                    <span class="badge bg-danger bg-opacity-75">3o</span>
                                @else
                                    <span class="text-muted">{{ $index + 1 }}o</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $dados['unidade']->nome }}</strong>
                                <br><small class="text-muted">{{ $dados['unidade']->cidade }}/{{ $dados['unidade']->uf }}</small>
                            </td>
                            <td class="text-end fw-bold">R$ {{ number_format($dados['faturamento'], 2, ',', '.') }}</td>
                            <td class="text-center">{{ $dados['total_vendas'] }}</td>
                            <td class="text-end">R$ {{ number_format($dados['ticket_medio'], 2, ',', '.') }}</td>
                            <td class="text-center">
                                @if($dados['estoque_critico'] > 0)
                                    <span class="badge bg-danger">{{ $dados['estoque_critico'] }}</span>
                                @else
                                    <span class="badge bg-success">OK</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success">Ativa</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Nenhuma unidade encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
