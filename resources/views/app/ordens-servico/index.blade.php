@extends('layouts.app')

@section('title', 'Ordens de Servico')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-wrench-adjustable me-2"></i>Ordens de Servico</h4>
    <a href="{{ route('app.ordens-servico.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nova OS
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Abertas</p>
                        <h3 class="fw-bold mb-0">{{ $abertas }}</h3>
                    </div>
                    <div class="rounded-3 bg-warning bg-opacity-10 p-2">
                        <i class="bi bi-folder2-open fs-4 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Em Andamento</p>
                        <h3 class="fw-bold mb-0 text-primary">{{ $emAndamento }}</h3>
                    </div>
                    <div class="rounded-3 bg-primary bg-opacity-10 p-2">
                        <i class="bi bi-gear fs-4 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Aguardando Peca</p>
                        <h3 class="fw-bold mb-0 text-info">{{ $aguardandoPeca }}</h3>
                    </div>
                    <div class="rounded-3 bg-info bg-opacity-10 p-2">
                        <i class="bi bi-clock-history fs-4 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Concluidas (Mes)</p>
                        <h3 class="fw-bold mb-0 text-success">{{ $concluidasMes }}</h3>
                    </div>
                    <div class="rounded-3 bg-success bg-opacity-10 p-2">
                        <i class="bi bi-check-circle fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('app.ordens-servico.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="busca" class="form-control" placeholder="Buscar por numero, cliente ou equipamento..."
                           value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos os status</option>
                    @php
                        $statusOptions = [
                            'aberta' => 'Aberta',
                            'em_andamento' => 'Em Andamento',
                            'aguardando_peca' => 'Aguardando Peca',
                            'concluida' => 'Concluida',
                            'entregue' => 'Entregue',
                            'cancelada' => 'Cancelada',
                        ];
                    @endphp
                    @foreach($statusOptions as $val => $label)
                        <option value="{{ $val }}" {{ request('status') == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('app.ordens-servico.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-x-lg me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Numero</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Equipamento</th>
                    <th>Tecnico</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Total</th>
                    <th class="text-center" style="width: 100px;">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ordensServico as $os)
                    @php
                        $statusColors = [
                            'aberta' => 'warning',
                            'em_andamento' => 'primary',
                            'aguardando_peca' => 'info',
                            'concluida' => 'success',
                            'entregue' => 'dark',
                            'cancelada' => 'danger',
                        ];
                        $statusLabels = [
                            'aberta' => 'Aberta',
                            'em_andamento' => 'Em Andamento',
                            'aguardando_peca' => 'Aguard. Peca',
                            'concluida' => 'Concluida',
                            'entregue' => 'Entregue',
                            'cancelada' => 'Cancelada',
                        ];
                    @endphp
                    <tr>
                        <td><strong>#{{ $os->numero }}</strong></td>
                        <td class="text-nowrap small">{{ $os->created_at->format('d/m/Y') }}</td>
                        <td>{{ $os->cliente->nome_razao_social ?? '-' }}</td>
                        <td>
                            <span class="text-truncate d-inline-block" style="max-width: 200px;" title="{{ $os->equipamento }}">
                                {{ $os->equipamento }}
                            </span>
                        </td>
                        <td>{{ $os->tecnico->name ?? '-' }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $statusColors[$os->status] ?? 'secondary' }}">
                                {{ $statusLabels[$os->status] ?? $os->status }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold">R$ {{ number_format($os->total, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.ordens-servico.show', $os) }}" class="btn btn-outline-primary" title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if(!in_array($os->status, ['entregue', 'cancelada']))
                                    <a href="{{ route('app.ordens-servico.edit', $os) }}" class="btn btn-outline-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-wrench fs-1 d-block mb-2 opacity-50"></i>
                            Nenhuma ordem de servico encontrada.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($ordensServico->hasPages())
        <div class="card-footer bg-white">
            {{ $ordensServico->links() }}
        </div>
    @endif
</div>
@endsection
