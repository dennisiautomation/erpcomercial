@extends('layouts.app')

@section('title', 'Detalhes da Empresa')

@push('styles')
<style>
    .detail-card {
        border: none;
        border-radius: 0.75rem;
    }
    .detail-card .card-header {
        background: #fff;
        border-bottom: 1px solid #f1f5f9;
        padding: 0.875rem 1.25rem;
    }
    .detail-card .card-header h6 {
        font-size: 0.9375rem;
        font-weight: 600;
    }
    .detail-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #94a3b8;
        margin-bottom: 0.125rem;
    }
    .detail-value {
        font-size: 0.9375rem;
        color: #1e293b;
    }
    .empresa-header {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        border-radius: 0.75rem;
        color: #fff;
        padding: 1.5rem;
    }
    .empresa-header .badge {
        font-size: 0.8125rem;
    }
    .nav-tabs .nav-link {
        font-weight: 500;
        color: #64748b;
        border: none;
        padding: 0.75rem 1.25rem;
        border-bottom: 2px solid transparent;
        transition: all 0.15s ease;
    }
    .nav-tabs .nav-link:hover {
        color: #0d6efd;
        border-bottom-color: #dee2e6;
    }
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        background: transparent;
    }
    .table-details th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
        border-bottom-width: 2px;
    }
    .status-badge-unidade {
        font-weight: 500;
    }
</style>
@endpush

@section('content')
{{-- Header Card --}}
<div class="empresa-header mb-4 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div class="d-flex align-items-center gap-3">
            @if($empresa->logo)
                <img src="{{ asset('storage/' . $empresa->logo) }}" alt="Logo"
                     class="rounded bg-white p-1" style="width: 56px; height: 56px; object-fit: contain;">
            @else
                <div class="bg-white bg-opacity-10 rounded d-flex align-items-center justify-content-center"
                     style="width: 56px; height: 56px;">
                    <i class="bi bi-building fs-3 text-white-50"></i>
                </div>
            @endif
            <div>
                <h4 class="fw-bold mb-1">{{ $empresa->razao_social }}</h4>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <code class="text-white-50">{{ $empresa->cnpj }}</code>
                    @if($empresa->nome_fantasia)
                        <span class="text-white-50">|</span>
                        <span class="text-white-50">{{ $empresa->nome_fantasia }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge bg-{{ $empresa->status->color() }}">{{ $empresa->status->label() }}</span>
            @if($empresa->plano)
                <span class="badge bg-white text-primary">{{ ucfirst($empresa->plano) }}</span>
            @endif
            @if($empresa->em_trial)
                <span class="badge bg-warning text-dark">Em Trial</span>
            @endif
        </div>
    </div>
    <div class="mt-3 d-flex flex-wrap gap-2">
        <a href="{{ route('admin.empresas.edit', $empresa) }}" class="btn btn-light btn-sm">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('admin.empresas.index') }}" class="btn btn-outline-light btn-sm">
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
            <span class="badge bg-primary bg-opacity-10 text-primary ms-1">{{ $empresa->unidades->count() }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios"
                type="button" role="tab">
            <i class="bi bi-people me-1"></i> Usuarios
            <span class="badge bg-primary bg-opacity-10 text-primary ms-1">{{ $empresa->users->count() }}</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="empresaTabContent">
    {{-- Tab Dados --}}
    <div class="tab-pane fade show active" id="dados" role="tabpanel">
        <div class="row g-4">
            {{-- Dados Cadastrais --}}
            <div class="col-lg-6">
                <div class="card detail-card shadow-sm h-100">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-building me-2 text-primary"></i>Dados Cadastrais</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="detail-label">CNPJ</div>
                                <div class="detail-value fw-semibold">{{ $empresa->cnpj }}</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Regime Tributario</div>
                                <div class="detail-value">{{ $empresa->regime_tributario?->label() ?? '-' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="detail-label">Razao Social</div>
                                <div class="detail-value">{{ $empresa->razao_social }}</div>
                            </div>
                            <div class="col-12">
                                <div class="detail-label">Nome Fantasia</div>
                                <div class="detail-value">{{ $empresa->nome_fantasia ?? '-' }}</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Inscricao Estadual</div>
                                <div class="detail-value">{{ $empresa->ie ?? '-' }}</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Inscricao Municipal</div>
                                <div class="detail-value">{{ $empresa->im ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Endereco + Contato --}}
            <div class="col-lg-6">
                <div class="card detail-card shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-geo-alt me-2 text-danger"></i>Endereco</h6>
                    </div>
                    <div class="card-body">
                        @if($empresa->logradouro)
                            <p class="mb-1">
                                {{ $empresa->logradouro }}, {{ $empresa->numero ?? 'S/N' }}
                                @if($empresa->complemento) - {{ $empresa->complemento }} @endif
                            </p>
                            <p class="mb-1">{{ $empresa->bairro ?? '' }}</p>
                            <p class="mb-0">{{ $empresa->cidade ?? '' }}{{ $empresa->uf ? ' / ' . $empresa->uf : '' }}
                                @if($empresa->cep) - CEP: {{ $empresa->cep }} @endif
                            </p>
                        @else
                            <p class="text-muted mb-0">Endereco nao informado.</p>
                        @endif
                    </div>
                </div>

                <div class="card detail-card shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-telephone me-2 text-success"></i>Contato</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="detail-label">Telefone</div>
                                <div class="detail-value">
                                    @if($empresa->telefone)
                                        <i class="bi bi-telephone me-1 text-muted small"></i>{{ $empresa->telefone }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">E-mail</div>
                                <div class="detail-value">
                                    @if($empresa->email)
                                        <i class="bi bi-envelope me-1 text-muted small"></i>{{ $empresa->email }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Assinatura --}}
                <div class="card detail-card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-credit-card me-2 text-info"></i>Assinatura</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="detail-label">Plano</div>
                                <div class="detail-value">
                                    @if($empresa->plano)
                                        <span class="badge bg-primary">{{ ucfirst($empresa->plano) }}</span>
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Tipo Cobranca</div>
                                <div class="detail-value">{{ ucfirst($empresa->tipo_cobranca ?? '-') }}</div>
                            </div>
                            @if($empresa->em_trial)
                            <div class="col-6">
                                <div class="detail-label">Trial Inicio</div>
                                <div class="detail-value">{{ $empresa->trial_inicio ? \Carbon\Carbon::parse($empresa->trial_inicio)->format('d/m/Y') : '-' }}</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Trial Fim</div>
                                <div class="detail-value">{{ $empresa->trial_fim ? \Carbon\Carbon::parse($empresa->trial_fim)->format('d/m/Y') : '-' }}</div>
                            </div>
                            @endif
                            @if($empresa->assinatura_inicio)
                            <div class="col-6">
                                <div class="detail-label">Assinatura Inicio</div>
                                <div class="detail-value">{{ \Carbon\Carbon::parse($empresa->assinatura_inicio)->format('d/m/Y') }}</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Assinatura Fim</div>
                                <div class="detail-value">{{ $empresa->assinatura_fim ? \Carbon\Carbon::parse($empresa->assinatura_fim)->format('d/m/Y') : '-' }}</div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($empresa->observacoes)
        <div class="card detail-card shadow-sm mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-chat-text me-2 text-warning"></i>Observacoes</h6>
            </div>
            <div class="card-body">
                <p class="mb-0" style="white-space: pre-line;">{{ $empresa->observacoes }}</p>
            </div>
        </div>
        @endif
    </div>

    {{-- Tab Unidades --}}
    <div class="tab-pane fade" id="unidades" role="tabpanel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="fw-semibold mb-0">Unidades</h5>
            <a href="{{ route('admin.empresas.unidades.create', $empresa) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Nova Unidade
            </a>
        </div>

        <div class="card detail-card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-details">
                        <thead>
                            <tr>
                                <th class="ps-3">Nome</th>
                                <th>CNPJ</th>
                                <th>Cidade / UF</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($empresa->unidades as $unidade)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $unidade->nome }}</td>
                                <td class="text-nowrap">
                                    <code class="text-body-secondary">{{ $unidade->cnpj ?? '-' }}</code>
                                </td>
                                <td>{{ $unidade->cidade ?? '-' }} / {{ $unidade->uf ?? '-' }}</td>
                                <td>
                                    @php
                                        $statusColor = match($unidade->status) {
                                            'ativa' => 'success',
                                            'inativa' => 'secondary',
                                            'em_implantacao' => 'warning',
                                            default => 'light',
                                        };
                                        $statusLabel = match($unidade->status) {
                                            'ativa' => 'Ativa',
                                            'inativa' => 'Inativa',
                                            'em_implantacao' => 'Em Implantacao',
                                            default => ucfirst($unidade->status ?? 'ativa'),
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusColor }} status-badge-unidade">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.unidades.edit', $unidade) }}"
                                           class="btn btn-outline-secondary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                    <x-delete-form :action="route('admin.unidades.destroy', $unidade)" />
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-shop fs-1 d-block mb-2 opacity-25"></i>
                                    <p class="text-muted mb-1">Nenhuma unidade cadastrada</p>
                                    <a href="{{ route('admin.empresas.unidades.create', $empresa) }}" class="btn btn-primary btn-sm mt-2">
                                        <i class="bi bi-plus-lg me-1"></i> Cadastrar primeira unidade
                                    </a>
                                </td>
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="fw-semibold mb-0">Usuarios</h5>
        </div>

        <div class="card detail-card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-details">
                        <thead>
                            <tr>
                                <th class="ps-3">Nome</th>
                                <th>E-mail</th>
                                <th>Perfil</th>
                                <th>Cadastrado em</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($empresa->users as $user)
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                                             style="width: 32px; height: 32px;">
                                            <span class="text-primary fw-bold small">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                        </div>
                                        <span class="fw-semibold">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @if($user->perfil)
                                        <span class="badge bg-light text-dark border">{{ ucfirst($user->perfil->value ?? $user->perfil) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
                                    <p class="text-muted mb-0">Nenhum usuario vinculado a esta empresa.</p>
                                </td>
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
