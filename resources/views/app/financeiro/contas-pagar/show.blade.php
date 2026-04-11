@extends('layouts.app')

@section('title', 'Detalhes - Conta a Pagar')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-eye me-2"></i>Conta a Pagar #{{ $contaPagar->id }}</h4>
    <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-outline-secondary">
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
                        <strong>Fornecedor:</strong><br>
                        {{ $contaPagar->fornecedor->nome_razao_social ?? '-' }}
                    </div>
                    <div class="col-md-6">
                        <strong>Descricao:</strong><br>
                        {{ $contaPagar->descricao }}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Valor:</strong><br>
                        R$ {{ number_format($contaPagar->valor, 2, ',', '.') }}
                    </div>
                    <div class="col-md-3">
                        <strong>Vencimento:</strong><br>
                        {{ $contaPagar->vencimento->format('d/m/Y') }}
                    </div>
                    <div class="col-md-3">
                        <strong>Parcela:</strong><br>
                        {{ $contaPagar->parcela }}/{{ $contaPagar->total_parcelas }}
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong><br>
                        @php
                            $statusBadge = match($contaPagar->status) {
                                'pendente' => $contaPagar->vencimento->isPast() ? 'bg-danger' : 'bg-warning text-dark',
                                'paga' => 'bg-success',
                                default => 'bg-secondary',
                            };
                            $statusLabel = $contaPagar->status === 'pendente' && $contaPagar->vencimento->isPast() ? 'Vencida' : ucfirst($contaPagar->status);
                        @endphp
                        <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Categoria:</strong><br>
                        {{ $contaPagar->categoria ?? '-' }}
                    </div>
                    <div class="col-md-3">
                        <strong>Centro de Custo:</strong><br>
                        {{ $contaPagar->centro_custo ?? '-' }}
                    </div>
                    <div class="col-md-3">
                        <strong>Recorrente:</strong><br>
                        {{ $contaPagar->recorrente ? 'Sim - ' . ucfirst($contaPagar->recorrencia_tipo ?? 'mensal') : 'Nao' }}
                    </div>
                </div>
                @if($contaPagar->status === 'paga')
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Valor Pago:</strong><br>
                        R$ {{ number_format($contaPagar->valor_pago, 2, ',', '.') }}
                    </div>
                    <div class="col-md-4">
                        <strong>Pago em:</strong><br>
                        {{ $contaPagar->pago_em?->format('d/m/Y') ?? '-' }}
                    </div>
                </div>
                @endif
                @if($contaPagar->observacoes)
                <div>
                    <strong>Observacoes:</strong><br>
                    {{ $contaPagar->observacoes }}
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
                        <tr class="{{ $parcela->id === $contaPagar->id ? 'table-active' : '' }}">
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

        @if($contaPagar->status === 'pendente')
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('app.contas-pagar.baixar', $contaPagar) }}"
                onsubmit="return confirm('Confirma o pagamento desta conta?')">
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
