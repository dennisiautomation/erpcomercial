@extends('layouts.app')

@section('title', 'Detalhes da Categoria')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-tag me-2"></i>{{ $categoria->nome }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('app.categorias.edit', $categoria) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('app.categorias.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3">Informações</h6>
                <p class="mb-1"><strong>Nome:</strong> {{ $categoria->nome }}</p>
                <p class="mb-1"><strong>Categoria Pai:</strong> {{ $categoria->parent->nome ?? 'Nenhuma (raiz)' }}</p>
                <p class="mb-1"><strong>Descrição:</strong> {{ $categoria->descricao ?: '-' }}</p>
                <p class="mb-0">
                    <strong>Status:</strong>
                    <span class="badge bg-{{ $categoria->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($categoria->status) }}</span>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3">Subcategorias</h6>
                @if($categoria->children->isEmpty())
                    <p class="text-muted">Nenhuma subcategoria</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($categoria->children as $child)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                {{ $child->nome }}
                                <span class="badge bg-{{ $child->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($child->status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Produtos nesta Categoria</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Descrição</th>
                        <th class="text-end">Preço Venda</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categoria->produtos as $produto)
                        <tr>
                            <td>{{ $produto->descricao }}</td>
                            <td class="text-end">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $produto->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($produto->status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Nenhum produto nesta categoria</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
