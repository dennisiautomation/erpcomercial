@extends('layouts.app')

@section('title', 'Detalhes - Conta a Receber')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-eye me-2"></i>Conta a Receber #{{ $contaReceber->id }}</h4>
    <a href="{{ route('app.contas-receber.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Informacoes</h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Cliente:</strong><br>
                        {{ $contaReceber->cliente->nome_razao_social ?? '-' }}
                    </div>
                    <div class="col-md-6">
                        <strong>Descricao:</strong><br>
                        {{ $contaReceber->descricao }}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Valor:</strong><br>
                        R$ {{ number_format($contaReceber->valor, 2, ',', '.') }}
                    </div>
                    <div class="col-md-3">
                        <strong>Vencimento:</strong><br>
                        {{ $contaReceber->vencimento->format('d/m/Y') }}
                    </div>
                    <div class="col-md-3">
                        <strong>Parcela:</strong><br>
                        {{ $contaReceber->parcela }}/{{ $contaReceber->total_parcelas }}
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong><br>
                        @php
                            $statusBadge = match($contaReceber->status) {
                                'pendente' => $contaReceber->vencimento->isPast() ? 'bg-danger' : 'bg-warning text-dark',
                                'paga' => 'bg-success',
                                default => 'bg-secondary',
                            };
                            $statusLabel = $contaReceber->status === 'pendente' && $contaReceber->vencimento->isPast() ? 'Vencida' : ucfirst($contaReceber->status);
                        @endphp
                        <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                    </div>
                </div>
                @if($contaReceber->status === 'paga')
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Valor Pago:</strong><br>
                        R$ {{ number_format($contaReceber->valor_pago, 2, ',', '.') }}
                    </div>
                    <div class="col-md-4">
                        <strong>Pago em:</strong><br>
                        {{ $contaReceber->pago_em?->format('d/m/Y') ?? '-' }}
                    </div>
                </div>
                @endif
                @if($contaReceber->observacoes)
                <div>
                    <strong>Observacoes:</strong><br>
                    {{ $contaReceber->observacoes }}
                </div>
                @endif
            </div>
        </div>

        {{-- Parcelas --}}
        @if($parcelas->count() > 1)
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Historico de Parcelas</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Parcela</th>
                            <th>Vencimento</th>
                            <th class="text-end">Valor</th>
                            <th class="text-center">Status</th>
                            <th>Pago em</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($parcelas as $parcela)
                        <tr class="{{ $parcela->id === $contaReceber->id ? 'table-active' : '' }}">
                            <td>{{ $parcela->parcela }}/{{ $parcela->total_parcelas }}</td>
                            <td>{{ $parcela->vencimento->format('d/m/Y') }}</td>
                            <td class="text-end">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @php
                                    $pBadge = match($parcela->status) {
                                        'pendente' => $parcela->vencimento->isPast() ? 'bg-danger' : 'bg-warning text-dark',
                                        'paga' => 'bg-success',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $pBadge }}">{{ ucfirst($parcela->status) }}</span>
                            </td>
                            <td>{{ $parcela->pago_em?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($contaReceber->status === 'pendente')
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('app.contas-receber.baixar', $contaReceber) }}"
                onsubmit="return confirm('Confirma o recebimento desta conta?')">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Marcar como Paga
                </button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
