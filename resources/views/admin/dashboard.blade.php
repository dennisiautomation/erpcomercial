@extends('layouts.app')

@section('title', 'Dashboard Admin')

@push('styles')
<style>
    .stat-card {
        border: none;
        border-radius: 0.75rem;
        transition: all 0.2s ease;
        cursor: default;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
    }
    .stat-icon {
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 1.5rem;
    }
    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-label {
        font-size: 0.8125rem;
        color: #64748b;
        font-weight: 500;
    }
    .quick-action {
        transition: all 0.15s ease;
        border: 2px dashed #dee2e6;
        border-radius: 0.75rem;
    }
    .quick-action:hover {
        border-color: #0d6efd;
        background: #f8f9ff;
        transform: translateY(-2px);
    }
    .table-recent th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
        border-bottom-width: 2px;
    }
    .badge-plano {
        font-weight: 500;
        letter-spacing: 0.02em;
    }
</style>
@endpush

@section('content')
{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-building"></i>
                </div>
                <div>
                    <div class="stat-value text-dark">{{ $totalEmpresas }}</div>
                    <div class="stat-label">Empresas Ativas</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-shop"></i>
                </div>
                <div>
                    <div class="stat-value text-dark">{{ $totalUnidades }}</div>
                    <div class="stat-label">Unidades Ativas</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="stat-value text-dark">{{ $totalUsuarios }}</div>
                    <div class="stat-label">Usuarios</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="stat-value text-dark">{{ $empresasEmTrial }}</div>
                    <div class="stat-label">Em Trial</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Actions + Recent Empresas --}}
<div class="row g-4">
    {{-- Quick Actions --}}
    <div class="col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <h6 class="fw-semibold text-muted mb-0">
                    <i class="bi bi-lightning-charge me-1"></i> Acoes Rapidas
                </h6>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                <a href="{{ route('admin.empresas.create') }}" class="quick-action text-decoration-none text-dark d-flex align-items-center gap-3 p-3">
                    <div class="bg-primary bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-building-add text-primary fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Nova Empresa</div>
                        <small class="text-muted">Cadastrar cliente</small>
                    </div>
                </a>
                <a href="{{ route('admin.usuarios.create') }}" class="quick-action text-decoration-none text-dark d-flex align-items-center gap-3 p-3">
                    <div class="bg-info bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-person-plus text-info fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Novo Usuario</div>
                        <small class="text-muted">Adicionar acesso</small>
                    </div>
                </a>
                <a href="{{ route('admin.planos.index') }}" class="quick-action text-decoration-none text-dark d-flex align-items-center gap-3 p-3">
                    <div class="bg-success bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-credit-card-2-front text-success fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Gerenciar Planos</div>
                        <small class="text-muted">Ver planos disponiveis</small>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- Recent Empresas --}}
    <div class="col-xl-9">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-clock-history me-1 text-muted"></i> Empresas Recentes
                </h6>
                <a href="{{ route('admin.empresas.index') }}" class="btn btn-sm btn-outline-primary">
                    Ver todas <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-recent">
                        <thead>
                            <tr>
                                <th class="ps-3">Empresa</th>
                                <th>CNPJ</th>
                                <th>Plano</th>
                                <th>Status</th>
                                <th>Criada em</th>
                                <th class="text-end pe-3">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($empresas as $empresa)
                            <tr>
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
                                <td class="text-muted small">
                                    {{ $empresa->created_at->format('d/m/Y') }}
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    <a href="{{ route('admin.empresas.show', $empresa) }}"
                                       class="btn btn-outline-primary btn-sm" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.empresas.edit', $empresa) }}"
                                       class="btn btn-outline-secondary btn-sm" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-building fs-1 d-block mb-2 opacity-25"></i>
                                    Nenhuma empresa cadastrada ainda.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
