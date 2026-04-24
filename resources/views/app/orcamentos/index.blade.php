@extends('layouts.app')

@section('title', 'Orcamentos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-file-earmark-text me-2"></i>Orcamentos</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Orcamentos</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('app.orcamentos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Orcamento
    </a>
</div>

{{-- Stats Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-clock-history text-warning fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Em Aberto</div>
                        <div class="fw-bold fs-5">{{ $stats['count_em_aberto'] }}</div>
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
                        <div class="bg-success bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-check-circle text-success fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Aprovados</div>
                        <div class="fw-bold fs-5">{{ $stats['count_aprovados'] }}</div>
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
                            <i class="bi bi-arrow-repeat text-info fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Convertidos</div>
                        <div class="fw-bold fs-5">{{ $stats['count_convertidos'] }}</div>
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
                        <div class="text-muted small">Total em Aberto</div>
                        <div class="fw-bold fs-5">R$ {{ number_format($stats['total_em_aberto'], 2, ',', '.') }}</div>
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
            <div class="col-md-4">
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
                    @foreach(\App\Enums\StatusOrcamento::cases() as $status)
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
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.orcamentos.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpar filtros">
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
                    <th>Validade</th>
                    <th class="text-center">Itens</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-center pe-3" style="width:140px">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orcamentos as $orcamento)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('app.orcamentos.show', $orcamento) }}" class="fw-bold text-decoration-none">
                                #{{ $orcamento->numero }}
                            </a>
                        </td>
                        <td class="text-muted small">{{ $orcamento->created_at->format('d/m/Y') }}</td>
                        <td>
                            <div class="fw-semibold">{{ Str::limit($orcamento->cliente->nome_razao_social ?? '-', 30) }}</div>
                            @if($orcamento->cliente?->cpf_cnpj)
                                <small class="text-muted">{{ $orcamento->cliente->cpf_cnpj }}</small>
                            @endif
                        </td>
                        <td class="text-muted">{{ $orcamento->vendedor->name ?? '-' }}</td>
                        <td>
                            @if($orcamento->validade_ate)
                                @if($orcamento->validade_ate->isPast() && $orcamento->status === \App\Enums\StatusOrcamento::EmAberto)
                                    <span class="text-danger fw-semibold">
                                        <i class="bi bi-exclamation-triangle me-1"></i>{{ $orcamento->validade_ate->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="text-muted small">{{ $orcamento->validade_ate->format('d/m/Y') }}</span>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark">{{ $orcamento->itens->count() }}</span>
                        </td>
                        <td class="text-end fw-bold">R$ {{ number_format($orcamento->total, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-{{ $orcamento->status->color() }}{{ in_array($orcamento->status->color(), ['warning']) ? ' text-dark' : '' }}">
                                {{ $orcamento->status->label() }}
                            </span>
                        </td>
                        <td class="text-center pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.orcamentos.show', $orcamento) }}" class="btn btn-outline-primary" title="Visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($orcamento->status->value !== 'convertido')
                                    <a href="{{ route('app.orcamentos.edit', $orcamento) }}" class="btn btn-outline-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('app.orcamentos.destroy', $orcamento) }}" class="d-inline"
                                          data-confirm="Tem certeza que deseja excluir este orcamento?">
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
                        <td colspan="9" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-file-earmark-text fs-1 d-block mb-2"></i>
                                Nenhum orcamento encontrado.
                            </div>
                            <a href="{{ route('app.orcamentos.create') }}" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-plus-lg me-1"></i> Criar Primeiro Orcamento
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orcamentos->hasPages())
        <div class="card-footer bg-transparent border-top">
            {{ $orcamentos->links() }}
        </div>
    @endif
</div>
@endsection
