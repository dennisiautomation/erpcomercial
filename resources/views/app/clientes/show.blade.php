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
<div class="client-header mb-4 shadow-sm fade-in">
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
                    <x-erp.status-badge :status="$cliente->status" />
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
        <x-erp.card title="Identificacao" icon="person-badge">
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
        </x-erp.card>
    </div>

    <div class="col-md-4">
        <x-erp.card title="Endereco" icon="geo-alt">
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
        </x-erp.card>
    </div>

    <div class="col-md-4">
        <x-erp.card title="Contato" icon="telephone">
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
        </x-erp.card>
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
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>N.</th>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Vendedor</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
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
                                @php $sv = $venda->status->value ?? $venda->status; @endphp
                                <x-erp.status-badge :status="$sv" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-erp.empty-state icon="cart" title="Nenhuma venda registrada para este cliente" />
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
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Descricao</th>
                        <th>Vencimento</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Pago</th>
                        <th class="text-center">Status</th>
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
                                    $crStatus = $isVencido ? 'vencida' : $conta->status;
                                @endphp
                                <x-erp.status-badge :status="$crStatus" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-erp.empty-state icon="wallet2" title="Nenhuma conta a receber registrada" />
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
                <x-erp.empty-state icon="chat-text" title="Nenhuma observacao registrada" />
            @endif
        </div>
    </div>
</div>
@endsection
