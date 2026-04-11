@extends('layouts.app')

@section('title', 'Unidades')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-shop me-2"></i>Unidades
        <small class="text-muted fs-6">- {{ $empresa->razao_social }}</small>
    </h4>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.empresas.unidades.create', $empresa) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nova Unidade
        </a>
        <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nome</th>
                        <th>CNPJ</th>
                        <th>Cidade / UF</th>
                        <th>Status</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($unidades as $unidade)
                    <tr>
                        <td class="fw-semibold">{{ $unidade->nome }}</td>
                        <td>{{ $unidade->cnpj ?? '-' }}</td>
                        <td>{{ $unidade->cidade ?? '-' }} / {{ $unidade->uf ?? '-' }}</td>
                        <td>
                            <span class="badge bg-{{ $unidade->status === 'ativo' ? 'success' : 'secondary' }}">
                                {{ ucfirst($unidade->status ?? 'ativo') }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.unidades.edit', $unidade) }}" class="btn btn-outline-secondary btn-sm" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <x-delete-form :action="route('admin.unidades.destroy', $unidade)" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Nenhuma unidade cadastrada para esta empresa.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(isset($unidades) && $unidades instanceof \Illuminate\Pagination\LengthAwarePaginator && $unidades->hasPages())
    <div class="card-footer bg-white">
        {{ $unidades->links() }}
    </div>
    @endif
</div>
@endsection
