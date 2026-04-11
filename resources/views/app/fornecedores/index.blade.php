@extends('layouts.app')

@section('title', 'Fornecedores')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-truck me-2"></i>Fornecedores</h4>
        <small class="text-muted">Gerencie seus fornecedores e parceiros comerciais</small>
    </div>
    <a href="{{ route('app.fornecedores.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Fornecedor
    </a>
</div>

{{-- Filtros --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.fornecedores.index') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="busca" class="form-control" placeholder="Razao social, nome fantasia, CPF/CNPJ ou e-mail..." value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">UF</label>
                <select name="uf" class="form-select">
                    <option value="">Todos</option>
                    @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $sigla)
                        <option value="{{ $sigla }}" {{ request('uf') === $sigla ? 'selected' : '' }}>{{ $sigla }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.fornecedores.index') }}" class="btn btn-outline-secondary">
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
                        <th>CPF/CNPJ</th>
                        <th>Razao Social / Nome Fantasia</th>
                        <th>Cidade/UF</th>
                        <th>Telefone</th>
                        <th>E-mail</th>
                        <th class="text-center" style="width: 150px;">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fornecedores as $fornecedor)
                        <tr>
                            <td class="text-nowrap">
                                <code>{{ $fornecedor->cpf_cnpj }}</code>
                            </td>
                            <td>
                                <strong>{{ $fornecedor->razao_social }}</strong>
                                @if($fornecedor->nome_fantasia)
                                    <br><small class="text-muted">{{ $fornecedor->nome_fantasia }}</small>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if($fornecedor->cidade)
                                    {{ $fornecedor->cidade }}{{ $fornecedor->uf ? '/' . $fornecedor->uf : '' }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $fornecedor->telefone ?: '-' }}</td>
                            <td>{{ $fornecedor->email ?: '-' }}</td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('app.fornecedores.show', $fornecedor) }}" class="btn btn-outline-info" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('app.fornecedores.edit', $fornecedor) }}" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('app.fornecedores.destroy', $fornecedor) }}" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este fornecedor?')">
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
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Nenhum fornecedor encontrado
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($fornecedores->hasPages())
        <div class="card-footer bg-white">
            {{ $fornecedores->links() }}
        </div>
    @endif
</div>
@endsection
