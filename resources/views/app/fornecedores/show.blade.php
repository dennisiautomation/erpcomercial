@extends('layouts.app')

@section('title', 'Detalhes do Fornecedor')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-truck me-2"></i>{{ $fornecedore->nome_razao_social }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('app.fornecedores.edit', $fornecedore) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('app.fornecedores.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-person-badge me-1"></i> Identificação</h6>
                <p class="mb-1"><strong>Tipo:</strong> {{ $fornecedore->tipo_pessoa === 'PF' ? 'Pessoa Física' : 'Pessoa Jurídica' }}</p>
                <p class="mb-1"><strong>{{ $fornecedore->tipo_pessoa === 'PF' ? 'CPF' : 'CNPJ' }}:</strong> {{ $fornecedore->cpf_cnpj }}</p>
                @if($fornecedore->tipo_pessoa === 'PJ')
                    <p class="mb-1"><strong>Nome Fantasia:</strong> {{ $fornecedore->nome_fantasia ?: '-' }}</p>
                    <p class="mb-1"><strong>IE:</strong> {{ $fornecedore->ie ?: '-' }}</p>
                @endif
                <p class="mb-0">
                    <strong>Status:</strong>
                    <span class="badge bg-{{ $fornecedore->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($fornecedore->status) }}</span>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-geo-alt me-1"></i> Endereço</h6>
                @if($fornecedore->logradouro)
                    <p class="mb-1">{{ $fornecedore->logradouro }}, {{ $fornecedore->numero }}</p>
                    @if($fornecedore->complemento) <p class="mb-1">{{ $fornecedore->complemento }}</p> @endif
                    <p class="mb-1">{{ $fornecedore->bairro }}</p>
                    <p class="mb-0">{{ $fornecedore->cidade }}/{{ $fornecedore->uf }} - CEP: {{ $fornecedore->cep }}</p>
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
                <p class="mb-1"><strong>Telefone:</strong> {{ $fornecedore->telefone ?: '-' }}</p>
                <p class="mb-1"><strong>WhatsApp:</strong> {{ $fornecedore->whatsapp ?: '-' }}</p>
                <p class="mb-0"><strong>E-mail:</strong> {{ $fornecedore->email ?: '-' }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Contas a Pagar</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Descrição</th>
                        <th>Vencimento</th>
                        <th class="text-end">Valor</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fornecedore->contasPagar as $conta)
                        <tr>
                            <td>{{ $conta->descricao }}</td>
                            <td>{{ $conta->vencimento->format('d/m/Y') }}</td>
                            <td class="text-end">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $conta->status === 'pago' ? 'success' : ($conta->vencimento->isPast() ? 'danger' : 'warning') }}">
                                    {{ $conta->status === 'pendente' && $conta->vencimento->isPast() ? 'Vencido' : ucfirst($conta->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">Nenhuma conta a pagar registrada</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
