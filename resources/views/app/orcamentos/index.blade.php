@extends('layouts.app')

@section('title', 'Orcamentos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Orcamentos</h4>
    <a href="{{ route('app.orcamentos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Orcamento
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
                    @foreach(\App\Enums\StatusOrcamento::cases() as $status)
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
                <a href="{{ route('app.orcamentos.index') }}" class="btn btn-outline-secondary w-100">
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
                    <th>Validade</th>
                    <th class="text-center">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orcamentos as $orcamento)
                    <tr>
                        <td><strong>#{{ $orcamento->numero }}</strong></td>
                        <td>{{ $orcamento->created_at->format('d/m/Y') }}</td>
                        <td>{{ $orcamento->cliente->nome_razao_social ?? '-' }}</td>
                        <td>{{ $orcamento->vendedor->name ?? '-' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($orcamento->total, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $orcamento->status->color() }}">
                                {{ $orcamento->status->label() }}
                            </span>
                        </td>
                        <td>
                            @if($orcamento->validade_ate)
                                <span class="{{ $orcamento->validade_ate->isPast() ? 'text-danger' : '' }}">
                                    {{ $orcamento->validade_ate->format('d/m/Y') }}
                                </span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.orcamentos.show', $orcamento) }}" class="btn btn-outline-primary" title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('app.orcamentos.edit', $orcamento) }}" class="btn btn-outline-warning" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('app.orcamentos.destroy', $orcamento) }}" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir?')">
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
                        <td colspan="8" class="text-center text-muted py-4">Nenhum orcamento encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orcamentos->hasPages())
        <div class="card-footer">
            {{ $orcamentos->links() }}
        </div>
    @endif
</div>
@endsection
