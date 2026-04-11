@extends('layouts.app')

@section('title', 'Detalhes do Servico')

@section('content')
<x-erp.page-header title="{{ $servico->descricao }}" icon="tools">
    <a href="{{ route('app.servicos.edit', $servico) }}" class="btn btn-erp-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    <a href="{{ route('app.servicos.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</x-erp.page-header>

<div class="row g-3">
    <div class="col-md-6">
        <x-erp.card title="Dados Gerais" icon="info-circle">
            <p class="mb-1"><strong>Codigo:</strong> {{ $servico->codigo ?: '-' }}</p>
            <p class="mb-1"><strong>Descricao:</strong> {{ $servico->descricao }}</p>
            <p class="mb-1"><strong>Valor Padrao:</strong> <span class="fs-5 fw-bold text-success">R$ {{ number_format($servico->valor_padrao, 2, ',', '.') }}</span></p>
            <p class="mb-0">
                <strong>Status:</strong>
                <x-erp.status-badge :status="$servico->status" />
            </p>
        </x-erp.card>
    </div>
    <div class="col-md-6">
        <x-erp.card title="Dados Fiscais" icon="file-earmark-text">
            <p class="mb-1"><strong>Cod. Servico Municipal:</strong> {{ $servico->codigo_servico_municipal ?: '-' }}</p>
            <p class="mb-1"><strong>CNAE:</strong> {{ $servico->cnae ?: '-' }}</p>
            <p class="mb-0"><strong>ISS:</strong> {{ $servico->iss_aliquota ? number_format($servico->iss_aliquota, 2, ',', '.') . '%' : '-' }}</p>
        </x-erp.card>
    </div>
</div>
@endsection
