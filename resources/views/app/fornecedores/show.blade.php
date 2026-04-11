@extends('layouts.app')

@section('title', 'Detalhes do Fornecedor')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-truck me-2"></i>{{ $fornecedore->razao_social }}</h4>
        @if($fornecedore->nome_fantasia)
            <small class="text-muted">{{ $fornecedore->nome_fantasia }}</small>
        @endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('app.fornecedores.edit', $fornecedore) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('app.fornecedores.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- Identificacao --}}
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Identificacao</h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted d-block">CPF/CNPJ</small>
                    <strong><code>{{ $fornecedore->cpf_cnpj }}</code></strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Razao Social</small>
                    <strong>{{ $fornecedore->razao_social }}</strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Nome Fantasia</small>
                    <span>{{ $fornecedore->nome_fantasia ?: '-' }}</span>
                </div>
                <div class="mb-0">
                    <small class="text-muted d-block">Contato / Representante</small>
                    <span>{{ $fornecedore->contato_representante ?: '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Endereco --}}
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Endereco</h6>
            </div>
            <div class="card-body">
                @if($fornecedore->logradouro)
                    <p class="mb-1">
                        <i class="bi bi-geo-alt text-muted me-1"></i>
                        {{ $fornecedore->logradouro }}{{ $fornecedore->numero ? ', ' . $fornecedore->numero : '' }}
                    </p>
                    @if($fornecedore->complemento)
                        <p class="mb-1 ms-4">{{ $fornecedore->complemento }}</p>
                    @endif
                    <p class="mb-1 ms-4">{{ $fornecedore->bairro }}</p>
                    <p class="mb-1 ms-4">
                        <strong>{{ $fornecedore->cidade }}</strong>{{ $fornecedore->uf ? '/' . $fornecedore->uf : '' }}
                    </p>
                    @if($fornecedore->cep)
                        <p class="mb-0 ms-4">
                            <span class="badge bg-light text-dark">CEP: {{ $fornecedore->cep }}</span>
                        </p>
                    @endif
                @else
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle me-1"></i>Endereco nao informado
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Contato --}}
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-telephone me-2"></i>Contato</h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted d-block">Telefone</small>
                    @if($fornecedore->telefone)
                        <i class="bi bi-telephone me-1"></i>{{ $fornecedore->telefone }}
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">E-mail</small>
                    @if($fornecedore->email)
                        <i class="bi bi-envelope me-1"></i>
                        <a href="mailto:{{ $fornecedore->email }}">{{ $fornecedore->email }}</a>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </div>
                @if($fornecedore->condicoes_comerciais)
                    <hr>
                    <div class="mb-0">
                        <small class="text-muted d-block mb-1">Condicoes Comerciais</small>
                        <span>{{ $fornecedore->condicoes_comerciais }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Contas a Pagar --}}
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Ultimas Contas a Pagar</h6>
        <span class="badge bg-secondary">{{ $fornecedore->contasPagar->count() }} registro(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Descricao</th>
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
                            <td class="text-end fw-bold">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $conta->status === 'pago' ? 'success' : ($conta->vencimento->isPast() ? 'danger' : 'warning') }}">
                                    {{ $conta->status === 'pendente' && $conta->vencimento->isPast() ? 'Vencido' : ucfirst($conta->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="bi bi-check-circle fs-4 d-block mb-1"></i>
                                Nenhuma conta a pagar registrada
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
