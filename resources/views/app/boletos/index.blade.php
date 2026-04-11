@extends('layouts.app')

@section('title', 'Boletos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-upc-scan me-2"></i>Boletos</h4>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCarne">
            <i class="bi bi-collection me-1"></i> Gerar Carne
        </button>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Pendente</div>
                        <div class="fs-4 fw-bold text-warning">R$ {{ number_format($totalPendente, 2, ',', '.') }}</div>
                    </div>
                    <i class="bi bi-clock-history fs-1 text-warning opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-danger border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Vencido</div>
                        <div class="fs-4 fw-bold text-danger">R$ {{ number_format($totalVencido, 2, ',', '.') }}</div>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 text-danger opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Pago no Mes</div>
                        <div class="fs-4 fw-bold text-success">R$ {{ number_format($totalPago, 2, ',', '.') }}</div>
                    </div>
                    <i class="bi bi-check-circle fs-1 text-success opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendente" @selected(request('status') == 'pendente')>Pendente</option>
                    <option value="pago" @selected(request('status') == 'pago')>Pago</option>
                    <option value="vencido" @selected(request('status') == 'vencido')>Vencido</option>
                    <option value="cancelado" @selected(request('status') == 'cancelado')>Cancelado</option>
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
                <label class="form-label">Vencimento De</label>
                <input type="date" name="vencimento_inicio" class="form-control" value="{{ request('vencimento_inicio') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Vencimento Ate</label>
                <input type="date" name="vencimento_fim" class="form-control" value="{{ request('vencimento_fim') }}">
            </div>
            <div class="col-md-3 d-flex align-items-end">
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
                    <th>Nosso Numero</th>
                    <th>Cliente</th>
                    <th>Valor</th>
                    <th>Vencimento</th>
                    <th>Status</th>
                    <th width="180">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($boletos as $boleto)
                <tr>
                    <td>{{ $boleto->nosso_numero ?? '-' }}</td>
                    <td>{{ $boleto->cliente->nome_razao_social ?? '-' }}</td>
                    <td>R$ {{ number_format($boleto->valor, 2, ',', '.') }}</td>
                    <td>{{ $boleto->vencimento?->format('d/m/Y') }}</td>
                    <td>
                        @php
                            $statusClass = match($boleto->status) {
                                'pago' => 'success',
                                'pendente' => 'warning',
                                'vencido' => 'danger',
                                'cancelado' => 'secondary',
                                default => 'secondary',
                            };
                        @endphp
                        <span class="badge bg-{{ $statusClass }}">{{ ucfirst($boleto->status) }}</span>
                    </td>
                    <td>
                        <a href="{{ route('app.boletos.show', $boleto) }}" class="btn btn-sm btn-outline-primary" title="Ver">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if($boleto->status === 'pendente')
                        <form action="{{ route('app.boletos.baixar', $boleto) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Baixar" onclick="return confirm('Marcar como pago?')">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <form action="{{ route('app.boletos.cancelar', $boleto) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar" onclick="return confirm('Cancelar este boleto?')">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhum boleto encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $boletos->links() }}
</div>

{{-- Modal Gerar Carne --}}
<div class="modal fade" id="modalCarne" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('app.boletos.carne') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-collection me-2"></i>Gerar Carne</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->id }}">{{ $cliente->nome_razao_social }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descricao <span class="text-danger">*</span></label>
                        <input type="text" name="descricao" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Valor Total <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="valor" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Parcelas <span class="text-danger">*</span></label>
                            <input type="number" name="parcelas" class="form-control" min="2" max="48" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Primeiro Vencimento <span class="text-danger">*</span></label>
                        <input type="date" name="primeiro_vencimento" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Gerar Carne
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
