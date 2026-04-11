@extends('layouts.app')

@section('title', 'Detalhes do Funcionário')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-person-fill me-2"></i>{{ $funcionario->name }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('app.funcionarios.edit', $funcionario) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('app.funcionarios.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-person me-1"></i> Dados Pessoais</h6>
                <p class="mb-1"><strong>Nome:</strong> {{ $funcionario->name }}</p>
                <p class="mb-1"><strong>E-mail:</strong> {{ $funcionario->email }}</p>
                <p class="mb-1"><strong>CPF:</strong> {{ $funcionario->cpf ?: '-' }}</p>
                <p class="mb-0"><strong>Telefone:</strong> {{ $funcionario->telefone ?: '-' }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-shield-lock me-1"></i> Acesso</h6>
                <p class="mb-1">
                    <strong>Perfil:</strong>
                    <span class="badge bg-primary">{{ $funcionario->perfil?->label() ?? '-' }}</span>
                </p>
                <p class="mb-1"><strong>Comissão:</strong> {{ $funcionario->comissao_percentual ? number_format($funcionario->comissao_percentual, 2, ',', '.') . '%' : '-' }}</p>
                <p class="mb-0">
                    <strong>Status:</strong>
                    <span class="badge bg-{{ $funcionario->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($funcionario->status) }}</span>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-building me-1"></i> Unidades</h6>
                @if($funcionario->unidades->isEmpty())
                    <p class="text-muted mb-0">Nenhuma unidade vinculada</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($funcionario->unidades as $unidade)
                            <li class="list-group-item px-0">{{ $unidade->nome }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="text-muted small">
    Cadastrado em {{ $funcionario->created_at->format('d/m/Y H:i') }}
    | Última atualização: {{ $funcionario->updated_at->format('d/m/Y H:i') }}
</div>
@endsection
