@extends('layouts.app')

@section('title', 'Detalhes do Cliente')

@push('styles')
<style>
    .client-header {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        border-radius: 16px;
        color: #fff;
        padding: 28px 32px;
        position: relative;
        overflow: hidden;
    }
    .client-header::after {
        content: '';
        position: absolute;
        top: -40%;
        right: -10%;
        width: 250px;
        height: 250px;
        background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
        border-radius: 50%;
    }
    .client-avatar {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .stat-mini {
        text-align: center;
        padding: 16px 12px;
        border-radius: 12px;
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(4px);
    }
    .stat-mini .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
    }
    .stat-mini .stat-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.7;
    }
    .info-card {
        border: none;
        border-radius: 12px;
    }
    .info-item {
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .info-item:last-child { border-bottom: none; }
    .info-item .info-label {
        font-size: 0.82rem;
        color: #64748b;
        font-weight: 500;
    }
    .info-item .info-value {
        font-weight: 600;
        color: #1e293b;
    }
    .tab-custom .nav-link {
        border: none;
        color: #64748b;
        font-weight: 500;
        padding: 12px 20px;
        border-radius: 8px 8px 0 0;
        transition: all 0.15s;
    }
    .tab-custom .nav-link:hover { color: #1e293b; background: #f8fafc; }
    .tab-custom .nav-link.active {
        color: #2563eb;
        background: #fff;
        border-bottom: 3px solid #2563eb;
        font-weight: 600;
    }
    .tab-content-custom {
        background: #fff;
        border-radius: 0 0 12px 12px;
        border: 1px solid #e2e8f0;
        border-top: none;
    }
    .venda-row { transition: background 0.15s; }
    .venda-row:hover { background: #f8fafc !important; }
</style>
@endpush

@section('content')
{{-- Client Header --}}
<div class="client-header mb-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-start">
        <div class="d-flex align-items-center gap-3">
            <div class="client-avatar" style="background: {{ $cliente->tipo_pessoa === 'pj' ? 'rgba(139,92,246,0.3)' : 'rgba(56,189,248,0.3)' }};">
                {{ strtoupper(substr($cliente->nome_razao_social, 0, 2)) }}
            </div>
            <div>
                <h4 class="fw-bold mb-1">{{ $cliente->nome_razao_social }}</h4>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-light bg-opacity-10 border border-light border-opacity-25 px-3 py-1">
                        {{ $cliente->tipo_pessoa === 'pf' ? 'Pessoa Fisica' : 'Pessoa Juridica' }}
                    </span>
                    @if($cliente->nome_fantasia)
                        <span class="opacity-75">{{ $cliente->nome_fantasia }}</span>
                    @endif
                    @php
                        $statusMap = [
                            'ativo'     => ['bg' => 'success', 'label' => 'Ativo'],
                            'inativo'   => ['bg' => 'secondary', 'label' => 'Inativo'],
                            'bloqueado' => ['bg' => 'danger', 'label' => 'Bloqueado'],
                        ];
                        $st = $statusMap[$cliente->status] ?? ['bg' => 'secondary', 'label' => ucfirst($cliente->status)];
                    @endphp
                    <span class="badge bg-{{ $st['bg'] }} px-3 py-1">{{ $st['label'] }}</span>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('app.clientes.edit', $cliente) }}" class="btn btn-light btn-sm rounded-pill px-3">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
            <a href="{{ route('app.clientes.index') }}" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>

    {{-- Mini Stats --}}
    <div class="row g-3 mt-3">
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="stat-value">{{ $totalCompras }}</div>
                <div class="stat-label">Total Compras</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="stat-value">R$ {{ number_format($valorTotalCompras, 2, ',', '.') }}</div>
                <div class="stat-label">Valor Total</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="stat-value">{{ $ultimaCompra ? $ultimaCompra->created_at->format('d/m/Y') : '-' }}</div>
                <div class="stat-label">Ultima Compra</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="stat-value {{ $saldoDevedor > 0 ? 'text-warning' : '' }}">R$ {{ number_format($saldoDevedor, 2, ',', '.') }}</div>
                <div class="stat-label">Saldo Devedor</div>
            </div>
        </div>
    </div>
</div>

{{-- Info Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card info-card shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold text-muted mb-3 small text-uppercase">
                    <i class="bi bi-person-badge me-1 text-primary"></i> Identificacao
                </h6>
                <div class="info-item">
                    <span class="info-label">{{ $cliente->tipo_pessoa === 'pf' ? 'CPF' : 'CNPJ' }}</span>
                    <span class="info-value">{{ $cliente->cpf_cnpj }}</span>
                </div>
                @if($cliente->tipo_pessoa === 'pj')
                    <div class="info-item">
                        <span class="info-label">Nome Fantasia</span>
                        <span class="info-value">{{ $cliente->nome_fantasia ?: '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">IE</span>
                        <span class="info-value">{{ $cliente->ie ?: 'Isento' }}</span>
                    </div>
                @endif
                <div class="info-item">
                    <span class="info-label">Limite de Credito</span>
                    <span class="info-value text-success">R$ {{ number_format($cliente->limite_credito ?? 0, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card info-card shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold text-muted mb-3 small text-uppercase">
                    <i class="bi bi-geo-alt me-1 text-danger"></i> Endereco
                </h6>
                @if($cliente->logradouro)
                    <div class="info-item">
                        <span class="info-label">Logradouro</span>
                        <span class="info-value">{{ $cliente->logradouro }}, {{ $cliente->numero }}</span>
                    </div>
                    @if($cliente->complemento)
                        <div class="info-item">
                            <span class="info-label">Complemento</span>
                            <span class="info-value">{{ $cliente->complemento }}</span>
                        </div>
                    @endif
                    <div class="info-item">
                        <span class="info-label">Bairro</span>
                        <span class="info-value">{{ $cliente->bairro }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Cidade/UF</span>
                        <span class="info-value">{{ $cliente->cidade }}/{{ $cliente->uf }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">CEP</span>
                        <span class="info-value">{{ $cliente->cep }}</span>
                    </div>
                @else
                    <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
                        <i class="bi bi-geo fs-2 opacity-50 mb-2"></i>
                        <span class="small">Endereco nao informado</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card info-card shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold text-muted mb-3 small text-uppercase">
                    <i class="bi bi-telephone me-1 text-success"></i> Contato
                </h6>
                <div class="info-item">
                    <span class="info-label">Telefone</span>
                    <span class="info-value">{{ $cliente->telefone ?: '-' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">WhatsApp</span>
                    <span class="info-value">
                        @if($cliente->whatsapp)
                            <a href="https://wa.me/55{{ preg_replace('/\D/', '', $cliente->whatsapp) }}" target="_blank" class="text-success text-decoration-none">
                                <i class="bi bi-whatsapp me-1"></i>{{ $cliente->whatsapp }}
                            </a>
                        @else
                            -
                        @endif
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">E-mail</span>
                    <span class="info-value">
                        @if($cliente->email)
                            <a href="mailto:{{ $cliente->email }}" class="text-decoration-none">{{ $cliente->email }}</a>
                        @else
                            -
                        @endif
                    </span>
                </div>
                <hr class="my-2">
                <small class="text-muted">
                    Cadastrado em {{ $cliente->created_at->format('d/m/Y H:i') }}<br>
                    Atualizado em {{ $cliente->updated_at->format('d/m/Y H:i') }}
                </small>
            </div>
        </div>
    </div>
</div>

{{-- Tabs --}}
<ul class="nav nav-tabs tab-custom border-bottom" id="clienteTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="vendas-tab" data-bs-toggle="tab" data-bs-target="#vendas" type="button" role="tab">
            <i class="bi bi-cart me-1"></i> Historico de Vendas
            <span class="badge bg-primary bg-opacity-10 text-primary ms-1">{{ $cliente->vendas->count() }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="financeiro-tab" data-bs-toggle="tab" data-bs-target="#financeiro" type="button" role="tab">
            <i class="bi bi-wallet2 me-1"></i> Financeiro
            @if($saldoDevedor > 0)
                <span class="badge bg-warning bg-opacity-10 text-warning ms-1">{{ $cliente->contasReceber->where('status', 'pendente')->count() }}</span>
            @endif
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="obs-tab" data-bs-toggle="tab" data-bs-target="#observacoes" type="button" role="tab">
            <i class="bi bi-chat-text me-1"></i> Observacoes
        </button>
    </li>
</ul>

<div class="tab-content tab-content-custom p-4" id="clienteTabsContent">
    {{-- Tab: Vendas --}}
    <div class="tab-pane fade show active" id="vendas" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th class="py-3 fw-semibold text-muted small text-uppercase">N.</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Data</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Tipo</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Vendedor</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase text-end">Total</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cliente->vendas as $venda)
                        <tr class="venda-row">
                            <td><span class="fw-bold text-primary">#{{ $venda->id }}</span></td>
                            <td>
                                <div>{{ $venda->created_at->format('d/m/Y') }}</div>
                                <small class="text-muted">{{ $venda->created_at->format('H:i') }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">{{ ucfirst($venda->tipo ?? 'venda') }}</span>
                            </td>
                            <td class="text-muted">{{ $venda->vendedor->name ?? '-' }}</td>
                            <td class="text-end fw-bold">R$ {{ number_format($venda->total, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @php
                                    $sv = $venda->status->value ?? $venda->status;
                                    $statusColors = [
                                        'finalizada' => ['bg' => 'success', 'icon' => 'check-circle'],
                                        'pendente'   => ['bg' => 'warning', 'icon' => 'clock'],
                                        'cancelada'  => ['bg' => 'danger', 'icon' => 'x-circle'],
                                    ];
                                    $sc = $statusColors[$sv] ?? ['bg' => 'secondary', 'icon' => 'question-circle'];
                                @endphp
                                <span class="badge bg-{{ $sc['bg'] }} bg-opacity-10 text-{{ $sc['bg'] }} px-3 py-2 rounded-pill">
                                    <i class="bi bi-{{ $sc['icon'] }} me-1"></i>{{ ucfirst($sv) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-cart fs-1 d-block mb-2 opacity-25"></i>
                                <p class="text-muted mb-0">Nenhuma venda registrada para este cliente</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tab: Financeiro --}}
    <div class="tab-pane fade" id="financeiro" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Descricao</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Vencimento</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase text-end">Valor</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase text-end">Pago</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cliente->contasReceber as $conta)
                        <tr class="venda-row">
                            <td class="fw-semibold">{{ $conta->descricao }}</td>
                            <td>
                                {{ $conta->vencimento->format('d/m/Y') }}
                                @if($conta->status === 'pendente' && $conta->vencimento->isPast())
                                    <br><small class="text-danger fw-semibold">{{ $conta->vencimento->diffForHumans() }}</small>
                                @endif
                            </td>
                            <td class="text-end fw-bold">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($conta->valor_pago ?? 0, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @php
                                    $isVencido = $conta->status === 'pendente' && $conta->vencimento->isPast();
                                    $statusCR = [
                                        'pendente'  => $isVencido ? 'danger' : 'warning',
                                        'pago'      => 'success',
                                        'cancelado' => 'secondary',
                                    ];
                                    $crColor = $statusCR[$conta->status] ?? 'secondary';
                                    $crLabel = $isVencido ? 'Vencido' : ucfirst($conta->status);
                                @endphp
                                <span class="badge bg-{{ $crColor }} bg-opacity-10 text-{{ $crColor }} px-3 py-2 rounded-pill">
                                    {{ $crLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="bi bi-wallet2 fs-1 d-block mb-2 opacity-25"></i>
                                <p class="text-muted mb-0">Nenhuma conta a receber registrada</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tab: Observacoes --}}
    <div class="tab-pane fade" id="observacoes" role="tabpanel">
        <div class="py-2">
            @if($cliente->observacoes)
                <div class="bg-light rounded-3 p-4">
                    <p class="mb-0" style="white-space: pre-line;">{{ $cliente->observacoes }}</p>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-chat-text fs-1 d-block mb-2 opacity-25"></i>
                    <p class="text-muted mb-0">Nenhuma observacao registrada</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
