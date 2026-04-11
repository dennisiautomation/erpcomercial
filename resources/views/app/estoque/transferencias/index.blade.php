@extends('layouts.app')

@section('title', 'Transferencias de Estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-truck me-2"></i>Transferencias de Estoque</h4>
    <a href="{{ route('app.transferencias.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Transferencia
    </a>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="solicitada" {{ request('status') == 'solicitada' ? 'selected' : '' }}>Solicitada</option>
                    <option value="aprovada" {{ request('status') == 'aprovada' ? 'selected' : '' }}>Aprovada</option>
                    <option value="cancelada" {{ request('status') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary">
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
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Data</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Solicitante</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Itens</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($transferencias as $transf)
                <tr>
                    <td>{{ $transf->id }}</td>
                    <td>{{ $transf->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $transf->unidadeOrigem->nome ?? '-' }}</td>
                    <td>{{ $transf->unidadeDestino->nome ?? '-' }}</td>
                    <td>{{ $transf->solicitante->name ?? '-' }}</td>
                    <td class="text-center">
                        @php
                            $statusBadge = match($transf->status) {
                                'solicitada' => 'bg-warning text-dark',
                                'aprovada' => 'bg-success',
                                'cancelada' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }}">{{ ucfirst($transf->status) }}</span>
                    </td>
                    <td class="text-center">{{ $transf->itens_count }}</td>
                    <td>
                        <a href="{{ route('app.transferencias.show', $transf) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Nenhuma transferencia encontrada.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transferencias->hasPages())
    <div class="card-footer">
        {{ $transferencias->links() }}
    </div>
    @endif
</div>
@endsection
