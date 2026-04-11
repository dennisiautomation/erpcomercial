@extends('layouts.app')

@section('title', 'Dashboard Admin')

@section('content')
<div class="row g-4 mb-4">
    {{-- Empresas Ativas --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-start border-primary border-4 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                    <i class="bi bi-building fs-3 text-primary"></i>
                </div>
                <div>
                    <div class="text-muted small">Empresas Ativas</div>
                    <div class="fs-4 fw-bold">{{ $totalEmpresas ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Total Unidades --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-start border-success border-4 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 rounded-3 p-3">
                    <i class="bi bi-shop fs-3 text-success"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Unidades</div>
                    <div class="fs-4 fw-bold">{{ $totalUnidades ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Usuarios Ativos --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-start border-info border-4 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-info bg-opacity-10 rounded-3 p-3">
                    <i class="bi bi-people fs-3 text-info"></i>
                </div>
                <div>
                    <div class="text-muted small">Usuarios Ativos</div>
                    <div class="fs-4 fw-bold">{{ $totalUsuarios ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Notas Emitidas Mes --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-start border-warning border-4 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                    <i class="bi bi-receipt fs-3 text-warning"></i>
                </div>
                <div>
                    <div class="text-muted small">Notas Emitidas Mes</div>
                    <div class="fs-4 fw-bold">{{ $totalNotas ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Empresas Recentes --}}
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-building me-2"></i>Empresas Recentes</h5>
        <a href="{{ route('admin.empresas.index') }}" class="btn btn-outline-primary btn-sm">
            Ver todas <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Razao Social</th>
                        <th>CNPJ</th>
                        <th>Plano</th>
                        <th>Status</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($empresas ?? [] as $empresa)
                    <tr>
                        <td class="fw-semibold">{{ $empresa->razao_social }}</td>
                        <td>{{ $empresa->cnpj }}</td>
                        <td><span class="badge bg-primary">{{ ucfirst($empresa->plano) }}</span></td>
                        <td>
                            <span class="badge bg-{{ $empresa->status->color() }}">
                                {{ $empresa->status->label() }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-primary btn-sm" title="Visualizar">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('admin.empresas.edit', $empresa) }}" class="btn btn-outline-secondary btn-sm" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Nenhuma empresa cadastrada.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
