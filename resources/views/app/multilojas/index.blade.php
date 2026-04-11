@extends('layouts.app')

@section('title', 'Multilojas - Dashboard')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-shop-window me-2"></i>Dashboard Multilojas</h4>
        <div class="subtitle">Visao consolidada de todas as unidades</div>
    </div>
    <a href="{{ route('app.multilojas.comparar') }}" class="btn btn-erp btn-erp-outline">
        <i class="bi bi-bar-chart me-1"></i> Comparar Unidades
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Faturamento Total</div>
                    <div class="stat-value" style="color: var(--success);">R$ {{ number_format($faturamentoTotal, 2, ',', '.') }}</div>
                </div>
                <div class="stat-icon success">
                    <i class="bi bi-currency-dollar"></i>
                </div>
            </div>
            <div class="stat-trend">Mes atual</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Vendas Total</div>
                    <div class="stat-value" style="color: var(--primary);">{{ $vendasTotal }}</div>
                </div>
                <div class="stat-icon primary">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
            <div class="stat-trend">Mes atual</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Ticket Medio Global</div>
                    <div class="stat-value" style="color: var(--info);">R$ {{ number_format($ticketMedioGlobal, 2, ',', '.') }}</div>
                </div>
                <div class="stat-icon info">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
            <div class="stat-trend">Mes atual</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Unidades Ativas</div>
                    <div class="stat-value">{{ $unidades->count() }}</div>
                </div>
                <div class="stat-icon primary">
                    <i class="bi bi-building"></i>
                </div>
            </div>
            <div class="stat-trend">Total</div>
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
<div class="erp-card">
    <div class="card-header">
        <i class="bi bi-table me-2"></i>Performance por Unidade
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="erp-table">
                <thead>
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
                                    <span class="badge-status aprovado"><i class="bi bi-trophy"></i> 1o</span>
                                @elseif($index === 1)
                                    <span class="badge-status inativo">2o</span>
                                @elseif($index === 2)
                                    <span class="badge-status cancelado">3o</span>
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
                                    <span class="badge-status cancelado">{{ $dados['estoque_critico'] }}</span>
                                @else
                                    <span class="badge-status ativo">OK</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge-status ativa">Ativa</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-shop-window d-block"></i>
                                    <h5>Nenhuma unidade encontrada</h5>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
