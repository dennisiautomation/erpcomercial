@extends('layouts.app')

@section('title', 'Detalhes da Empresa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-building me-2"></i>{{ $empresa->razao_social }}
        <span class="badge bg-{{ $empresa->status->color() }} fs-6 ms-2">{{ $empresa->status->label() }}</span>
    </h4>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.empresas.edit', $empresa) }}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('admin.empresas.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Tabs --}}
<ul class="nav nav-tabs mb-4" id="empresaTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados"
                type="button" role="tab">
            <i class="bi bi-info-circle me-1"></i> Dados
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="unidades-tab" data-bs-toggle="tab" data-bs-target="#unidades"
                type="button" role="tab">
            <i class="bi bi-shop me-1"></i> Unidades
            <span class="badge bg-secondary ms-1">{{ $empresa->unidades->count() }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios"
                type="button" role="tab">
            <i class="bi bi-people me-1"></i> Usuarios
            <span class="badge bg-secondary ms-1">{{ $empresa->users->count() }}</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="empresaTabContent">
    {{-- Tab Dados --}}
    <div class="tab-pane fade show active" id="dados" role="tabpanel">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-building me-2"></i>Dados Cadastrais</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-muted">CNPJ</dt>
                            <dd class="col-sm-7">{{ $empresa->cnpj }}</dd>

                            <dt class="col-sm-5 text-muted">Razao Social</dt>
                            <dd class="col-sm-7">{{ $empresa->razao_social }}</dd>

                            <dt class="col-sm-5 text-muted">Nome Fantasia</dt>
                            <dd class="col-sm-7">{{ $empresa->nome_fantasia ?? '-' }}</dd>

                            <dt class="col-sm-5 text-muted">IE</dt>
                            <dd class="col-sm-7">{{ $empresa->ie ?? '-' }}</dd>

                            <dt class="col-sm-5 text-muted">IM</dt>
                            <dd class="col-sm-7">{{ $empresa->im ?? '-' }}</dd>

                            <dt class="col-sm-5 text-muted">Regime Tributario</dt>
                            <dd class="col-sm-7">{{ $empresa->regime_tributario?->label() ?? '-' }}</dd>

                            <dt class="col-sm-5 text-muted">Plano</dt>
                            <dd class="col-sm-7"><span class="badge bg-primary">{{ ucfirst($empresa->plano) }}</span></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Endereco</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-muted">CEP</dt>
                            <dd class="col-sm-8">{{ $empresa->cep ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Logradouro</dt>
                            <dd class="col-sm-8">{{ $empresa->logradouro ?? '-' }}, {{ $empresa->numero ?? 'S/N' }}</dd>

                            <dt class="col-sm-4 text-muted">Complemento</dt>
                            <dd class="col-sm-8">{{ $empresa->complemento ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Bairro</dt>
                            <dd class="col-sm-8">{{ $empresa->bairro ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Cidade / UF</dt>
                            <dd class="col-sm-8">{{ $empresa->cidade ?? '-' }} / {{ $empresa->uf ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-telephone me-2"></i>Contato</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-muted">Telefone</dt>
                            <dd class="col-sm-8">{{ $empresa->telefone ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">E-mail</dt>
                            <dd class="col-sm-8">{{ $empresa->email ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        @if($empresa->observacoes)
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-chat-text me-2"></i>Observacoes</h6>
            </div>
            <div class="card-body">
                <p class="mb-0">{{ $empresa->observacoes }}</p>
            </div>
        </div>
        @endif
    </div>

    {{-- Tab Unidades --}}
    <div class="tab-pane fade" id="unidades" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Unidades</h5>
            <a href="{{ route('admin.empresas.unidades.create', $empresa) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Nova Unidade
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>CNPJ</th>
                                <th>Cidade / UF</th>
                                <th>Status</th>
                                <th class="text-end">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($empresa->unidades as $unidade)
                            <tr>
                                <td class="fw-semibold">{{ $unidade->nome }}</td>
                                <td>{{ $unidade->cnpj ?? '-' }}</td>
                                <td>{{ $unidade->cidade ?? '-' }} / {{ $unidade->uf ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $unidade->status === 'ativo' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($unidade->status ?? 'ativo') }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.empresas.unidades.edit', [$empresa, $unidade]) }}" class="btn btn-outline-secondary btn-sm" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <x-delete-form :action="route('admin.empresas.unidades.destroy', [$empresa, $unidade])" />
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Nenhuma unidade cadastrada.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab Usuarios --}}
    <div class="tab-pane fade" id="usuarios" role="tabpanel">
        <h5 class="mb-3">Usuarios</h5>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Cadastrado em</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($empresa->users as $user)
                            <tr>
                                <td class="fw-semibold">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->created_at->format('d/m/Y') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Nenhum usuario vinculado.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
