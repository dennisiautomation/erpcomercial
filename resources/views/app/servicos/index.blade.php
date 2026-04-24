@extends('layouts.app')

@section('title', 'Servicos')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-tools me-2"></i>Servicos</h4>
        <div class="subtitle">Gerencie os servicos prestados</div>
    </div>
    <a href="{{ route('app.servicos.create') }}" class="btn btn-erp btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Servico
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total</div>
                    <div class="stat-value">{{ $totalAtivos + $totalInativos }}</div>
                </div>
                <div class="stat-icon primary">
                    <i class="bi bi-tools"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Ativos</div>
                    <div class="stat-value" style="color: var(--success);">{{ $totalAtivos }}</div>
                </div>
                <div class="stat-icon success">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Inativos</div>
                    <div class="stat-value" style="color: var(--text-muted);">{{ $totalInativos }}</div>
                </div>
                <div class="stat-icon danger">
                    <i class="bi bi-pause-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('app.servicos.index') }}" class="row g-2 align-items-end erp-form">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="busca" class="form-control" placeholder="Buscar por descricao ou codigo..." value="{{ request('busca') }}">
            </div>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">Todos</option>
                <option value="ativo" {{ request('status') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                <option value="inativo" {{ request('status') === 'inativo' ? 'selected' : '' }}>Inativo</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-erp btn-erp-primary w-100">
                <i class="bi bi-search me-1"></i> Filtrar
            </button>
        </div>
        <div class="col-md-2">
            <a href="{{ route('app.servicos.index') }}" class="btn btn-erp btn-erp-outline w-100">
                <i class="bi bi-x-lg me-1"></i> Limpar
            </a>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="erp-card">
    <div class="table-responsive">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Descricao</th>
                    <th class="text-end">Valor Padrao</th>
                    <th>Cod. Municipal</th>
                    <th>ISS (%)</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 130px;">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($servicos as $servico)
                    <tr>
                        <td><code>{{ $servico->codigo ?: '-' }}</code></td>
                        <td><strong>{{ $servico->descricao }}</strong></td>
                        <td class="text-end fw-semibold">R$ {{ number_format($servico->valor_padrao, 2, ',', '.') }}</td>
                        <td class="text-muted">{{ $servico->codigo_servico_municipal ?: '-' }}</td>
                        <td>{{ $servico->iss_aliquota ? number_format($servico->iss_aliquota, 2, ',', '.') . '%' : '-' }}</td>
                        <td class="text-center">
                            <span class="badge-status {{ $servico->status }}">
                                {{ ucfirst($servico->status) }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="action-btns justify-content-center">
                                <a href="{{ route('app.servicos.edit', $servico) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('app.servicos.destroy', $servico) }}" class="d-inline"
                                      data-confirm="Tem certeza que deseja excluir este servico?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-tools d-block"></i>
                                <h5>Nenhum servico encontrado</h5>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($servicos->hasPages())
        <div class="card-body border-top">{{ $servicos->links() }}</div>
    @endif
</div>
</div>
@endsection
