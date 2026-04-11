@extends('layouts.app')

@section('title', 'Transferencias de Estoque')

@section('content')
<x-erp.page-header title="Transferencias de Estoque" subtitle="Gerencie transferencias entre unidades" icon="truck">
    <a href="{{ route('app.transferencias.create') }}" class="btn btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Transferencia
    </a>
</x-erp.page-header>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="hourglass-split" color="warning" :value="$totalSolicitadas" label="Solicitadas" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="check-circle" color="success" :value="$totalAprovadas" label="Aprovadas" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="check-all" color="primary" :value="$totalConcluidas" label="Concluidas" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="x-circle" color="danger" :value="$totalCanceladas" label="Canceladas" />
    </div>
</div>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.transferencias.index')">
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
    <div class="col-auto">
        <a href="{{ route('app.transferencias.index') }}" class="btn btn-erp-outline">
            <i class="bi bi-x-lg me-1"></i> Limpar
        </a>
    </div>
</x-erp.filter-bar>

{{-- Table --}}
<x-erp.data-table>
    <thead>
        <tr>
            <th>#</th>
            <th>Data</th>
            <th>Origem</th>
            <th></th>
            <th>Destino</th>
            <th>Solicitante</th>
            <th class="text-center">Status</th>
            <th class="text-center">Itens</th>
            <th class="text-center">Acoes</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transferencias as $transf)
        <tr>
            <td class="fw-semibold text-muted">#{{ $transf->id }}</td>
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
                <x-erp.status-badge :status="$transf->status" />
            </td>
            <td class="text-center">
                <span class="badge bg-light text-dark border rounded-pill">{{ $transf->itens_count }}</span>
            </td>
            <td class="text-center">
                <div class="action-btns">
                    <a href="{{ route('app.transferencias.show', $transf) }}" class="btn btn-sm btn-erp-outline" title="Detalhes">
                        <i class="bi bi-eye"></i>
                    </a>
                </div>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="9">
                <x-erp.empty-state icon="truck" title="Nenhuma transferencia encontrada" />
            </td>
        </tr>
        @endforelse
    </tbody>
    <x-slot name="pagination">
        @if($transferencias->hasPages())
            {{ $transferencias->links() }}
        @endif
    </x-slot>
</x-erp.data-table>
@endsection
