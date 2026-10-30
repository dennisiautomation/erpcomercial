@extends('layouts.app')

@section('title', 'Grupos de opções')

@section('content')
<x-erp.page-header title="Grupos de opções" icon="ui-checks-grid" subtitle="Tamanho, leite, extras: o que o caixa escolhe ao lançar o produto no PDV">
    <a href="{{ route('app.produtos.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Produtos
    </a>
    <a href="{{ route('app.grupos-opcoes.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo grupo
    </a>
</x-erp.page-header>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    Um grupo vale para vários produtos: crie "Leite" uma vez e ligue em todos os cafés, na tela
    <strong>Ficha técnica e opções</strong> de cada produto. Cada opção é um produto do tipo
    <strong>Adicional</strong>; se ele tiver receita, a venda baixa os insumos dela.
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('app.grupos-opcoes.index') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="campo-busca" class="form-label">Buscar</label>
                <input id="campo-busca" type="text" name="busca" class="form-control" placeholder="Nome do grupo..." value="{{ request('busca') }}">
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                <a href="{{ route('app.grupos-opcoes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i> Limpar</a>
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
                        <th>Grupo</th>
                        <th>Regra</th>
                        <th>Opções</th>
                        <th class="text-center">Produtos</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width:120px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grupos as $grupo)
                        <tr>
                            <td><strong>{{ $grupo->nome }}</strong></td>
                            <td class="small">{{ $grupo->regraTexto() }}</td>
                            <td class="small">
                                @foreach($grupo->itens as $item)
                                    <span class="badge bg-light text-dark border me-1 mb-1">
                                        {{ $item->adicional?->descricao ?? '—' }}
                                        @if($item->precoEfetivo() > 0) +R$ {{ number_format($item->precoEfetivo(), 2, ',', '.') }} @endif
                                    </span>
                                @endforeach
                            </td>
                            <td class="text-center">{{ $grupo->produtos_count }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $grupo->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($grupo->status) }}</span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('app.grupos-opcoes.edit', $grupo) }}" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('app.grupos-opcoes.destroy', $grupo) }}" class="d-inline"
                                          data-confirm="Excluir o grupo {{ $grupo->nome }}? Ele sai de {{ $grupo->produtos_count }} produto(s). As vendas já feitas não mudam.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-ui-checks-grid fs-1 d-block mb-2"></i>
                                Nenhum grupo ainda. Comece por "Tamanho" ou "Leite".
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($grupos->hasPages())
        <div class="card-footer bg-white">{{ $grupos->links() }}</div>
    @endif
</div>
@endsection
