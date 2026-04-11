@extends('layouts.app')

@section('title', 'Detalhes do Fornecedor')

@section('content')
<x-erp.page-header title="{{ $fornecedore->razao_social }}" subtitle="{{ $fornecedore->nome_fantasia }}" icon="truck">
    <a href="{{ route('app.fornecedores.edit', $fornecedore) }}" class="btn btn-erp-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    <a href="{{ route('app.fornecedores.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</x-erp.page-header>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <x-erp.card title="Identificacao" icon="person-badge">
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
        </x-erp.card>
    </div>

    <div class="col-md-4">
        <x-erp.card title="Endereco" icon="geo-alt">
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
                    <p class="mb-0 ms-4"><span class="badge bg-light text-dark">CEP: {{ $fornecedore->cep }}</span></p>
                @endif
            @else
                <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>Endereco nao informado</p>
            @endif
        </x-erp.card>
    </div>

    <div class="col-md-4">
        <x-erp.card title="Contato" icon="telephone">
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
                    <i class="bi bi-envelope me-1"></i><a href="mailto:{{ $fornecedore->email }}">{{ $fornecedore->email }}</a>
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
        </x-erp.card>
    </div>
</div>

<x-erp.card title="Ultimas Contas a Pagar" icon="wallet2">
    <span class="badge bg-secondary float-end">{{ $fornecedore->contasPagar->count() }} registro(s)</span>
    <div class="table-responsive mt-3">
        <table class="erp-table">
            <thead>
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
                            <x-erp.status-badge :status="$conta->status === 'pendente' && $conta->vencimento->isPast() ? 'vencida' : $conta->status" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><x-erp.empty-state title="Nenhuma conta a pagar registrada" icon="check-circle" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-erp.card>
@endsection
