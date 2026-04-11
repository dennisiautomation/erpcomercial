@extends('layouts.app')

@section('title', 'Contratos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Contratos / Cobranças Recorrentes</h4>
    <a href="{{ route('app.contratos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Contrato
    </a>
</div>

{{-- Summary Cards --}}
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Contratos Ativos</div>
                        <div class="fs-4 fw-bold text-primary">{{ $ativos }}</div>
                    </div>
                    <i class="bi bi-file-earmark-check fs-1 text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Vencendo em 30 dias</div>
                        <div class="fs-4 fw-bold text-warning">{{ $vencendo30d }}</div>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 text-warning opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Valor Recorrente Mensal</div>
                        <div class="fs-4 fw-bold text-success">R$ {{ number_format($valorRecorrenteMensal, 2, ',', '.') }}</div>
                    </div>
                    <i class="bi bi-currency-dollar fs-1 text-success opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
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
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
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
                        <span class="badge bg-info text-dark">{{ ucfirst($contrato->periodicidade) }}</span>
                    </td>
                    <td>{{ $contrato->proximo_faturamento?->format('d/m/Y') ?? '-' }}</td>
                    <td>
                        @php
                            $statusClass = match($contrato->status) {
                                'ativo' => 'success',
                                'vencido' => 'danger',
                                'cancelado' => 'secondary',
                                'suspenso' => 'warning',
                                default => 'secondary',
                            };
                        @endphp
                        <span class="badge bg-{{ $statusClass }}">{{ ucfirst($contrato->status) }}</span>
                    </td>
                    <td>
                        <a href="{{ route('app.contratos.show', $contrato) }}" class="btn btn-sm btn-outline-primary" title="Ver">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('app.contratos.edit', $contrato) }}" class="btn btn-sm btn-outline-secondary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if($contrato->status === 'ativo')
                        <form action="{{ route('app.contratos.faturar', $contrato) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Faturar" onclick="return confirm('Gerar faturamento?')">
                                <i class="bi bi-receipt"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhum contrato encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $contratos->links() }}
</div>
@endsection
