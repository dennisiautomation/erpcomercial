@extends('layouts.app')

@section('title', 'Contratos')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-file-earmark-text me-2"></i>Contratos / Cobranças Recorrentes</h4>
        <div class="subtitle">Gerencie contratos e cobranças recorrentes</div>
    </div>
    <a href="{{ route('app.contratos.create') }}" class="btn btn-erp btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Contrato
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Contratos Ativos</div>
                    <div class="stat-value" style="color: var(--primary);">{{ $ativos }}</div>
                </div>
                <div class="stat-icon primary">
                    <i class="bi bi-file-earmark-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Vencendo em 30 dias</div>
                    <div class="stat-value" style="color: var(--warning);">{{ $vencendo30d }}</div>
                </div>
                <div class="stat-icon warning">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Valor Recorrente Mensal</div>
                    <div class="stat-value" style="color: var(--success);">R$ {{ number_format($valorRecorrenteMensal, 2, ',', '.') }}</div>
                </div>
                <div class="stat-icon success">
                    <i class="bi bi-currency-dollar"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="filter-bar">
    <form method="GET" class="row g-3 erp-form">
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">Todos</option>
                <option value="ativo" @selected(request('status') == 'ativo')>Ativo</option>
                <option value="vencido" @selected(request('status') == 'vencido')>Vencido</option>
                <option value="cancelado" @selected(request('status') == 'cancelado')>Cancelado</option>
                <option value="suspenso" @selected(request('status') == 'suspenso')>Suspenso</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Cliente</label>
            <select name="cliente_id" class="form-select">
                <option value="">Todos</option>
                @foreach($clientes as $cliente)
                    <option value="{{ $cliente->id }}" @selected(request('cliente_id') == $cliente->id)>{{ $cliente->nome_razao_social }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Inicio</label>
            <input type="date" name="inicio" class="form-control" value="{{ request('inicio') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">Fim</label>
            <input type="date" name="fim" class="form-control" value="{{ request('fim') }}">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-erp btn-erp-primary w-100">
                <i class="bi bi-search me-1"></i> Filtrar
            </button>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="erp-card">
    <div class="table-responsive">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Descricao</th>
                    <th>Valor</th>
                    <th>Periodicidade</th>
                    <th>Prox. Faturamento</th>
                    <th>Status</th>
                    <th width="150">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contratos as $contrato)
                <tr>
                    <td>{{ $contrato->cliente->nome_razao_social ?? '-' }}</td>
                    <td>{{ Str::limit($contrato->descricao, 40) }}</td>
                    <td>R$ {{ number_format($contrato->valor, 2, ',', '.') }}</td>
                    <td>
                        <span class="badge-status confirmado">{{ ucfirst($contrato->periodicidade) }}</span>
                    </td>
                    <td>{{ $contrato->proximo_faturamento?->format('d/m/Y') ?? '-' }}</td>
                    <td>
                        <span class="badge-status {{ $contrato->status }}">{{ ucfirst($contrato->status) }}</span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="{{ route('app.contratos.show', $contrato) }}" class="btn btn-sm btn-outline-primary" title="Ver">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('app.contratos.edit', $contrato) }}" class="btn btn-sm btn-outline-secondary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if($contrato->status === 'ativo')
                            <form action="{{ route('app.contratos.faturar', $contrato) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Faturar" data-confirm="Gerar faturamento?">
                                    <i class="bi bi-receipt"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="bi bi-file-earmark-text d-block"></i>
                            <h5>Nenhum contrato encontrado</h5>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($contratos->hasPages())
        <div class="card-body border-top">
            {{ $contratos->links() }}
        </div>
    @endif
</div>
</div>
@endsection
