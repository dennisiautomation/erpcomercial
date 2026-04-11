@extends('layouts.app')

@section('title', 'Servicos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-tools me-2"></i>Servicos</h4>
    <a href="{{ route('app.servicos.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Novo Servico
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total</p>
                        <h3 class="fw-bold mb-0">{{ $totalAtivos + $totalInativos }}</h3>
                    </div>
                    <div class="rounded-3 bg-primary bg-opacity-10 p-2">
                        <i class="bi bi-tools fs-4 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Ativos</p>
                        <h3 class="fw-bold mb-0 text-success">{{ $totalAtivos }}</h3>
                    </div>
                    <div class="rounded-3 bg-success bg-opacity-10 p-2">
                        <i class="bi bi-check-circle fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Inativos</p>
                        <h3 class="fw-bold mb-0 text-secondary">{{ $totalInativos }}</h3>
                    </div>
                    <div class="rounded-3 bg-secondary bg-opacity-10 p-2">
                        <i class="bi bi-pause-circle fs-4 text-secondary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('app.servicos.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="busca" class="form-control" placeholder="Buscar por descricao ou codigo..." value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="ativo" {{ request('status') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                    <option value="inativo" {{ request('status') === 'inativo' ? 'selected' : '' }}>Inativo</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-x-lg me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Codigo</th>
                    <th>Descricao</th>
                    <th class="text-end">Valor Padrao</th>
                    <th>Cod. Municipal</th>
                    <th>ISS (%)</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 130px;">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($servicos as $servico)
                    <tr>
                        <td><code>{{ $servico->codigo ?: '-' }}</code></td>
                        <td><strong>{{ $servico->descricao }}</strong></td>
                        <td class="text-end fw-semibold">R$ {{ number_format($servico->valor_padrao, 2, ',', '.') }}</td>
                        <td class="text-muted">{{ $servico->codigo_servico_municipal ?: '-' }}</td>
                        <td>{{ $servico->iss_aliquota ? number_format($servico->iss_aliquota, 2, ',', '.') . '%' : '-' }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $servico->status === 'ativo' ? 'success' : 'secondary' }}">
                                {{ ucfirst($servico->status) }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.servicos.edit', $servico) }}" class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('app.servicos.destroy', $servico) }}" class="d-inline"
                                      onsubmit="return confirm('Tem certeza que deseja excluir este servico?')">
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
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-tools fs-1 d-block mb-2 opacity-50"></i>
                            Nenhum servico encontrado
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($servicos->hasPages())
        <div class="card-footer bg-white">{{ $servicos->links() }}</div>
    @endif
</div>
@endsection
