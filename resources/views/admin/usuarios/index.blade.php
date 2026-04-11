@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Usuarios</h4>
    <a href="{{ route('admin.usuarios.create') }}" class="btn btn-dark"><i class="bi bi-plus-lg me-1"></i> Novo Usuario</a>
</div>
<x-alert />
<div class="card mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Buscar..." value="{{ request('search') }}"></div>
        <div class="col-md-3"><select name="perfil" class="form-select"><option value="">Todos os perfis</option>@foreach($perfis as $p)<option value="{{ $p->value }}" {{ request('perfil') == $p->value ? 'selected' : '' }}>{{ $p->label() }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="empresa_id" class="form-select"><option value="">Todas as empresas</option>@foreach($empresas as $emp)<option value="{{ $emp->id }}" {{ request('empresa_id') == $emp->id ? 'selected' : '' }}>{{ $emp->nome_fantasia ?: $emp->razao_social }}</option>@endforeach</select></div>
        <div class="col-md-2"><button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i></button></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Empresa</th><th>Status</th><th class="text-end">Acoes</th></tr></thead><tbody>
@forelse($usuarios as $u)
<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td><span class="badge bg-primary">{{ $u->perfil instanceof \App\Enums\Perfil ? $u->perfil->label() : $u->perfil }}</span></td><td>{{ $u->empresa?->nome_fantasia ?: $u->empresa?->razao_social ?: '—' }}</td><td><span class="badge bg-{{ $u->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($u->status ?? 'ativo') }}</span></td><td class="text-end"><a href="{{ route('admin.usuarios.show', $u) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye"></i></a> <a href="{{ route('admin.usuarios.edit', $u) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a></td></tr>
@empty
<tr><td colspan="6" class="text-center text-muted py-4">Nenhum usuario encontrado.</td></tr>
@endforelse
</tbody></table></div>
@if($usuarios->hasPages())<div class="card-footer">{{ $usuarios->links() }}</div>@endif
</div>
@endsection
