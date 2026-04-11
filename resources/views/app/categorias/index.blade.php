@extends('layouts.app')

@section('title', 'Categorias')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-tags me-2"></i>Categorias</h4>
        <div class="subtitle">Gerencie as categorias de produtos</div>
    </div>
    <a href="{{ route('app.categorias.create') }}" class="btn btn-erp btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Categoria
    </a>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('app.categorias.index') }}" class="row g-3 align-items-end erp-form">
        <div class="col-md-6">
            <label class="form-label">Buscar</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="busca" class="form-control" placeholder="Nome da categoria..." value="{{ request('busca') }}">
            </div>
        </div>
        <div class="col-md-6 d-flex gap-2">
            <button type="submit" class="btn btn-erp btn-erp-primary">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
            <a href="{{ route('app.categorias.index') }}" class="btn btn-erp btn-erp-outline">
                <i class="bi bi-x-lg me-1"></i> Limpar
            </a>
        </div>
    </form>
</div>

<div class="erp-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Categoria Pai</th>
                        <th class="text-center">Produtos</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categorias as $categoria)
                        <tr>
                            <td><strong>{{ $categoria->nome }}</strong></td>
                            <td>{{ $categoria->parent->nome ?? '-' }}</td>
                            <td class="text-center"><span class="badge-status confirmado">{{ $categoria->produtos_count }}</span></td>
                            <td class="text-center">
                                <span class="badge-status {{ $categoria->status }}">
                                    {{ ucfirst($categoria->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="action-btns justify-content-center">
                                    <a href="{{ route('app.categorias.show', $categoria) }}" class="btn btn-sm btn-outline-info" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('app.categorias.edit', $categoria) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('app.categorias.destroy', $categoria) }}" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-inbox d-block"></i>
                                    <h5>Nenhuma categoria encontrada</h5>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($categorias->hasPages())
        <div class="card-body border-top">
            {{ $categorias->links() }}
        </div>
    @endif
</div>
</div>
@endsection
