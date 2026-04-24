@extends('layouts.app')

@section('title', 'Comissoes')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-percent me-2"></i>Comissoes</h4>
        <div class="subtitle">Gerencie comissoes dos vendedores</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('app.comissoes.relatorio') }}" class="btn btn-erp btn-erp-outline">
            <i class="bi bi-file-earmark-bar-graph me-1"></i> Relatorio
        </a>
        <a href="{{ route('app.comissoes.configurar') }}" class="btn btn-erp btn-erp-outline">
            <i class="bi bi-gear me-1"></i> Configurar
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Pendentes</div>
                    <div class="stat-value" style="color: var(--warning);">R$ {{ number_format($totalPendente, 2, ',', '.') }}</div>
                </div>
                <div class="stat-icon warning">
                    <i class="bi bi-clock-history"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Pagas no Mes</div>
                    <div class="stat-value" style="color: var(--success);">R$ {{ number_format($pagoMes, 2, ',', '.') }}</div>
                </div>
                <div class="stat-icon success">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total no Mes</div>
                    <div class="stat-value" style="color: var(--primary);">R$ {{ number_format($totalMes, 2, ',', '.') }}</div>
                </div>
                <div class="stat-icon primary">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('app.comissoes.index') }}" class="row g-3 align-items-end erp-form">
        <div class="col-md-3">
            <label for="vendedor_id" class="form-label">Vendedor</label>
            <select name="vendedor_id" id="vendedor_id" class="form-select">
                <option value="">Todos</option>
                @foreach($vendedores as $vendedor)
                    <option value="{{ $vendedor->id }}" {{ request('vendedor_id') == $vendedor->id ? 'selected' : '' }}>
                        {{ $vendedor->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Todos</option>
                <option value="pendente" {{ request('status') === 'pendente' ? 'selected' : '' }}>Pendente</option>
                <option value="paga" {{ request('status') === 'paga' ? 'selected' : '' }}>Paga</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="data_inicio" class="form-label">De</label>
            <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
        </div>
        <div class="col-md-2">
            <label for="data_fim" class="form-label">Ate</label>
            <input type="date" name="data_fim" id="data_fim" class="form-control" value="{{ request('data_fim') }}">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-erp btn-erp-primary w-100">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
        </div>
    </form>
</div>

{{-- Table with bulk pay --}}
<form method="POST" action="{{ route('app.comissoes.pagar') }}" id="formPagar">
    @csrf
    <div class="erp-card">
        <div class="card-header">
            <span>Lista de Comissoes</span>
            <div class="ms-auto">
                <button type="submit" class="btn btn-erp btn-erp-success btn-sm" data-confirm="Confirma o pagamento das comissoes selecionadas?">
                    <i class="bi bi-check2-all me-1"></i> Pagar Selecionadas
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAll" onclick="toggleAll(this)">
                            </th>
                            <th>Vendedor</th>
                            <th>Venda</th>
                            <th class="text-end">Valor Venda</th>
                            <th class="text-end">%</th>
                            <th class="text-end">Comissao</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($comissoes as $comissao)
                            <tr>
                                <td class="ps-3">
                                    @if($comissao->status === 'pendente')
                                        <input type="checkbox" name="comissao_ids[]" value="{{ $comissao->id }}" class="form-check-input comissao-check">
                                    @endif
                                </td>
                                <td>{{ $comissao->vendedor->name ?? '-' }}</td>
                                <td>
                                    @if($comissao->venda)
                                        <a href="{{ route('app.vendas.show', $comissao->venda_id) }}" class="text-decoration-none">
                                            #{{ $comissao->venda_id }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end">R$ {{ number_format($comissao->valor_venda, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($comissao->percentual, 1, ',', '.') }}%</td>
                                <td class="text-end fw-semibold">R$ {{ number_format($comissao->valor_comissao, 2, ',', '.') }}</td>
                                <td>
                                    <span class="badge-status {{ $comissao->status }}">{{ ucfirst($comissao->status) }}</span>
                                </td>
                                <td>{{ $comissao->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="bi bi-percent d-block"></i>
                                        <h5>Nenhuma comissao encontrada</h5>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($comissoes->hasPages())
        <div class="mt-3">
            {{ $comissoes->links() }}
        </div>
    @endif
</form>
</div>

@push('scripts')
<script>
    function toggleAll(el) {
        document.querySelectorAll('.comissao-check').forEach(cb => cb.checked = el.checked);
    }
</script>
@endpush
@endsection
