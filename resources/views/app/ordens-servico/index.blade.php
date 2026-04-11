@extends('layouts.app')

@section('title', 'Ordens de Servico')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-wrench-adjustable me-2"></i>Ordens de Servico</h4>
    <a href="{{ route('app.ordens-servico.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova OS
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <p class="text-muted small mb-1">Abertas</p>
                <h4 class="fw-bold mb-0">{{ $abertas }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body">
                <p class="text-muted small mb-1">Em Andamento</p>
                <h4 class="fw-bold mb-0">{{ $emAndamento }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 border-start border-4 border-info">
            <div class="card-body">
                <p class="text-muted small mb-1">Aguardando Peca</p>
                <h4 class="fw-bold mb-0">{{ $aguardandoPeca }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <p class="text-muted small mb-1">Concluidas (Mes)</p>
                <h4 class="fw-bold mb-0">{{ $concluidasMes }}</h4>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.ordens-servico.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="busca" class="form-control" placeholder="Buscar por numero, cliente ou equipamento..."
                       value="{{ request('busca') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="aberta" {{ request('status') == 'aberta' ? 'selected' : '' }}>Aberta</option>
                    <option value="em_andamento" {{ request('status') == 'em_andamento' ? 'selected' : '' }}>Em Andamento</option>
                    <option value="aguardando_peca" {{ request('status') == 'aguardando_peca' ? 'selected' : '' }}>Aguardando Peca</option>
                    <option value="concluida" {{ request('status') == 'concluida' ? 'selected' : '' }}>Concluida</option>
                    <option value="entregue" {{ request('status') == 'entregue' ? 'selected' : '' }}>Entregue</option>
                    <option value="cancelada" {{ request('status') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('app.ordens-servico.index') }}" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-circle me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card shadow-sm">
    <div class="card-body p-0">
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
                        <th class="text-center">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ordensServico as $os)
                        <tr>
                            <td><strong>#{{ $os->numero }}</strong></td>
                            <td>{{ $os->created_at->format('d/m/Y') }}</td>
                            <td>{{ $os->cliente->nome_razao_social ?? '-' }}</td>
                            <td>{{ $os->equipamento }}</td>
                            <td>{{ $os->tecnico->name ?? '-' }}</td>
                            <td class="text-center">
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
                                <span class="badge bg-{{ $statusColors[$os->status] ?? 'secondary' }}">
                                    {{ $statusLabels[$os->status] ?? $os->status }}
                                </span>
                            </td>
                            <td class="text-end">R$ {{ number_format($os->total, 2, ',', '.') }}</td>
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
                            <td colspan="8" class="text-center text-muted py-4">Nenhuma ordem de servico encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($ordensServico->hasPages())
        <div class="card-footer bg-white">
            {{ $ordensServico->links() }}
        </div>
    @endif
</div>
@endsection
