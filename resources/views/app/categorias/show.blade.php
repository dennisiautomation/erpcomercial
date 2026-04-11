@extends('layouts.app')

@section('title', 'Detalhes da Categoria')

@section('content')
<x-erp.page-header title="{{ $categoria->nome }}" icon="tag">
    <a href="{{ route('app.categorias.edit', $categoria) }}" class="btn btn-erp-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    <a href="{{ route('app.categorias.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</x-erp.page-header>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <x-erp.card title="Informacoes" icon="info-circle">
            <p class="mb-1"><strong>Nome:</strong> {{ $categoria->nome }}</p>
            <p class="mb-1"><strong>Categoria Pai:</strong> {{ $categoria->parent->nome ?? 'Nenhuma (raiz)' }}</p>
            <p class="mb-1"><strong>Descricao:</strong> {{ $categoria->descricao ?: '-' }}</p>
            <p class="mb-0">
                <strong>Status:</strong>
                <x-erp.status-badge :status="$categoria->status" />
            </p>
        </x-erp.card>
    </div>
    <div class="col-md-6">
        <x-erp.card title="Subcategorias" icon="diagram-3">
            @if($categoria->children->isEmpty())
                <p class="text-muted">Nenhuma subcategoria</p>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($categoria->children as $child)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            {{ $child->nome }}
                            <x-erp.status-badge :status="$child->status" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-erp.card>
    </div>
</div>

<x-erp.card title="Produtos nesta Categoria" icon="box-seam">
    <div class="table-responsive">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Descricao</th>
                    <th class="text-end">Preco Venda</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categoria->produtos as $produto)
                    <tr>
                        <td>{{ $produto->descricao }}</td>
                        <td class="text-end">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                        <td class="text-center"><x-erp.status-badge :status="$produto->status" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3"><x-erp.empty-state title="Nenhum produto nesta categoria" icon="box-seam" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-erp.card>
@endsection
