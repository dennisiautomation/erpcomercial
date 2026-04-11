@extends('layouts.app')

@section('title', 'Produtos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Produtos</h4>
    <a href="{{ route('app.produtos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Produto
    </a>
</div>

{{-- Filtros --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.produtos.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="busca" class="form-control" placeholder="Descrição, código de barras, SKU..." value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoria</label>
                <select name="categoria_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ request('categoria_id') == $cat->id ? 'selected' : '' }}>{{ $cat->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="ativo" {{ request('status') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                    <option value="inativo" {{ request('status') === 'inativo' ? 'selected' : '' }}>Inativo</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.produtos.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Limpar
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
                        <th>Código</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th class="text-end">Preço Venda</th>
                        <th class="text-center">Estoque Mín.</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($produtos as $produto)
                        <tr>
                            <td><code>{{ $produto->codigo_interno }}</code></td>
                            <td>
                                <strong>{{ $produto->descricao }}</strong>
                                @if($produto->codigo_barras)
                                    <br><small class="text-muted"><i class="bi bi-upc me-1"></i>{{ $produto->codigo_barras }}</small>
                                @endif
                            </td>
                            <td>{{ $produto->categoria->nome ?? '-' }}</td>
                            <td class="text-end fw-bold">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                            <td class="text-center">{{ $produto->estoque_minimo ?? '-' }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $produto->status === 'ativo' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($produto->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('app.produtos.show', $produto) }}" class="btn btn-outline-info" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('app.produtos.edit', $produto) }}" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('app.produtos.destroy', $produto) }}" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este produto?')">
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
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Nenhum produto encontrado
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($produtos->hasPages())
        <div class="card-footer bg-white">
            {{ $produtos->links() }}
        </div>
    @endif
</div>
@endsection
