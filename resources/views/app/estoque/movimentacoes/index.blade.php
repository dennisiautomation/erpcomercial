@extends('layouts.app')

@section('title', 'Movimentacoes de Estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-arrow-left-right me-2"></i>Movimentacoes de Estoque</h4>
        <p class="text-muted mb-0 small">Controle de entradas, saidas, ajustes e transferencias</p>
    </div>
    <a href="{{ route('app.movimentacoes.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Movimentacao
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-box-arrow-in-down fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Entradas (mes)</div>
                        <div class="fs-4 fw-bold text-success">{{ $totalEntradas }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-danger bg-opacity-10 p-3 me-3">
                        <i class="bi bi-box-arrow-up fs-4 text-danger"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Saidas (mes)</div>
                        <div class="fs-4 fw-bold text-danger">{{ $totalSaidas }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-warning bg-opacity-10 p-3 me-3">
                        <i class="bi bi-pencil-square fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Ajustes (mes)</div>
                        <div class="fs-4 fw-bold text-warning">{{ $totalAjustes }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-3 bg-primary bg-opacity-10 p-3 me-3">
                        <i class="bi bi-arrow-left-right fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Transferencias (mes)</div>
                        <div class="fs-4 fw-bold text-primary">{{ $totalTransferencias }}</div>
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
            <div class="col-md-3">
                <label class="form-label fw-semibold small text-muted">Produto</label>
                <select name="produto_id" class="form-select">
                    <option value="">Todos os produtos</option>
                    @foreach($produtos as $produto)
                        <option value="{{ $produto->id }}" {{ request('produto_id') == $produto->id ? 'selected' : '' }}>
                            {{ $produto->descricao }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="entrada" {{ request('tipo') == 'entrada' ? 'selected' : '' }}>Entrada</option>
                    <option value="saida" {{ request('tipo') == 'saida' ? 'selected' : '' }}>Saida</option>
                    <option value="ajuste" {{ request('tipo') == 'ajuste' ? 'selected' : '' }}>Ajuste</option>
                    <option value="perda" {{ request('tipo') == 'perda' ? 'selected' : '' }}>Perda</option>
                    <option value="bonificacao" {{ request('tipo') == 'bonificacao' ? 'selected' : '' }}>Bonificacao</option>
                    <option value="transferencia" {{ request('tipo') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                    <option value="devolucao" {{ request('tipo') == 'devolucao' ? 'selected' : '' }}>Devolucao</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted">De</label>
                <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small text-muted">Ate</label>
                <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-outline-secondary">
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
                    <th class="ps-3">Data</th>
                    <th>Produto</th>
                    <th>Tipo</th>
                    <th class="text-end">Quantidade</th>
                    <th class="text-center">Estoque</th>
                    <th>Usuario</th>
                    <th>Origem</th>
                    <th class="text-center pe-3">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movimentacoes as $mov)
                <tr>
                    <td class="ps-3">
                        <div class="fw-semibold">{{ $mov->created_at->format('d/m/Y') }}</div>
                        <small class="text-muted">{{ $mov->created_at->format('H:i') }}</small>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $mov->produto->descricao ?? '-' }}</div>
                        @if($mov->unidade)
                            <small class="text-muted">{{ $mov->unidade->nome }}</small>
                        @endif
                    </td>
                    <td>
                        @php
                            $badgeClass = match($mov->tipo->value) {
                                'entrada', 'devolucao' => 'bg-success',
                                'saida', 'perda' => 'bg-danger',
                                'ajuste' => 'bg-warning text-dark',
                                'bonificacao' => 'bg-info text-dark',
                                'transferencia' => 'bg-primary',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }} rounded-pill px-3">{{ $mov->tipo->label() }}</span>
                    </td>
                    <td class="text-end">
                        @if(in_array($mov->tipo->value, ['entrada', 'devolucao', 'ajuste']))
                            <span class="text-success fw-bold">
                                <i class="bi bi-arrow-up-short"></i>{{ number_format($mov->quantidade, 3, ',', '.') }}
                            </span>
                        @else
                            <span class="text-danger fw-bold">
                                <i class="bi bi-arrow-down-short"></i>{{ number_format($mov->quantidade, 3, ',', '.') }}
                            </span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="text-muted">{{ number_format($mov->quantidade_anterior, 3, ',', '.') }}</span>
                        <i class="bi bi-arrow-right mx-1 text-muted small"></i>
                        <span class="fw-semibold">{{ number_format($mov->quantidade_posterior, 3, ',', '.') }}</span>
                    </td>
                    <td>
                        <small>{{ $mov->user->name ?? '-' }}</small>
                    </td>
                    <td>
                        @if($mov->origem_tipo)
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-link-45deg me-1"></i>{{ class_basename($mov->origem_tipo) }} #{{ $mov->origem_id }}
                            </span>
                        @else
                            <span class="badge bg-light text-muted border">Manual</span>
                        @endif
                    </td>
                    <td class="text-center pe-3">
                        <a href="{{ route('app.movimentacoes.show', $mov) }}" class="btn btn-sm btn-outline-primary" title="Detalhes">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                            Nenhuma movimentacao encontrada.
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($movimentacoes->hasPages())
    <div class="card-footer bg-white border-top">
        {{ $movimentacoes->links() }}
    </div>
    @endif
</div>
@endsection
