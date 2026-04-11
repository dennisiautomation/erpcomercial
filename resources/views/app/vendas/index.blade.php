@extends('layouts.app')

@section('title', 'Vendas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bag-check me-2"></i>Vendas</h4>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" name="busca" class="form-control" placeholder="Numero ou cliente..." value="{{ request('busca') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\StatusVenda::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="pdv" {{ request('tipo') === 'pdv' ? 'selected' : '' }}>PDV</option>
                    <option value="pedido" {{ request('tipo') === 'pedido' ? 'selected' : '' }}>Pedido</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Inicio</label>
                <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i>
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
                    <th>Numero</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th class="text-center">Tipo</th>
                    <th class="text-end">Total (R$)</th>
                    <th>Forma Pgto</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendas as $venda)
                    <tr>
                        <td><strong>#{{ $venda->numero }}</strong></td>
                        <td>{{ $venda->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $venda->cliente->nome_razao_social ?? 'Consumidor Final' }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $venda->tipo === 'pdv' ? 'info' : 'primary' }}">
                                {{ strtoupper($venda->tipo ?? 'N/A') }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold">{{ number_format($venda->total, 2, ',', '.') }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $venda->forma_pagamento ?? '-')) }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $venda->status->color() }}">
                                {{ $venda->status->label() }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.vendas.show', $venda) }}" class="btn btn-outline-primary" title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($venda->status->value === 'concluida')
                                    <form method="POST" action="{{ route('app.vendas.destroy', $venda) }}" class="d-inline" onsubmit="return confirm('Cancelar esta venda? O estoque sera revertido.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Cancelar">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Nenhuma venda encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($vendas->hasPages())
        <div class="card-footer">
            {{ $vendas->links() }}
        </div>
    @endif
</div>
@endsection
