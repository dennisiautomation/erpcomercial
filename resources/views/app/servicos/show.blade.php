@extends('layouts.app')

@section('title', 'Detalhes do Serviço')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-tools me-2"></i>{{ $servico->descricao }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('app.servicos.edit', $servico) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3">Dados Gerais</h6>
                <p class="mb-1"><strong>Código:</strong> {{ $servico->codigo ?: '-' }}</p>
                <p class="mb-1"><strong>Descrição:</strong> {{ $servico->descricao }}</p>
                <p class="mb-1"><strong>Valor Padrão:</strong> <span class="fs-5 fw-bold text-success">R$ {{ number_format($servico->valor_padrao, 2, ',', '.') }}</span></p>
                <p class="mb-0">
                    <strong>Status:</strong>
                    <span class="badge bg-{{ $servico->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($servico->status) }}</span>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3">Dados Fiscais</h6>
                <p class="mb-1"><strong>Cód. Serviço Municipal:</strong> {{ $servico->codigo_servico_municipal ?: '-' }}</p>
                <p class="mb-1"><strong>CNAE:</strong> {{ $servico->cnae ?: '-' }}</p>
                <p class="mb-0"><strong>ISS:</strong> {{ $servico->iss_aliquota ? number_format($servico->iss_aliquota, 2, ',', '.') . '%' : '-' }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
