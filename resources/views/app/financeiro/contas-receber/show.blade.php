@extends('layouts.app')

@section('title', 'Detalhes - Conta a Receber')

@section('content')
<x-erp.page-header title="Conta a Receber #{{ $contaReceber->id }}" icon="eye">
    <a href="{{ route('app.contas-receber.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row g-4">
    <div class="col-lg-8">
        <x-erp.card title="Informacoes" icon="info-circle">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Cliente</th>
                    <td>{{ $contaReceber->cliente->nome_razao_social ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Descricao</th>
                    <td>{{ $contaReceber->descricao }}</td>
                </tr>
                <tr>
                    <th>Valor</th>
                    <td>R$ {{ number_format($contaReceber->valor, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Vencimento</th>
                    <td>{{ $contaReceber->vencimento->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <th>Parcela</th>
                    <td>{{ $contaReceber->parcela }}/{{ $contaReceber->total_parcelas }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        @php
                            $isOverdue = $contaReceber->status === 'pendente' && $contaReceber->vencimento->isPast();
                            $statusValue = $isOverdue ? 'vencida' : $contaReceber->status;
                        @endphp
                        <x-erp.status-badge :status="$statusValue" />
                    </td>
                </tr>
                @if($contaReceber->status === 'paga')
                <tr>
                    <th>Valor Pago</th>
                    <td>R$ {{ number_format($contaReceber->valor_pago, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Pago em</th>
                    <td>{{ $contaReceber->pago_em?->format('d/m/Y') ?? '-' }}</td>
                </tr>
                @endif
                @if($contaReceber->observacoes)
                <tr>
                    <th>Observacoes</th>
                    <td>{{ $contaReceber->observacoes }}</td>
                </tr>
                @endif
            </table>
        </x-erp.card>

        {{-- Parcelas --}}
        @if($parcelas->count() > 1)
        <x-erp.card title="Historico de Parcelas" icon="list-ol" class="mt-4">
            <div class="table-responsive">
                <table class="erp-table">
                    <thead>
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
                                    $pOverdue = $parcela->status === 'pendente' && $parcela->vencimento->isPast();
                                    $pStatus = $pOverdue ? 'vencida' : $parcela->status;
                                @endphp
                                <x-erp.status-badge :status="$pStatus" />
                            </td>
                            <td>{{ $parcela->pago_em?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-erp.card>
        @endif

        @if($contaReceber->status === 'pendente')
        <div class="d-flex gap-2 mt-4">
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
