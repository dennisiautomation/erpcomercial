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
        <form method="POST" action="{{ route('admin.empresas.acessar-como', $empresa) }}" class="m-0">
            @csrf
            <button class="btn btn-warning btn-sm"
                    data-confirm="Acessar o sistema como o responsável da empresa {{ $empresa->razao_social }}? Suas ações ficam registradas na auditoria.">
                <i class="bi bi-incognito me-1"></i> Acessar como cliente
            </button>
        </form>
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
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="integracao-tab" data-bs-toggle="tab" data-bs-target="#integracao"
                type="button" role="tab">
            <i class="bi bi-plug me-1"></i> Integração
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

    {{-- Tab Integração (API consumida pelo Gersen) --}}
    <div class="tab-pane fade" id="integracao" role="tabpanel">
        @php($agenteIa = $empresa->agenteIaConfig)
        <div class="row g-4 mb-1">
            <div class="col-12">
                <div class="card detail-card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-robot me-1"></i> Agente IA (WhatsApp via app.ia365)</h6>
                        @if($agenteIa?->ativo)
                            <span class="badge bg-success bg-opacity-10 text-success">Ativo</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Inativo</span>
                        @endif
                    </div>
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <div class="flex-grow-1">
                            <p class="text-muted small mb-1">
                                Indexa os produtos desta empresa para busca semântica e libera os endpoints
                                do agente (<code>/api/integracao/v1/produtos/buscar</code> e
                                <code>/api/integracao/v1/pedidos</code>) — autenticados pelos mesmos tokens abaixo.
                            </p>
                            @if($agenteIa?->indexado_em)
                                <p class="small mb-0">
                                    <i class="bi bi-check-circle text-success me-1"></i>
                                    {{ $agenteIa->produtos_indexados }} produtos indexados
                                    (última indexação {{ $agenteIa->indexado_em->format('d/m/Y H:i') }})
                                </p>
                            @elseif($agenteIa?->ativo)
                                <p class="small mb-0 text-muted">
                                    <i class="bi bi-hourglass-split me-1"></i> Indexação inicial na fila…
                                </p>
                            @endif
                            @if($agenteIa?->ultima_falha)
                                <p class="small mb-0 text-danger">
                                    <i class="bi bi-x-circle me-1"></i> Última falha: {{ $agenteIa->ultima_falha }}
                                </p>
                            @endif
                        </div>
                        <div class="d-flex gap-2">
                            @if($agenteIa?->ativo)
                            <form method="POST" action="{{ route('admin.empresas.agente-ia.reindexar', $empresa) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-arrow-repeat me-1"></i> Reindexar
                                </button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('admin.empresas.agente-ia.toggle', $empresa) }}"
                                  @if($agenteIa?->ativo) onsubmit="return confirm('Desativar o Agente IA? Os endpoints de busca e pedidos param de responder para esta empresa.')" @endif>
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $agenteIa?->ativo ? 'btn-outline-danger' : 'btn-primary' }}">
                                    {{ $agenteIa?->ativo ? 'Desativar' : 'Ativar Agente IA' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @php($gatewayPix = \App\Models\EmpresaGateway::where('empresa_id', $empresa->id)->where('provedor', \App\Models\EmpresaGateway::PROVEDOR_SICREDI_PIX)->first())
        <div class="row g-4 mb-1">
            <div class="col-12">
                <div class="card detail-card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-qr-code me-1"></i> PIX do Vendedor IA (Sicredi)</h6>
                        @if($gatewayPix?->ativo && $gatewayPix->utilizavel())
                            <span class="badge bg-success bg-opacity-10 text-success">Ativo</span>
                        @elseif($gatewayPix?->ativo)
                            <span class="badge bg-warning bg-opacity-10 text-warning">Incompleto</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Inativo</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Com o gateway ativo, o pedido criado pelo agente já sai com PIX copia-e-cola e o
                            pagamento confirma o pedido automaticamente (rascunho → confirmado). O faturamento
                            continua manual. A chave privada deve ser a versão <strong>sem senha</strong>.
                            ⚠️ O webhook do Sicredi é <strong>por chave PIX</strong> — registrar aqui sobrescreve
                            webhook anterior da mesma chave em outro sistema.
                        </p>
                        <form method="POST" action="{{ route('admin.empresas.gateway-pix.store', $empresa) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Client ID</label>
                                <input type="text" name="client_id" class="form-control form-control-sm"
                                       placeholder="{{ $gatewayPix?->client_id ? '•••• salvo — preencha p/ trocar' : 'client_id do app Sicredi' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Client Secret</label>
                                <input type="password" name="client_secret" class="form-control form-control-sm"
                                       placeholder="{{ $gatewayPix?->client_secret ? '•••• salvo — preencha p/ trocar' : 'client_secret do app Sicredi' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Chave PIX da conta</label>
                                <input type="text" name="chave_pix" class="form-control form-control-sm"
                                       value="{{ $gatewayPix?->chave_pix }}" placeholder="CNPJ, e-mail ou chave aleatória">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Certificado mTLS (.cer/.pem) {{ $gatewayPix?->cert_path ? '✓ enviado' : '' }}</label>
                                <input type="file" name="certificado" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Chave privada SEM senha (.key) {{ $gatewayPix?->key_path ? '✓ enviada' : '' }}</label>
                                <input type="file" name="chave_privada" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Expiração (s)</label>
                                <input type="number" name="expiracao_segundos" class="form-control form-control-sm"
                                       value="{{ $gatewayPix?->expiracao_segundos ?? 86400 }}" min="300" max="604800">
                            </div>
                            <div class="col-md-2">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" name="ativo" value="1" id="pixAtivo"
                                           @checked($gatewayPix?->ativo)>
                                    <label class="form-check-label small" for="pixAtivo">Ativo</label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary w-100">Salvar</button>
                            </div>
                        </form>
                        <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
                            <form method="POST" action="{{ route('admin.empresas.gateway-pix.testar', $empresa) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(! $gatewayPix?->utilizavel())>
                                    <i class="bi bi-activity me-1"></i> Testar conexão
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.empresas.gateway-pix.webhook', $empresa) }}"
                                  onsubmit="return confirm('Registrar o webhook desta chave PIX apontando para ESTE ERP? Sobrescreve webhook anterior da chave.')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(! $gatewayPix?->utilizavel())>
                                    <i class="bi bi-broadcast me-1"></i> Registrar webhook
                                </button>
                            </form>
                            @if($gatewayPix?->webhook_registrado_em)
                                <span class="small text-muted">Webhook registrado em {{ $gatewayPix->webhook_registrado_em->format('d/m/Y H:i') }}</span>
                            @endif
                            @if($gatewayPix?->ultima_falha)
                                <span class="small text-danger"><i class="bi bi-x-circle me-1"></i>Última falha: {{ $gatewayPix->ultima_falha }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @php($gatewayMe = \App\Models\EmpresaGateway::where('empresa_id', $empresa->id)->where('provedor', \App\Models\EmpresaGateway::PROVEDOR_MELHOR_ENVIO)->first())
        @php($meApp = \App\Services\Entrega\MelhorEnvioService::app())
        @php($meConectado = filled($gatewayMe?->access_token))
        <div class="row g-4 mb-4">
            {{-- 05/09/2026: Melhor Envio — frete para OUTRA cidade no Vendedor IA. A empresa autoriza a PRÓPRIA conta (OAuth); o aplicativo é da IA365 (/admin/integracoes) --}}
            <div class="col-lg-6">
                <div class="card detail-card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-box-seam me-1"></i> Envio — Melhor Envio</h6>
                        @if(! $meApp['configurado'])
                            <span class="badge bg-warning bg-opacity-10 text-warning">App não configurado</span>
                        @elseif($meConectado && $gatewayMe->ativo)
                            <span class="badge bg-success bg-opacity-10 text-success">Conectado</span>
                        @elseif($meConectado)
                            <span class="badge bg-warning bg-opacity-10 text-warning">Conectado, inativo</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Não conectado</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Cliente do Vendedor IA em <strong>outra cidade</strong>: o frete é cotado aqui (Correios, Jadlog,
                            Loggi…) pelas medidas dos produtos e o cliente escolhe a opção na conversa. Na mesma cidade
                            continua valendo o Uber Direct. A empresa autoriza a <strong>própria conta</strong> do Melhor
                            Envio — não copia chave nenhuma.
                        </p>
                        @if(! $meApp['configurado'])
                            <div class="alert alert-warning small py-2 mb-3">
                                Cadastre o aplicativo IA365 em <a href="{{ route('admin.integracoes.index') }}">Integrações</a> antes de conectar empresas.
                            </div>
                        @endif
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            @if($meConectado)
                                <span class="small"><i class="bi bi-person-check me-1"></i>{{ $gatewayMe->config['conta_nome'] ?? '' }}
                                    <span class="text-muted">{{ $gatewayMe->config['conta_email'] ?? '' }}</span></span>
                                <span class="small text-muted">· token até {{ optional($gatewayMe->token_expira_em)->format('d/m/Y') ?? '?' }} (renova sozinho)</span>
                                <form method="POST" action="{{ route('admin.empresas.gateway-melhor-envio.testar', $empresa) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-activity me-1"></i> Testar conexão</button>
                                </form>
                                <form method="POST" action="{{ route('admin.empresas.gateway-melhor-envio.conectar', $empresa) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary" @disabled(! $meApp['configurado'])>Reconectar</button>
                                </form>
                                <form method="POST" action="{{ route('admin.empresas.gateway-melhor-envio.desconectar', $empresa) }}"
                                      onsubmit="return confirm('Desconectar a conta do Melhor Envio desta empresa?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Desconectar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.empresas.gateway-melhor-envio.conectar', $empresa) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary" @disabled(! $meApp['configurado'])>
                                        <i class="bi bi-plug me-1"></i> Conectar Melhor Envio
                                    </button>
                                </form>
                                <span class="small text-muted">Abre o login do Melhor Envio; a loja entra com a conta dela e autoriza.</span>
                            @endif
                            @if($gatewayMe?->ultima_falha)
                                <span class="small text-danger w-100"><i class="bi bi-x-circle me-1"></i>Última falha: {{ $gatewayMe->ultima_falha }}</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.empresas.gateway-melhor-envio.store', $empresa) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-12"><div class="small fw-semibold">Pacote padrão — produto sem medida cadastrada</div></div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Altura (cm)</label>
                                <input type="number" step="0.1" min="1" name="pacote_altura" class="form-control form-control-sm"
                                       value="{{ $gatewayMe?->config['pacote_altura'] ?? \App\Services\Entrega\MelhorEnvioService::PACOTE_PADRAO['altura'] }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Largura (cm)</label>
                                <input type="number" step="0.1" min="1" name="pacote_largura" class="form-control form-control-sm"
                                       value="{{ $gatewayMe?->config['pacote_largura'] ?? \App\Services\Entrega\MelhorEnvioService::PACOTE_PADRAO['largura'] }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Comprimento (cm)</label>
                                <input type="number" step="0.1" min="1" name="pacote_comprimento" class="form-control form-control-sm"
                                       value="{{ $gatewayMe?->config['pacote_comprimento'] ?? \App\Services\Entrega\MelhorEnvioService::PACOTE_PADRAO['comprimento'] }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Peso (kg)</label>
                                <input type="number" step="0.01" min="0.01" name="pacote_peso" class="form-control form-control-sm"
                                       value="{{ $gatewayMe?->config['pacote_peso'] ?? \App\Services\Entrega\MelhorEnvioService::PACOTE_PADRAO['peso'] }}">
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small mb-1">Serviços permitidos (ids do Melhor Envio, por vírgula)</label>
                                <input type="text" name="servicos" class="form-control form-control-sm"
                                       value="{{ $gatewayMe?->config['servicos'] ?? '' }}" placeholder="vazio = todos · ex.: 1,2 (PAC, SEDEX)">
                            </div>
                            <div class="col-md-5 d-flex flex-wrap gap-3 pb-1">
                                <div class="form-check">
                                    <input type="hidden" name="seguro" value="0">
                                    <input class="form-check-input" type="checkbox" name="seguro" value="1" id="meSeguro" @checked($gatewayMe?->config['seguro'] ?? true)>
                                    <label class="form-check-label small" for="meSeguro">Declarar valor (seguro)</label>
                                </div>
                                <div class="form-check">
                                    <input type="hidden" name="ativo" value="0">
                                    <input class="form-check-input" type="checkbox" name="ativo" value="1" id="meAtivo" @checked($gatewayMe?->ativo)>
                                    <label class="form-check-label small" for="meAtivo">Ativo</label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @php($gatewayAsaas = \App\Models\EmpresaGateway::where('empresa_id', $empresa->id)->where('provedor', \App\Models\EmpresaGateway::PROVEDOR_ASAAS)->first())
        @php($gatewayUber = \App\Models\EmpresaGateway::where('empresa_id', $empresa->id)->where('provedor', \App\Models\EmpresaGateway::PROVEDOR_UBER_DIRECT)->first())
        <div class="row g-4 mb-1">
            {{-- Fase 2 (13/08/2026): cartão via link Asaas no pedido do agente --}}
            <div class="col-lg-6">
                <div class="card detail-card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-credit-card me-1"></i> Cartão do Vendedor IA (Asaas)</h6>
                        @if($gatewayAsaas?->ativo && filled($gatewayAsaas->client_secret))
                            <span class="badge bg-success bg-opacity-10 text-success">Ativo</span>
                        @elseif($gatewayAsaas?->ativo)
                            <span class="badge bg-warning bg-opacity-10 text-warning">Incompleto</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Inativo</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Com o Asaas ativo, o pedido do agente sai também com <strong>link de pagamento
                            no cartão</strong>; o pagamento confirma o pedido automaticamente (igual ao PIX).
                            Cadastre o webhook no painel do Asaas apontando para
                            <code>{{ url('/api/integracao/v1/webhooks/asaas') }}</code> com o token abaixo.
                        </p>
                        <form method="POST" action="{{ route('admin.empresas.gateway-asaas.store', $empresa) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-12">
                                <label class="form-label small mb-1">api_key</label>
                                <input type="password" name="api_key" class="form-control form-control-sm"
                                       placeholder="{{ $gatewayAsaas?->client_secret ? '•••• salva — preencha p/ trocar' : 'chave de API do Asaas ($aact_...)' }}">
                            </div>
                            <div class="col-auto form-check ms-2 mt-3">
                                <input type="hidden" name="sandbox" value="0">
                                <input class="form-check-input" type="checkbox" name="sandbox" value="1" id="asaasSandbox" @checked($gatewayAsaas?->config['sandbox'] ?? false)>
                                <label class="form-check-label small" for="asaasSandbox">Sandbox</label>
                            </div>
                            <div class="col-auto form-check ms-2 mt-3">
                                <input type="hidden" name="ativo" value="0">
                                <input class="form-check-input" type="checkbox" name="ativo" value="1" id="asaasAtivo" @checked($gatewayAsaas?->ativo)>
                                <label class="form-check-label small" for="asaasAtivo">Ativo</label>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                            </div>
                        </form>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                            <form method="POST" action="{{ route('admin.empresas.gateway-asaas.testar', $empresa) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(blank($gatewayAsaas?->client_secret))>
                                    <i class="bi bi-activity me-1"></i> Testar conexão
                                </button>
                            </form>
                            @if(filled($gatewayAsaas?->config['webhook_token'] ?? null))
                                <span class="small text-muted">Token do webhook: <code>{{ $gatewayAsaas->config['webhook_token'] }}</code></span>
                            @endif
                            @if($gatewayAsaas?->ultima_falha)
                                <span class="small text-danger"><i class="bi bi-x-circle me-1"></i>{{ $gatewayAsaas->ultima_falha }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            {{-- Fase 3 (13/08/2026): entrega local Uber Direct, credenciais POR EMPRESA (porte do China Mix) --}}
            <div class="col-lg-6">
                <div class="card detail-card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-scooter me-1"></i> Entrega — Uber Direct</h6>
                        @if($gatewayUber?->ativo && filled($gatewayUber->client_id) && filled($gatewayUber->config['customer_id'] ?? null))
                            <span class="badge bg-success bg-opacity-10 text-success">Ativo</span>
                        @elseif($gatewayUber?->ativo)
                            <span class="badge bg-warning bg-opacity-10 text-warning">Incompleto</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Inativo</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Pagamento confirmado → o sistema chama o Uber sozinho (coleta na loja do pedido,
                            entrega no endereço do cliente). Se o Uber falhar ou estiver fora da janela, o
                            pedido segue confirmado e o despacho fica manual. Cada empresa usa as
                            <strong>próprias credenciais</strong> do Uber Direct.
                        </p>
                        <form method="POST" action="{{ route('admin.empresas.gateway-uber.store', $empresa) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-6">
                                <label class="form-label small mb-1">ID de cliente do desenvolvedor (Client ID)</label>
                                <input type="text" name="client_id" class="form-control form-control-sm"
                                       placeholder="{{ $gatewayUber?->client_id ? '•••• salvo — preencha p/ trocar' : '32 letras/números, SEM traços — ex.: haH0Sezg…' }}">
                                <div class="form-text">No painel do Uber: <strong>"ID de cliente do desenvolvedor"</strong>.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Client Secret</label>
                                <input type="password" name="client_secret" class="form-control form-control-sm"
                                       placeholder="{{ $gatewayUber?->client_secret ? '•••• salvo — preencha p/ trocar' : 'Client Secret do painel (40 caracteres)' }}">
                                <div class="form-text">No painel do Uber: <strong>"Client Secret"</strong>.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">ID do usuário (Customer ID)</label>
                                <input type="text" name="customer_id" class="form-control form-control-sm"
                                       value="{{ $gatewayUber?->config['customer_id'] ?? '' }}" placeholder="UUID com traços — ex.: 56d97aa0-8311-…">
                                <div class="form-text">No painel do Uber: <strong>"ID do usuário"</strong> — o código que aparece na URL <code>/v1/customers/…/deliveries</code>.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Faixas de CEP atendidas</label>
                                <input type="text" name="ceps" class="form-control form-control-sm"
                                       value="{{ $gatewayUber?->config['ceps'] ?? '' }}" placeholder="64000-64099,65630-65639 (vazio = todos)">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Seg–sex de</label>
                                <input type="number" step="0.5" name="hora_inicio" class="form-control form-control-sm"
                                       value="{{ $gatewayUber?->config['hora_inicio'] ?? 8 }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">até</label>
                                <input type="number" step="0.5" name="hora_fim" class="form-control form-control-sm"
                                       value="{{ $gatewayUber?->config['hora_fim'] ?? 16.5 }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Sáb de</label>
                                <input type="number" step="0.5" name="hora_inicio_sab" class="form-control form-control-sm"
                                       value="{{ $gatewayUber?->config['hora_inicio_sab'] ?? 9 }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">até</label>
                                <input type="number" step="0.5" name="hora_fim_sab" class="form-control form-control-sm"
                                       value="{{ $gatewayUber?->config['hora_fim_sab'] ?? 12 }}">
                            </div>
                            <div class="col-auto form-check ms-2 mt-3">
                                <input type="hidden" name="ativo" value="0">
                                <input class="form-check-input" type="checkbox" name="ativo" value="1" id="uberAtivo" @checked($gatewayUber?->ativo)>
                                <label class="form-check-label small" for="uberAtivo">Ativo</label>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                            </div>
                        </form>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                            <form method="POST" action="{{ route('admin.empresas.gateway-uber.testar', $empresa) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(blank($gatewayUber?->client_id))>
                                    <i class="bi bi-activity me-1"></i> Testar conexão
                                </button>
                            </form>
                            @if($gatewayUber?->ultima_falha)
                                <span class="small text-danger"><i class="bi bi-x-circle me-1"></i>{{ $gatewayUber->ultima_falha }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card detail-card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-plug me-1"></i> API de Integração (Gersen)</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Tokens de <strong>leitura</strong> para o Gersen importar vendas, lojas e
                            vendedores desta empresa (<code>/api/integracao/v1</code>). O token aparece
                            <strong>uma única vez</strong>, logo após ser gerado — copie e cole no
                            assistente de conexão do Gersen.
                        </p>

                        @if(session('novo_token'))
                        <div class="alert alert-warning">
                            <div class="fw-semibold mb-2">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Copie o token agora — ele não será exibido de novo:
                            </div>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="novoTokenValor"
                                       value="{{ session('novo_token') }}" readonly onclick="this.select()">
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('novoTokenValor').value); this.innerText='Copiado!'">
                                    <i class="bi bi-clipboard me-1"></i>Copiar
                                </button>
                            </div>
                        </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table align-middle table-details mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Nome</th>
                                        <th>Criado em</th>
                                        <th>Último uso</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($empresa->integracaoTokens->sortByDesc('created_at') as $token)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $token->nome }}</td>
                                        <td class="text-muted small">{{ $token->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="text-muted small">
                                            @if($token->last_used_at)
                                                {{ $token->last_used_at->format('d/m/Y H:i') }}
                                                <span class="opacity-75">({{ $token->last_used_ip }})</span>
                                            @else
                                                Nunca usado
                                            @endif
                                        </td>
                                        <td>
                                            @if($token->ativo)
                                                <span class="badge bg-success bg-opacity-10 text-success">Ativo</span>
                                            @else
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary">Revogado</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if($token->ativo)
                                            <form method="POST"
                                                  action="{{ route('admin.empresas.integracao-tokens.revogar', [$empresa, $token]) }}"
                                                  onsubmit="return confirm('Revogar este token? O Gersen para de sincronizar na hora.')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Revogar</button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">Nenhum token gerado.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card detail-card shadow-sm">
                    <div class="card-header"><h6 class="mb-0">Gerar token novo</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.empresas.integracao-tokens.store', $empresa) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label detail-label">Nome</label>
                                <input type="text" name="nome" class="form-control" value="Gersen" maxlength="80">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-key me-1"></i> Gerar token
                            </button>
                        </form>
                        <p class="text-muted small mt-3 mb-0">
                            Acesso somente leitura (vendas, lojas, vendedores, situações), escopado a
                            esta empresa. Cada request fica registrada em
                            <code>storage/logs/integracao-*.log</code>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('abrir_integracao'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Tab(document.getElementById('integracao-tab')).show();
    });
</script>
@endif
@endsection
