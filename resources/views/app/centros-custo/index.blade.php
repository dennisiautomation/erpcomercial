@extends('layouts.app')

@section('title', 'Centros de Custo')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-building me-2"></i>Centros de Custo</h4>
    <a href="{{ route('app.centros-custo.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Centro
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        @if($centros->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-building fs-1"></i>
                <p class="mt-2">Nenhum centro de custo cadastrado.</p>
                <a href="{{ route('app.centros-custo.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Cadastrar Primeiro
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Nome</th>
                            <th>Descricao</th>
                            <th>Status</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($centros as $centro)
                            <tr>
                                <td><code>{{ $centro->codigo }}</code></td>
                                <td>{{ $centro->nome }}</td>
                                <td class="text-muted">{{ Str::limit($centro->descricao, 50) }}</td>
                                <td>
                                    @if($centro->ativo)
                                        <span class="badge bg-success-subtle text-success">Ativo</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('app.centros-custo.edit', $centro) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('app.centros-custo.destroy', $centro) }}" class="d-inline" onsubmit="return confirm('Confirma a exclusao?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $centros->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
