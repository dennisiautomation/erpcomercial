@extends('layouts.app')

@section('title', 'Funcionários')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-people-fill me-2"></i>Funcionários</h4>
        <div class="subtitle">Gerencie a equipe da empresa</div>
    </div>
    <a href="{{ route('app.funcionarios.create') }}" class="btn btn-erp btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Funcionário
    </a>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('app.funcionarios.index') }}" class="row g-3 align-items-end erp-form">
        <div class="col-md-4">
            <label class="form-label">Buscar</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="busca" class="form-control" placeholder="Nome, e-mail ou CPF..." value="{{ request('busca') }}">
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Perfil</label>
            <select name="perfil" class="form-select">
                <option value="">Todos</option>
                @foreach(\App\Enums\Perfil::cases() as $p)
                    <option value="{{ $p->value }}" {{ request('perfil') === $p->value ? 'selected' : '' }}>{{ $p->label() }}</option>
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
            <button type="submit" class="btn btn-erp btn-erp-primary"><i class="bi bi-funnel me-1"></i> Filtrar</button>
            <a href="{{ route('app.funcionarios.index') }}" class="btn btn-erp btn-erp-outline"><i class="bi bi-x-lg me-1"></i> Limpar</a>
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
                        <th>E-mail</th>
                        <th>CPF</th>
                        <th>Perfil</th>
                        <th class="text-center">Comissão</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($funcionarios as $func)
                        <tr>
                            <td><strong>{{ $func->name }}</strong></td>
                            <td>{{ $func->email }}</td>
                            <td>{{ $func->cpf ?: '-' }}</td>
                            <td>
                                <span class="badge-status confirmado">{{ $func->perfil?->label() ?? '-' }}</span>
                            </td>
                            <td class="text-center">{{ $func->comissao_percentual ? number_format($func->comissao_percentual, 2, ',', '.') . '%' : '-' }}</td>
                            <td class="text-center">
                                <span class="badge-status {{ $func->status }}">{{ ucfirst($func->status) }}</span>
                            </td>
                            <td class="text-center">
                                <div class="action-btns justify-content-center">
                                    <a href="{{ route('app.funcionarios.show', $func) }}" class="btn btn-sm btn-outline-info" title="Visualizar"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('app.funcionarios.edit', $func) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    @if($func->id !== auth()->id())
                                        <form method="POST" action="{{ route('app.funcionarios.destroy', $func) }}" class="d-inline" data-confirm="Tem certeza que deseja desativar este funcionário?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Desativar"><i class="bi bi-person-x"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-inbox d-block"></i>
                                    <h5>Nenhum funcionário encontrado</h5>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($funcionarios->hasPages())
        <div class="card-body border-top">{{ $funcionarios->links() }}</div>
    @endif
</div>
</div>
@endsection
