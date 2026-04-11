@extends('layouts.app')

@section('title', 'Detalhes do Funcionario')

@section('content')
<x-erp.page-header title="{{ $funcionario->name }}" icon="person-fill">
    <a href="{{ route('app.funcionarios.edit', $funcionario) }}" class="btn btn-erp-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    <a href="{{ route('app.funcionarios.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</x-erp.page-header>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <x-erp.card title="Dados Pessoais" icon="person">
            <p class="mb-1"><strong>Nome:</strong> {{ $funcionario->name }}</p>
            <p class="mb-1"><strong>E-mail:</strong> {{ $funcionario->email }}</p>
            <p class="mb-1"><strong>CPF:</strong> {{ $funcionario->cpf ?: '-' }}</p>
            <p class="mb-0"><strong>Telefone:</strong> {{ $funcionario->telefone ?: '-' }}</p>
        </x-erp.card>
    </div>
    <div class="col-md-4">
        <x-erp.card title="Acesso" icon="shield-lock">
            <p class="mb-1">
                <strong>Perfil:</strong>
                <span class="badge bg-primary">{{ $funcionario->perfil?->label() ?? '-' }}</span>
            </p>
            <p class="mb-1"><strong>Comissao:</strong> {{ $funcionario->comissao_percentual ? number_format($funcionario->comissao_percentual, 2, ',', '.') . '%' : '-' }}</p>
            <p class="mb-0">
                <strong>Status:</strong>
                <x-erp.status-badge :status="$funcionario->status" />
            </p>
        </x-erp.card>
    </div>
    <div class="col-md-4">
        <x-erp.card title="Unidades" icon="building">
            @if($funcionario->unidades->isEmpty())
                <p class="text-muted mb-0">Nenhuma unidade vinculada</p>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($funcionario->unidades as $unidade)
                        <li class="list-group-item px-0">{{ $unidade->nome }}</li>
                    @endforeach
                </ul>
            @endif
        </x-erp.card>
    </div>
</div>

<div class="text-muted small">
    Cadastrado em {{ $funcionario->created_at->format('d/m/Y H:i') }}
    | Ultima atualizacao: {{ $funcionario->updated_at->format('d/m/Y H:i') }}
</div>
@endsection
