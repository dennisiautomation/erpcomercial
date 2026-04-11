@extends('layouts.app')

@section('title', 'Funcionários')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-people-fill me-2"></i>Funcionários</h4>
    <a href="{{ route('app.funcionarios.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Funcionário
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.funcionarios.index') }}" class="row g-3 align-items-end">
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
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                <a href="{{ route('app.funcionarios.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i> Limpar</a>
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
                                <span class="badge bg-primary">{{ $func->perfil?->label() ?? '-' }}</span>
                            </td>
                            <td class="text-center">{{ $func->comissao_percentual ? number_format($func->comissao_percentual, 2, ',', '.') . '%' : '-' }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $func->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($func->status) }}</span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('app.funcionarios.show', $func) }}" class="btn btn-outline-info" title="Visualizar"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('app.funcionarios.edit', $func) }}" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    @if($func->id !== auth()->id())
                                        <form method="POST" action="{{ route('app.funcionarios.destroy', $func) }}" class="d-inline" onsubmit="return confirm('Tem certeza que deseja desativar este funcionário?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Desativar"><i class="bi bi-person-x"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Nenhum funcionário encontrado
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($funcionarios->hasPages())
        <div class="card-footer bg-white">{{ $funcionarios->links() }}</div>
    @endif
</div>
@endsection
