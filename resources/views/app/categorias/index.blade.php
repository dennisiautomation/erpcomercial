@extends('layouts.app')

@section('title', 'Categorias')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-tags me-2"></i>Categorias</h4>
    <a href="{{ route('app.categorias.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Categoria
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.categorias.index') }}" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="busca" class="form-control" placeholder="Nome da categoria..." value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.categorias.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
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
                            <td class="text-center"><span class="badge bg-info">{{ $categoria->produtos_count }}</span></td>
                            <td class="text-center">
                                <span class="badge bg-{{ $categoria->status === 'ativo' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($categoria->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('app.categorias.show', $categoria) }}" class="btn btn-outline-info" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('app.categorias.edit', $categoria) }}" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('app.categorias.destroy', $categoria) }}" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?')">
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
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Nenhuma categoria encontrada
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($categorias->hasPages())
        <div class="card-footer bg-white">
            {{ $categorias->links() }}
        </div>
    @endif
</div>
@endsection
