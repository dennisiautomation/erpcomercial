@extends('layouts.app')

@section('title', 'Centros de Custo')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-building me-2"></i>Centros de Custo</h4>
        <div class="subtitle">Gerencie os centros de custo da empresa</div>
    </div>
    <a href="{{ route('app.centros-custo.create') }}" class="btn btn-erp btn-erp-primary">
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

<div class="erp-card">
    <div class="card-body">
        @if($centros->isEmpty())
            <div class="empty-state">
                <i class="bi bi-building d-block"></i>
                <h5>Nenhum centro de custo cadastrado</h5>
                <p>Crie o primeiro centro de custo.</p>
                <a href="{{ route('app.centros-custo.create') }}" class="btn btn-erp btn-erp-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Cadastrar Primeiro
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="erp-table">
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
                                        <span class="badge-status ativo">Ativo</span>
                                    @else
                                        <span class="badge-status inativo">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="action-btns justify-content-end">
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
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($centros->hasPages())
                <div class="mt-3">
                    {{ $centros->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
</div>
@endsection
