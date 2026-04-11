@extends('layouts.app')

@section('title', 'Contas a Receber')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-cash-stack me-2"></i>Contas a Receber</h4>
        <p class="text-muted mb-0 small">Gerencie recebiveis, parcelas e baixas</p>
    </div>
    <a href="{{ route('app.contas-receber.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Conta
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-warning bg-opacity-10 p-3 me-3">
                        <i class="bi bi-clock-history fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Pendente</div>
                        <div class="fs-4 fw-bold text-warning">R$ {{ number_format($totalPendente, 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 {{ $totalVencido > 0 ? 'border-start border-danger border-3' : '' }}">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-danger bg-opacity-10 p-3 me-3">
                        <i class="bi bi-exclamation-triangle fs-4 text-danger"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Vencido</div>
                        <div class="fs-4 fw-bold text-danger">R$ {{ number_format($totalVencido, 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-check-circle fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Recebido no Mes</div>
                        <div class="fs-4 fw-bold text-success">R$ {{ number_format($recebidoMes, 2, ',', '.') }}</div>
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
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendente" {{ request('status') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                    <option value="paga" {{ request('status') == 'paga' ? 'selected' : '' }}>Paga</option>
                    <option value="vencida" {{ request('status') == 'vencida' ? 'selected' : '' }}>Vencida</option>
                    <option value="cancelada" {{ request('status') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                    <option value="renegociada" {{ request('status') == 'renegociada' ? 'selected' : '' }}>Renegociada</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-muted">Cliente</label>
                <select name="cliente_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ request('cliente_id') == $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nome_razao_social }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted">Vencimento De</label>
                <input type="date" name="vencimento_inicio" class="form-control" value="{{ request('vencimento_inicio') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted">Vencimento Ate</label>
                <input type="date" name="vencimento_fim" class="form-control" value="{{ request('vencimento_fim') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.contas-receber.index') }}" class="btn btn-outline-secondary">
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
                    <th>Cliente</th>
                    <th>Descricao</th>
                    <th class="text-center">Parcela</th>
                    <th>Vencimento</th>
                    <th class="text-end">Valor</th>
                    <th class="text-center">Status</th>
                    <th class="text-center pe-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contas as $conta)
                @php
                    $isOverdue = $conta->status === 'pendente' && $conta->vencimento->isPast();
                    $daysOverdue = $isOverdue ? (int) $conta->vencimento->diffInDays(now()) : 0;
                @endphp
                <tr class="{{ $isOverdue ? 'border-start border-danger border-3' : '' }}">
                    <td class="ps-3 text-muted small">#{{ $conta->id }}</td>
                    <td>
                        <div class="fw-semibold">{{ $conta->cliente->nome_razao_social ?? '-' }}</div>
                    </td>
                    <td>
                        <div>{{ Str::limit($conta->descricao, 40) }}</div>
                        @if($conta->forma_pagamento)
                            <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $conta->forma_pagamento)) }}</small>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border rounded-pill">{{ $conta->parcela }}/{{ $conta->total_parcelas }}</span>
                    </td>
                    <td>
                        <div class="fw-semibold {{ $isOverdue ? 'text-danger' : '' }}">{{ $conta->vencimento->format('d/m/Y') }}</div>
                        @if($isOverdue)
                            <small class="text-danger">{{ $daysOverdue }} {{ $daysOverdue === 1 ? 'dia' : 'dias' }} em atraso</small>
                        @elseif($conta->status === 'pendente')
                            <small class="text-muted">{{ $conta->vencimento->diffForHumans() }}</small>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="fw-bold">R$ {{ number_format($conta->valor, 2, ',', '.') }}</div>
                        @if($conta->valor_pago && $conta->valor_pago > 0)
                            <small class="text-success">Pago: R$ {{ number_format($conta->valor_pago, 2, ',', '.') }}</small>
                        @endif
                    </td>
                    <td class="text-center">
                        @php
                            $statusBadge = match($conta->status) {
                                'pendente' => $isOverdue ? 'bg-danger' : 'bg-warning text-dark',
                                'paga' => 'bg-success',
                                'vencida' => 'bg-danger',
                                'cancelada' => 'bg-secondary',
                                'renegociada' => 'bg-info text-dark',
                                default => 'bg-secondary',
                            };
                            $statusLabel = $isOverdue ? 'Vencida' : ucfirst($conta->status);
                            $statusIcon = match(true) {
                                $isOverdue => 'bi-exclamation-circle',
                                $conta->status === 'pendente' => 'bi-clock',
                                $conta->status === 'paga' => 'bi-check-circle',
                                $conta->status === 'cancelada' => 'bi-x-circle',
                                default => 'bi-circle',
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }} rounded-pill px-3">
                            <i class="bi {{ $statusIcon }} me-1"></i>{{ $statusLabel }}
                        </span>
                    </td>
                    <td class="text-center pe-3">
                        <div class="d-flex gap-1 justify-content-center">
                            <a href="{{ route('app.contas-receber.show', $conta) }}" class="btn btn-sm btn-outline-primary" title="Detalhes">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if($conta->status === 'pendente')
                            <form method="POST" action="{{ route('app.contas-receber.baixar', $conta) }}"
                                onsubmit="return confirm('Confirma o recebimento de R$ {{ number_format($conta->valor, 2, ',', '.') }}?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success" title="Dar baixa">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('app.contas-receber.destroy', $conta) }}"
                                onsubmit="return confirm('Confirma a exclusao desta conta?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-cash-stack fs-1 d-block mb-2 opacity-25"></i>
                            Nenhuma conta encontrada.
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($contas->hasPages())
    <div class="card-footer bg-white border-top">
        {{ $contas->links() }}
    </div>
    @endif
</div>
@endsection
