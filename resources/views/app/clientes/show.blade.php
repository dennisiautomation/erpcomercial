@extends('layouts.app')

@section('title', 'Detalhes do Cliente')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-person me-2"></i>{{ $cliente->nome_razao_social }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('app.clientes.edit', $cliente) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('app.clientes.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Info Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-person-badge me-1"></i> Identificação</h6>
                <p class="mb-1"><strong>Tipo:</strong> {{ $cliente->tipo_pessoa === 'PF' ? 'Pessoa Física' : 'Pessoa Jurídica' }}</p>
                <p class="mb-1"><strong>{{ $cliente->tipo_pessoa === 'PF' ? 'CPF' : 'CNPJ' }}:</strong> {{ $cliente->cpf_cnpj }}</p>
                @if($cliente->tipo_pessoa === 'PJ')
                    <p class="mb-1"><strong>Nome Fantasia:</strong> {{ $cliente->nome_fantasia ?: '-' }}</p>
                    <p class="mb-1"><strong>IE:</strong> {{ $cliente->ie ?: '-' }}</p>
                @endif
                <p class="mb-0">
                    <strong>Status:</strong>
                    <span class="badge bg-{{ $cliente->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($cliente->status) }}</span>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-geo-alt me-1"></i> Endereço</h6>
                @if($cliente->logradouro)
                    <p class="mb-1">{{ $cliente->logradouro }}, {{ $cliente->numero }}</p>
                    @if($cliente->complemento)
                        <p class="mb-1">{{ $cliente->complemento }}</p>
                    @endif
                    <p class="mb-1">{{ $cliente->bairro }}</p>
                    <p class="mb-1">{{ $cliente->cidade }}/{{ $cliente->uf }} - CEP: {{ $cliente->cep }}</p>
                @else
                    <p class="text-muted mb-0">Endereço não informado</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-telephone me-1"></i> Contato</h6>
                <p class="mb-1"><strong>Telefone:</strong> {{ $cliente->telefone ?: '-' }}</p>
                <p class="mb-1"><strong>WhatsApp:</strong> {{ $cliente->whatsapp ?: '-' }}</p>
                <p class="mb-1"><strong>E-mail:</strong> {{ $cliente->email ?: '-' }}</p>
                <hr>
                <p class="mb-0"><strong>Limite de Crédito:</strong> R$ {{ number_format($cliente->limite_credito ?? 0, 2, ',', '.') }}</p>
            </div>
        </div>
    </div>
</div>

{{-- Tabs --}}
<ul class="nav nav-tabs" id="clienteTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados" type="button" role="tab">
            <i class="bi bi-info-circle me-1"></i> Dados
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="vendas-tab" data-bs-toggle="tab" data-bs-target="#vendas" type="button" role="tab">
            <i class="bi bi-cart me-1"></i> Vendas
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="financeiro-tab" data-bs-toggle="tab" data-bs-target="#financeiro" type="button" role="tab">
            <i class="bi bi-wallet2 me-1"></i> Financeiro
        </button>
    </li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom bg-white p-4" id="clienteTabsContent">
    {{-- Tab: Dados --}}
    <div class="tab-pane fade show active" id="dados" role="tabpanel">
        <div class="row">
            <div class="col-md-12">
                <h6>Observações</h6>
                <p>{{ $cliente->observacoes ?: 'Nenhuma observação registrada.' }}</p>
                <hr>
                <small class="text-muted">
                    Cadastrado em {{ $cliente->created_at->format('d/m/Y H:i') }}
                    | Última atualização: {{ $cliente->updated_at->format('d/m/Y H:i') }}
                </small>
            </div>
        </div>
    </div>

    {{-- Tab: Vendas --}}
    <div class="tab-pane fade" id="vendas" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nº</th>
                        <th>Data</th>
                        <th>Vendedor</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cliente->vendas as $venda)
                        <tr>
                            <td>{{ $venda->numero }}</td>
                            <td>{{ $venda->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $venda->vendedor->name ?? '-' }}</td>
                            <td class="text-end">R$ {{ number_format($venda->total, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @php
                                    $statusColors = ['finalizada' => 'success', 'pendente' => 'warning', 'cancelada' => 'danger'];
                                    $sv = $venda->status->value ?? $venda->status;
                                    $color = $statusColors[$sv] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $color }}">{{ ucfirst($sv) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Nenhuma venda registrada</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tab: Financeiro --}}
    <div class="tab-pane fade" id="financeiro" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Descrição</th>
                        <th>Vencimento</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Pago</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cliente->contasReceber as $conta)
                        <tr>
                            <td>{{ $conta->descricao }}</td>
                            <td>{{ $conta->vencimento->format('d/m/Y') }}</td>
                            <td class="text-end">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($conta->valor_pago ?? 0, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @php
                                    $statusCR = [
                                        'pendente' => $conta->vencimento->isPast() ? 'danger' : 'warning',
                                        'pago' => 'success',
                                        'cancelado' => 'secondary',
                                    ];
                                    $crColor = $statusCR[$conta->status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $crColor }}">
                                    {{ $conta->status === 'pendente' && $conta->vencimento->isPast() ? 'Vencido' : ucfirst($conta->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Nenhuma conta a receber registrada</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
