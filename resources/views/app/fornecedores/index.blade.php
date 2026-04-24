@extends('layouts.app')

@section('title', 'Fornecedores')

@section('content')
<x-erp.page-header title="Fornecedores" subtitle="Gerencie seus fornecedores e parceiros comerciais" icon="truck">
    <a href="{{ route('app.export.fornecedores') }}" class="btn btn-erp-outline"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar</a>
    <x-erp.import-buttons :importRoute="route('app.import.fornecedores')" templateType="fornecedores" />
    <a href="{{ route('app.fornecedores.create') }}" class="btn btn-erp-primary"><i class="bi bi-plus-lg me-1"></i>Novo Fornecedor</a>
</x-erp.page-header>

<x-erp.filter-bar :action="route('app.fornecedores.index')">
    <div class="col-md-5">
        <label class="form-label">Buscar</label>
        <input type="text" name="busca" class="form-control" placeholder="Razao social, nome fantasia, CPF/CNPJ ou e-mail..." value="{{ request('busca') }}">
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
</x-erp.filter-bar>

<x-erp.data-table>
    <thead>
        <tr>
            <th>CPF/CNPJ</th>
            <th>Razao Social / Nome Fantasia</th>
            <th>Cidade/UF</th>
            <th>Telefone</th>
            <th>E-mail</th>
            <th class="text-center" style="width:150px;">Acoes</th>
        </tr>
    </thead>
    <tbody>
        @forelse($fornecedores as $fornecedor)
            <tr>
                <td class="text-nowrap"><code>{{ $fornecedor->cpf_cnpj }}</code></td>
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
                <td class="action-btns text-center">
                    <a href="{{ route('app.fornecedores.show', $fornecedor) }}" class="btn btn-sm btn-outline-info" title="Visualizar"><i class="bi bi-eye"></i></a>
                    <a href="{{ route('app.fornecedores.edit', $fornecedor) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('app.fornecedores.destroy', $fornecedor) }}" class="d-inline" data-confirm="Tem certeza que deseja excluir este fornecedor?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6"><x-erp.empty-state title="Nenhum fornecedor encontrado" icon="truck" :actionUrl="route('app.fornecedores.create')" actionLabel="Novo Fornecedor" /></td>
            </tr>
        @endforelse
    </tbody>
    <x-slot:pagination>{{ $fornecedores->links() }}</x-slot:pagination>
</x-erp.data-table>
@endsection
