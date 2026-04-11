@extends('layouts.app')

@section('title', 'Contas a Pagar')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Contas a Pagar</h4>
    <a href="{{ route('app.contas-pagar.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Conta
    </a>
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
                        <div class="fs-4 fw-bold text-success">R$ {{ number_format($pagoMes, 2, ',', '.') }}</div>
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
                    <option value="pendente" {{ request('status') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                    <option value="paga" {{ request('status') == 'paga' ? 'selected' : '' }}>Paga</option>
                    <option value="cancelada" {{ request('status') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fornecedor</label>
                <select name="fornecedor_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($fornecedores as $fornecedor)
                        <option value="{{ $fornecedor->id }}" {{ request('fornecedor_id') == $fornecedor->id ? 'selected' : '' }}>
                            {{ $fornecedor->nome_razao_social }}
                        </option>
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
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Limpar
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
                    <th>#</th>
                    <th>Fornecedor</th>
                    <th>Descricao</th>
                    <th>Categoria</th>
                    <th>Parcela</th>
                    <th>Vencimento</th>
                    <th class="text-end">Valor</th>
                    <th class="text-center">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($contas as $conta)
                <tr class="{{ $conta->status === 'pendente' && $conta->vencimento->isPast() ? 'table-danger' : '' }}">
                    <td>{{ $conta->id }}</td>
                    <td>{{ $conta->fornecedor->nome_razao_social ?? '-' }}</td>
                    <td>{{ Str::limit($conta->descricao, 35) }}</td>
                    <td>{{ $conta->categoria ?? '-' }}</td>
                    <td>{{ $conta->parcela }}/{{ $conta->total_parcelas }}</td>
                    <td>{{ $conta->vencimento->format('d/m/Y') }}</td>
                    <td class="text-end">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                    <td class="text-center">
                        @php
                            $statusBadge = match($conta->status) {
                                'pendente' => $conta->vencimento->isPast() ? 'bg-danger' : 'bg-warning text-dark',
                                'paga' => 'bg-success',
                                'cancelada' => 'bg-secondary',
                                default => 'bg-secondary',
                            };
                            $statusLabel = $conta->status === 'pendente' && $conta->vencimento->isPast() ? 'Vencida' : ucfirst($conta->status);
                        @endphp
                        <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('app.contas-pagar.show', $conta) }}" class="btn btn-sm btn-outline-secondary" title="Ver">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if($conta->status === 'pendente')
                            <form method="POST" action="{{ route('app.contas-pagar.baixar', $conta) }}"
                                onsubmit="return confirm('Confirma o pagamento desta conta?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Baixar">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('app.contas-pagar.destroy', $conta) }}"
                                onsubmit="return confirm('Confirma a exclusao?')">
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
                    <td colspan="9" class="text-center text-muted py-4">Nenhuma conta encontrada.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($contas->hasPages())
    <div class="card-footer">
        {{ $contas->links() }}
    </div>
    @endif
</div>
@endsection
