@extends('layouts.app')

@section('title', 'Vendas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-bag-check me-2"></i>Vendas</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Vendas</li>
            </ol>
        </nav>
    </div>
</div>

{{-- Stats Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-bag-check text-success fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Vendas Concluidas</div>
                        <div class="fw-bold fs-5">{{ $stats['count_concluidas'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-currency-dollar text-primary fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Total Concluidas</div>
                        <div class="fw-bold fs-6">R$ {{ number_format($stats['total_concluidas'], 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-calendar-check text-info fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Vendas Hoje</div>
                        <div class="fw-bold fs-6">R$ {{ number_format($stats['total_hoje'], 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-danger bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-x-circle text-danger fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Canceladas</div>
                        <div class="fw-bold fs-5">{{ $stats['count_canceladas'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-3">
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
                    @foreach(\App\Enums\StatusVenda::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="pdv" {{ request('tipo') === 'pdv' ? 'selected' : '' }}>PDV</option>
                    <option value="balcao" {{ request('tipo') === 'balcao' ? 'selected' : '' }}>Balcao</option>
                    <option value="online" {{ request('tipo') === 'online' ? 'selected' : '' }}>Online</option>
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
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill" title="Filtrar">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="{{ route('app.vendas.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpar">
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
                    <th>Data/Hora</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th class="text-center">Tipo</th>
                    <th>Forma Pgto</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-center pe-3" style="width:120px">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendas as $venda)
                    <tr class="{{ $venda->status->value === 'cancelada' ? 'table-light text-muted' : '' }}">
                        <td class="ps-3">
                            <a href="{{ route('app.vendas.show', $venda) }}" class="fw-bold text-decoration-none">
                                #{{ $venda->numero }}
                            </a>
                        </td>
                        <td class="small">{{ $venda->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <div class="fw-semibold">{{ Str::limit($venda->cliente->nome_razao_social ?? 'Consumidor Final', 28) }}</div>
                        </td>
                        <td class="text-muted small">{{ $venda->vendedor->name ?? '-' }}</td>
                        <td class="text-center">
                            @php
                                $tipoColors = ['pdv' => 'info', 'balcao' => 'primary', 'online' => 'success'];
                                $tipoBg = $tipoColors[$venda->tipo] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $tipoBg }}">{{ strtoupper($venda->tipo ?? 'N/A') }}</span>
                        </td>
                        <td>
                            @php
                                $formaIcons = [
                                    'dinheiro' => 'bi-cash',
                                    'cartao_credito' => 'bi-credit-card',
                                    'cartao_debito' => 'bi-credit-card-2-front',
                                    'pix' => 'bi-qr-code',
                                    'boleto' => 'bi-upc',
                                    'split' => 'bi-diagram-2',
                                ];
                                $formaIcon = $formaIcons[$venda->forma_pagamento] ?? 'bi-wallet2';
                            @endphp
                            <i class="bi {{ $formaIcon }} me-1 text-muted"></i>
                            <span class="small">{{ ucfirst(str_replace('_', ' ', $venda->forma_pagamento ?? '-')) }}</span>
                        </td>
                        <td class="text-end fw-bold">
                            @if($venda->status->value === 'cancelada')
                                <del class="text-muted">R$ {{ number_format($venda->total, 2, ',', '.') }}</del>
                            @else
                                R$ {{ number_format($venda->total, 2, ',', '.') }}
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-{{ $venda->status->color() }}">
                                {{ $venda->status->label() }}
                            </span>
                        </td>
                        <td class="text-center pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.vendas.show', $venda) }}" class="btn btn-outline-primary" title="Visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($venda->status->value === 'concluida')
                                    <form method="POST" action="{{ route('app.vendas.destroy', $venda) }}" class="d-inline"
                                          onsubmit="return confirm('Cancelar esta venda? O estoque sera revertido automaticamente.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Cancelar venda">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-bag fs-1 d-block mb-2"></i>
                                Nenhuma venda encontrada.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($vendas->hasPages())
        <div class="card-footer bg-transparent border-top">
            {{ $vendas->links() }}
        </div>
    @endif
</div>
@endsection
