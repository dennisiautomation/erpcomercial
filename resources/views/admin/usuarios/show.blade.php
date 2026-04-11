@extends('layouts.app')

@section('title', 'Detalhes do Usuario')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-person me-2"></i>{{ $usuario->name }}
        @if($usuario->status === 'ativo')
            <span class="badge bg-success fs-6 ms-2">Ativo</span>
        @else
            <span class="badge bg-secondary fs-6 ms-2">Inativo</span>
        @endif
    </h4>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- Dados do Usuario --}}
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-person me-2"></i>Dados do Usuario</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted">Nome</dt>
                    <dd class="col-sm-7">{{ $usuario->name }}</dd>

                    <dt class="col-sm-5 text-muted">E-mail</dt>
                    <dd class="col-sm-7">{{ $usuario->email }}</dd>

                    <dt class="col-sm-5 text-muted">CPF</dt>
                    <dd class="col-sm-7">{{ $usuario->cpf ?? '-' }}</dd>

                    <dt class="col-sm-5 text-muted">Telefone</dt>
                    <dd class="col-sm-7">{{ $usuario->telefone ?? '-' }}</dd>

                    <dt class="col-sm-5 text-muted">Perfil</dt>
                    <dd class="col-sm-7">
                        @if($usuario->perfil instanceof \App\Enums\Perfil)
                            <span class="badge bg-info text-dark">{{ $usuario->perfil->label() }}</span>
                        @else
                            <span class="badge bg-secondary">{{ $usuario->perfil ?? '-' }}</span>
                        @endif
                    </dd>

                    <dt class="col-sm-5 text-muted">Administrador</dt>
                    <dd class="col-sm-7">
                        @if($usuario->is_admin)
                            <span class="badge bg-danger">Sim</span>
                        @else
                            <span class="badge bg-secondary">Nao</span>
                        @endif
                    </dd>

                    <dt class="col-sm-5 text-muted">Status</dt>
                    <dd class="col-sm-7">
                        @if($usuario->status === 'ativo')
                            <span class="badge bg-success">Ativo</span>
                        @else
                            <span class="badge bg-secondary">Inativo</span>
                        @endif
                    </dd>

                    <dt class="col-sm-5 text-muted">Comissao</dt>
                    <dd class="col-sm-7">
                        {{ $usuario->comissao_percentual ? number_format($usuario->comissao_percentual, 2, ',', '.') . '%' : '-' }}
                    </dd>

                    <dt class="col-sm-5 text-muted">Cadastrado em</dt>
                    <dd class="col-sm-7">{{ $usuario->created_at?->format('d/m/Y H:i') ?? '-' }}</dd>

                    <dt class="col-sm-5 text-muted">Atualizado em</dt>
                    <dd class="col-sm-7">{{ $usuario->updated_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Empresa --}}
    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-building me-2"></i>Empresa</h6>
            </div>
            <div class="card-body">
                @if($usuario->empresa)
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted">Razao Social</dt>
                        <dd class="col-sm-7 fw-semibold">{{ $usuario->empresa->razao_social }}</dd>

                        <dt class="col-sm-5 text-muted">Nome Fantasia</dt>
                        <dd class="col-sm-7">{{ $usuario->empresa->nome_fantasia ?? '-' }}</dd>

                        <dt class="col-sm-5 text-muted">CNPJ</dt>
                        <dd class="col-sm-7">{{ $usuario->empresa->cnpj ?? '-' }}</dd>
                    </dl>
                    <div class="mt-3">
                        <a href="{{ route('admin.empresas.show', $usuario->empresa) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i> Ver Empresa
                        </a>
                    </div>
                @else
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i> Sem empresa vinculada (Admin da plataforma)
                    </p>
                @endif
            </div>
        </div>

        {{-- Unidades --}}
        @if($usuario->relationLoaded('unidades') && $usuario->unidades->count() > 0)
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="bi bi-shop me-2"></i>Unidades
                    <span class="badge bg-secondary ms-1">{{ $usuario->unidades->count() }}</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Cidade / UF</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usuario->unidades as $unidade)
                            <tr>
                                <td class="fw-semibold">{{ $unidade->nome }}</td>
                                <td>{{ $unidade->cidade ?? '-' }} / {{ $unidade->uf ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
