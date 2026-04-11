@extends('layouts.app')

@section('title', 'Empresas')

@push('styles')
<style>
    .filter-card {
        border: none;
        border-radius: 0.75rem;
    }
    .table-empresas th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
        border-bottom-width: 2px;
    }
    .table-empresas td {
        vertical-align: middle;
    }
    .badge-plano {
        font-weight: 500;
        letter-spacing: 0.02em;
    }
    .empresa-row {
        transition: background-color 0.1s ease;
    }
    .empresa-row:hover {
        background-color: #f8fafc !important;
    }
    .results-info {
        font-size: 0.8125rem;
        color: #64748b;
    }
    .empty-state {
        padding: 4rem 2rem;
    }
    .empty-state i {
        font-size: 3rem;
        opacity: 0.15;
    }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-building me-2"></i>Empresas</h4>
        <p class="text-muted mb-0 small">Gerenciar empresas cadastradas na plataforma</p>
    </div>
    <a href="{{ route('admin.onboarding.step1') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Empresa
    </a>
</div>

{{-- Filtros --}}
<div class="card filter-card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('admin.empresas.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4 col-lg-5">
                <label for="search" class="form-label small fw-semibold text-muted mb-1">Buscar</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Razao social, nome fantasia ou CNPJ..."
                           value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2 col-lg-2">
                <label for="status" class="form-label small fw-semibold text-muted mb-1">Status</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">Todos</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-lg-2">
                <label for="plano" class="form-label small fw-semibold text-muted mb-1">Plano</label>
                <select class="form-select form-select-sm" id="plano" name="plano">
                    <option value="">Todos</option>
                    @foreach($planos as $plano)
                        <option value="{{ $plano->slug }}" {{ request('plano') === $plano->slug ? 'selected' : '' }}>
                            {{ $plano->nome }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-lg-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
            </div>
            @if(request()->hasAny(['search', 'status', 'plano']))
            <div class="col-md-2 col-lg-1">
                <a href="{{ route('admin.empresas.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-x-circle me-1"></i> Limpar
                </a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- Results info --}}
<div class="d-flex justify-content-between align-items-center mb-2">
    <span class="results-info">
        {{ $empresas->total() }} empresa{{ $empresas->total() !== 1 ? 's' : '' }} encontrada{{ $empresas->total() !== 1 ? 's' : '' }}
    </span>
</div>

{{-- Tabela --}}
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-empresas">
                <thead>
                    <tr>
                        <th class="ps-3">Empresa</th>
                        <th>CNPJ</th>
                        <th>Plano</th>
                        <th>Status</th>
                        <th class="text-center">Unidades</th>
                        <th class="text-center">Usuarios</th>
                        <th class="text-end pe-3">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($empresas as $empresa)
                    <tr class="empresa-row">
                        <td class="ps-3">
                            <div class="fw-semibold">{{ $empresa->razao_social }}</div>
                            @if($empresa->nome_fantasia)
                                <small class="text-muted">{{ $empresa->nome_fantasia }}</small>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            <code class="text-body-secondary">{{ $empresa->cnpj }}</code>
                        </td>
                        <td>
                            @if($empresa->plano)
                                <span class="badge bg-primary bg-opacity-10 text-primary badge-plano">
                                    {{ ucfirst($empresa->plano) }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $empresa->status->color() }}">
                                {{ $empresa->status->label() }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">{{ $empresa->unidades_count }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">{{ $empresa->users_count }}</span>
                        </td>
                        <td class="text-end pe-3 text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.empresas.show', $empresa) }}"
                                   class="btn btn-outline-primary" title="Visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.empresas.edit', $empresa) }}"
                                   class="btn btn-outline-secondary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                            <x-delete-form :action="route('admin.empresas.destroy', $empresa)" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="empty-state text-center">
                            <i class="bi bi-building d-block mb-2"></i>
                            <p class="text-muted mb-1 fw-semibold">Nenhuma empresa encontrada</p>
                            <small class="text-muted">
                                @if(request()->hasAny(['search', 'status', 'plano']))
                                    Tente ajustar os filtros de busca.
                                @else
                                    Comece cadastrando a primeira empresa.
                                @endif
                            </small>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($empresas->hasPages())
    <div class="card-footer bg-white border-top py-3">
        {{ $empresas->links() }}
    </div>
    @endif
</div>
@endsection
