@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>Clientes</h4>
    <a href="{{ route('app.clientes.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Cliente
    </a>
</div>

{{-- Filtros --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.clientes.index') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="busca" class="form-control" placeholder="Nome, CPF/CNPJ ou Nome Fantasia..." value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="ativo" {{ request('status') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                    <option value="inativo" {{ request('status') === 'inativo' ? 'selected' : '' }}>Inativo</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.clientes.index') }}" class="btn btn-outline-secondary">
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
                        <th>Nome / Razão Social</th>
                        <th>Cidade/UF</th>
                        <th>Telefone</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $cliente)
                        <tr>
                            <td>{{ $cliente->cpf_cnpj }}</td>
                            <td>
                                <strong>{{ $cliente->nome_razao_social }}</strong>
                                @if($cliente->nome_fantasia)
                                    <br><small class="text-muted">{{ $cliente->nome_fantasia }}</small>
                                @endif
                            </td>
                            <td>{{ $cliente->cidade }}{{ $cliente->uf ? '/' . $cliente->uf : '' }}</td>
                            <td>{{ $cliente->telefone ?: $cliente->whatsapp ?: '-' }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $cliente->status === 'ativo' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($cliente->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('app.clientes.show', $cliente) }}" class="btn btn-outline-info" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('app.clientes.edit', $cliente) }}" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('app.clientes.destroy', $cliente) }}" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este cliente?')">
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
                                Nenhum cliente encontrado
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($clientes->hasPages())
        <div class="card-footer bg-white">
            {{ $clientes->links() }}
        </div>
    @endif
</div>
@endsection
