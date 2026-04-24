@extends('layouts.app')

@section('title', 'Pedidos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-cart-check me-2"></i>Pedidos</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Pedidos</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('app.pedidos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Pedido
    </a>
</div>

{{-- Stats Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-secondary bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-pencil text-secondary fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Rascunho</div>
                        <div class="fw-bold fs-5">{{ $stats['count_rascunho'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-check-circle text-primary fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Confirmados</div>
                        <div class="fw-bold fs-5">{{ $stats['count_confirmado'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-receipt text-info fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Faturados</div>
                        <div class="fw-bold fs-5">{{ $stats['count_faturado'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-currency-dollar text-warning fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Total Pendente</div>
                        <div class="fw-bold fs-6">R$ {{ number_format($stats['total_pendente'], 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Workflow Progress --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between text-center small">
            <div class="flex-fill">
                <div class="bg-secondary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                    <span class="fw-bold text-secondary">{{ $stats['count_rascunho'] }}</span>
                </div>
                <div class="text-muted mt-1">Rascunho</div>
            </div>
            <div class="flex-fill d-flex align-items-center px-2"><div class="border-top flex-fill"></div><i class="bi bi-chevron-right text-muted mx-1"></i><div class="border-top flex-fill"></div></div>
            <div class="flex-fill">
                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                    <span class="fw-bold text-primary">{{ $stats['count_confirmado'] }}</span>
                </div>
                <div class="text-muted mt-1">Confirmado</div>
            </div>
            <div class="flex-fill d-flex align-items-center px-2"><div class="border-top flex-fill"></div><i class="bi bi-chevron-right text-muted mx-1"></i><div class="border-top flex-fill"></div></div>
            <div class="flex-fill">
                <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                    <span class="fw-bold text-info">{{ $stats['count_faturado'] }}</span>
                </div>
                <div class="text-muted mt-1">Faturado</div>
            </div>
            <div class="flex-fill d-flex align-items-center px-2"><div class="border-top flex-fill"></div><i class="bi bi-chevron-right text-muted mx-1"></i><div class="border-top flex-fill"></div></div>
            <div class="flex-fill">
                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                    <span class="fw-bold text-success">{{ $stats['count_entregue'] }}</span>
                </div>
                <div class="text-muted mt-1">Entregue</div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Buscar</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                    <input type="text" name="busca" class="form-control" placeholder="Numero ou cliente..." value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\StatusPedido::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Data Inicio</label>
                <input type="date" name="data_inicio" class="form-control form-control-sm" value="{{ request('data_inicio') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Data Fim</label>
                <input type="date" name="data_fim" class="form-control form-control-sm" value="{{ request('data_fim') }}">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.pedidos.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpar filtros">
                    <i class="bi bi-x-lg"></i>
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
                    <th class="ps-3" style="width:80px">Numero</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th class="text-center">Itens</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-center pe-3" style="width:160px">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pedidos as $pedido)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('app.pedidos.show', $pedido) }}" class="fw-bold text-decoration-none">
                                #{{ $pedido->numero }}
                            </a>
                        </td>
                        <td class="text-muted small">{{ $pedido->created_at->format('d/m/Y') }}</td>
                        <td>
                            <div class="fw-semibold">{{ Str::limit($pedido->cliente->nome_razao_social ?? '-', 30) }}</div>
                            @if($pedido->cliente?->cpf_cnpj)
                                <small class="text-muted">{{ $pedido->cliente->cpf_cnpj }}</small>
                            @endif
                        </td>
                        <td class="text-muted">{{ $pedido->vendedor->name ?? '-' }}</td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark">{{ $pedido->itens->count() }}</span>
                        </td>
                        <td class="text-end fw-bold">R$ {{ number_format($pedido->total, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-{{ $pedido->status->color() }}">
                                {{ $pedido->status->label() }}
                            </span>
                        </td>
                        <td class="text-center pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.pedidos.show', $pedido) }}" class="btn btn-outline-primary" title="Visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($pedido->status->value === 'rascunho')
                                    <a href="{{ route('app.pedidos.edit', $pedido) }}" class="btn btn-outline-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif
                                @if(in_array($pedido->status->value, ['rascunho', 'cancelado']))
                                    <form method="POST" action="{{ route('app.pedidos.destroy', $pedido) }}" class="d-inline"
                                          data-confirm="Tem certeza que deseja excluir este pedido?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Excluir">
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
                                <i class="bi bi-cart fs-1 d-block mb-2"></i>
                                Nenhum pedido encontrado.
                            </div>
                            <a href="{{ route('app.pedidos.create') }}" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-plus-lg me-1"></i> Criar Primeiro Pedido
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pedidos->hasPages())
        <div class="card-footer bg-transparent border-top">
            {{ $pedidos->links() }}
        </div>
    @endif
</div>
@endsection
