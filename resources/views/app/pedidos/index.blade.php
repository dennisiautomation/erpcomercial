@extends('layouts.app')

@section('title', 'Pedidos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cart-check me-2"></i>Pedidos</h4>
    <a href="{{ route('app.pedidos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Pedido
    </a>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Buscar</label>
                <input type="text" name="busca" class="form-control" placeholder="Numero ou cliente..." value="{{ request('busca') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\StatusPedido::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('app.pedidos.index') }}" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-circle me-1"></i> Limpar
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
                    <th>Numero</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th class="text-end">Total (R$)</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pedidos as $pedido)
                    <tr>
                        <td><strong>#{{ $pedido->numero }}</strong></td>
                        <td>{{ $pedido->created_at->format('d/m/Y') }}</td>
                        <td>{{ $pedido->cliente->nome_razao_social ?? '-' }}</td>
                        <td>{{ $pedido->vendedor->name ?? '-' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($pedido->total, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $pedido->status->color() }}">
                                {{ $pedido->status->label() }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.pedidos.show', $pedido) }}" class="btn btn-outline-primary" title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($pedido->status->value === 'rascunho')
                                    <a href="{{ route('app.pedidos.edit', $pedido) }}" class="btn btn-outline-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif
                                <form method="POST" action="{{ route('app.pedidos.destroy', $pedido) }}" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhum pedido encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pedidos->hasPages())
        <div class="card-footer">
            {{ $pedidos->links() }}
        </div>
    @endif
</div>
@endsection
