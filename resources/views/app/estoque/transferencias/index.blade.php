@extends('layouts.app')

@section('title', 'Transferencias de Estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-truck me-2"></i>Transferencias de Estoque</h4>
        <p class="text-muted mb-0 small">Gerencie transferencias entre unidades</p>
    </div>
    <a href="{{ route('app.transferencias.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Transferencia
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-warning bg-opacity-10 p-3 me-3">
                        <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Solicitadas</div>
                        <div class="fs-4 fw-bold text-warning">{{ $totalSolicitadas }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-check-circle fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Aprovadas</div>
                        <div class="fs-4 fw-bold text-success">{{ $totalAprovadas }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-primary bg-opacity-10 p-3 me-3">
                        <i class="bi bi-check-all fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Concluidas</div>
                        <div class="fs-4 fw-bold text-primary">{{ $totalConcluidas }}</div>
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
                        <i class="bi bi-x-circle fs-4 text-danger"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Canceladas</div>
                        <div class="fs-4 fw-bold text-danger">{{ $totalCanceladas }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="solicitada" {{ request('status') == 'solicitada' ? 'selected' : '' }}>Solicitada</option>
                    <option value="aprovada" {{ request('status') == 'aprovada' ? 'selected' : '' }}>Aprovada</option>
                    <option value="em_transito" {{ request('status') == 'em_transito' ? 'selected' : '' }}>Em Transito</option>
                    <option value="concluida" {{ request('status') == 'concluida' ? 'selected' : '' }}>Concluida</option>
                    <option value="cancelada" {{ request('status') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.transferencias.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="bg-light">
                    <th class="ps-3">#</th>
                    <th>Data</th>
                    <th>Origem</th>
                    <th></th>
                    <th>Destino</th>
                    <th>Solicitante</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Itens</th>
                    <th class="text-center pe-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transferencias as $transf)
                <tr>
                    <td class="ps-3 fw-semibold text-muted">#{{ $transf->id }}</td>
                    <td>
                        <div class="fw-semibold">{{ $transf->created_at->format('d/m/Y') }}</div>
                        <small class="text-muted">{{ $transf->created_at->format('H:i') }}</small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-building me-1"></i>{{ $transf->unidadeOrigem->nome ?? '-' }}
                        </span>
                    </td>
                    <td class="text-center px-0">
                        <i class="bi bi-arrow-right text-primary"></i>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-building me-1"></i>{{ $transf->unidadeDestino->nome ?? '-' }}
                        </span>
                    </td>
                    <td><small>{{ $transf->solicitante->name ?? '-' }}</small></td>
                    <td class="text-center">
                        @php
                            $statusBadge = match($transf->status) {
                                'solicitada' => 'bg-warning text-dark',
                                'aprovada' => 'bg-success',
                                'em_transito' => 'bg-info text-dark',
                                'concluida' => 'bg-primary',
                                'cancelada' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                            $statusIcon = match($transf->status) {
                                'solicitada' => 'bi-hourglass-split',
                                'aprovada' => 'bi-check-circle',
                                'em_transito' => 'bi-truck',
                                'concluida' => 'bi-check-all',
                                'cancelada' => 'bi-x-circle',
                                default => 'bi-circle',
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }} rounded-pill px-3">
                            <i class="bi {{ $statusIcon }} me-1"></i>{{ ucfirst(str_replace('_', ' ', $transf->status)) }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border rounded-pill">{{ $transf->itens_count }}</span>
                    </td>
                    <td class="text-center pe-3">
                        <a href="{{ route('app.transferencias.show', $transf) }}" class="btn btn-sm btn-outline-primary" title="Detalhes">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-truck fs-1 d-block mb-2 opacity-25"></i>
                            Nenhuma transferencia encontrada.
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transferencias->hasPages())
    <div class="card-footer bg-white border-top">
        {{ $transferencias->links() }}
    </div>
    @endif
</div>
@endsection
