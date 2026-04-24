@extends('layouts.app')

@section('title', 'Contas a Receber')

@section('content')
<x-erp.page-header title="Contas a Receber" subtitle="Gerencie recebiveis, parcelas e baixas" icon="cash-stack">
    <a href="{{ route('app.export.contas-receber') }}" class="btn btn-erp-outline"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar</a>
    <a href="{{ route('app.contas-receber.create') }}" class="btn btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Conta
    </a>
</x-erp.page-header>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <x-erp.stat-card icon="clock-history" color="warning" :value="number_format($totalPendente, 2, ',', '.')" label="Total Pendente" prefix="R$ " />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="exclamation-triangle" color="danger" :value="number_format($totalVencido, 2, ',', '.')" label="Total Vencido" prefix="R$ " />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="check-circle" color="success" :value="number_format($recebidoMes, 2, ',', '.')" label="Recebido no Mes" prefix="R$ " />
    </div>
</div>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.contas-receber.index')">
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
    <div class="col-auto">
        <a href="{{ route('app.contas-receber.index') }}" class="btn btn-erp-outline">
            <i class="bi bi-x-lg me-1"></i> Limpar
        </a>
    </div>
</x-erp.filter-bar>

{{-- Table --}}
<x-erp.data-table>
    <thead>
        <tr>
            <th>#</th>
            <th>Cliente</th>
            <th>Descricao</th>
            <th class="text-center">Parcela</th>
            <th>Vencimento</th>
            <th class="text-end">Valor</th>
            <th class="text-center">Status</th>
            <th class="text-center">Acoes</th>
        </tr>
    </thead>
    <tbody>
        @forelse($contas as $conta)
        @php
            $isOverdue = $conta->status === 'pendente' && $conta->vencimento->isPast();
            $daysOverdue = $isOverdue ? (int) $conta->vencimento->diffInDays(now()) : 0;
        @endphp
        <tr class="{{ $isOverdue ? 'border-start border-danger border-3' : '' }}">
            <td class="text-muted small">#{{ $conta->id }}</td>
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
                    $statusValue = $isOverdue ? 'vencida' : $conta->status;
                @endphp
                <x-erp.status-badge :status="$statusValue" />
            </td>
            <td class="text-center">
                <div class="action-btns">
                    <a href="{{ route('app.contas-receber.show', $conta) }}" class="btn btn-sm btn-erp-outline" title="Detalhes">
                        <i class="bi bi-eye"></i>
                    </a>
                    @if($conta->status === 'pendente')
                    <form method="POST" action="{{ route('app.contas-receber.baixar', $conta) }}"
                        data-confirm="Confirma o recebimento de R$ {{ number_format($conta->valor, 2, ',', '.') }}?">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success" title="Dar baixa">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('app.contas-receber.destroy', $conta) }}"
                        data-confirm="Confirma a exclusao desta conta?">
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
            <td colspan="8">
                <x-erp.empty-state icon="cash-stack" title="Nenhuma conta encontrada" />
            </td>
        </tr>
        @endforelse
    </tbody>
    <x-slot name="pagination">
        @if($contas->hasPages())
            {{ $contas->links() }}
        @endif
    </x-slot>
</x-erp.data-table>
@endsection
