@extends('layouts.app')

@section('title', 'Empresas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-building me-2"></i>Empresas</h4>
    <a href="{{ route('admin.empresas.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Empresa
    </a>
</div>

{{-- Filtros --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.empresas.index') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="search" class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Nome ou CNPJ..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\StatusEmpresa::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('admin.empresas.index') }}" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-circle me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Tabela --}}
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>CNPJ</th>
                        <th>Razao Social</th>
                        <th>Nome Fantasia</th>
                        <th>Plano</th>
                        <th>Status</th>
                        <th class="text-center">Unidades</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($empresas as $empresa)
                    <tr>
                        <td class="text-nowrap">{{ $empresa->cnpj }}</td>
                        <td class="fw-semibold">{{ $empresa->razao_social }}</td>
                        <td>{{ $empresa->nome_fantasia }}</td>
                        <td><span class="badge bg-primary">{{ ucfirst($empresa->plano) }}</span></td>
                        <td>
                            <span class="badge bg-{{ $empresa->status->color() }}">
                                {{ $empresa->status->label() }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark">{{ $empresa->unidades_count ?? $empresa->unidades->count() }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-primary btn-sm" title="Visualizar">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('admin.empresas.edit', $empresa) }}" class="btn btn-outline-secondary btn-sm" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <x-delete-form :action="route('admin.empresas.destroy', $empresa)" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhuma empresa encontrada.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($empresas->hasPages())
    <div class="card-footer bg-white">
        {{ $empresas->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
