@extends('layouts.app')

@section('title', 'Detalhes da Movimentacao')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-eye me-2"></i>Movimentacao #{{ $movimentacao->id }}</h4>
    <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Data:</strong><br>
                        {{ $movimentacao->created_at->format('d/m/Y H:i:s') }}
                    </div>
                    <div class="col-md-6">
                        <strong>Tipo:</strong><br>
                        @php
                            $badgeClass = match($movimentacao->tipo->value) {
                                'entrada', 'devolucao' => 'bg-success',
                                'saida', 'perda' => 'bg-danger',
                                'ajuste' => 'bg-warning text-dark',
                                'bonificacao' => 'bg-info',
                                'transferencia' => 'bg-primary',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }} fs-6">{{ $movimentacao->tipo->label() }}</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Produto:</strong><br>
                        {{ $movimentacao->produto->descricao ?? '-' }}
                    </div>
                    <div class="col-md-6">
                        <strong>Quantidade:</strong><br>
                        {{ number_format($movimentacao->quantidade, 3, ',', '.') }}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Estoque Anterior:</strong><br>
                        {{ number_format($movimentacao->quantidade_anterior, 3, ',', '.') }}
                    </div>
                    <div class="col-md-6">
                        <strong>Estoque Posterior:</strong><br>
                        {{ number_format($movimentacao->quantidade_posterior, 3, ',', '.') }}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Custo Unitario:</strong><br>
                        R$ {{ number_format($movimentacao->custo_unitario, 2, ',', '.') }}
                    </div>
                    <div class="col-md-6">
                        <strong>Usuario:</strong><br>
                        {{ $movimentacao->user->name ?? '-' }}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Unidade:</strong><br>
                        {{ $movimentacao->unidade->nome ?? '-' }}
                    </div>
                    <div class="col-md-6">
                        <strong>Origem:</strong><br>
                        @if($movimentacao->origem_tipo)
                            {{ class_basename($movimentacao->origem_tipo) }} #{{ $movimentacao->origem_id }}
                        @else
                            Entrada Manual
                        @endif
                    </div>
                </div>

                @if($movimentacao->observacoes)
                <div class="mb-3">
                    <strong>Observacoes:</strong><br>
                    {{ $movimentacao->observacoes }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
