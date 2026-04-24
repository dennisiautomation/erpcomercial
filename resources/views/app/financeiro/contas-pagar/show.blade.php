@extends('layouts.app')

@section('title', 'Detalhes - Conta a Pagar')

@section('content')
<x-erp.page-header title="Conta a Pagar #{{ $contaPagar->id }}" icon="eye">
    <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row g-4">
    <div class="col-lg-8">
        <x-erp.card title="Informacoes" icon="info-circle">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Fornecedor</th>
                    <td>{{ $contaPagar->fornecedor->razao_social ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Descricao</th>
                    <td>{{ $contaPagar->descricao }}</td>
                </tr>
                <tr>
                    <th>Valor</th>
                    <td>R$ {{ number_format($contaPagar->valor, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Vencimento</th>
                    <td>{{ $contaPagar->vencimento->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <th>Parcela</th>
                    <td>{{ $contaPagar->parcela }}/{{ $contaPagar->total_parcelas }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        @php
                            $isOverdue = $contaPagar->status === 'pendente' && $contaPagar->vencimento->isPast();
                            $statusValue = $isOverdue ? 'vencida' : $contaPagar->status;
                        @endphp
                        <x-erp.status-badge :status="$statusValue" />
                    </td>
                </tr>
                <tr>
                    <th>Categoria</th>
                    <td>{{ $contaPagar->categoria ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Centro de Custo</th>
                    <td>{{ $contaPagar->centro_custo ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Recorrente</th>
                    <td>{{ $contaPagar->recorrente ? 'Sim - ' . ucfirst($contaPagar->recorrencia_tipo ?? 'mensal') : 'Nao' }}</td>
                </tr>
                @if($contaPagar->status === 'paga')
                <tr>
                    <th>Valor Pago</th>
                    <td>R$ {{ number_format($contaPagar->valor_pago, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Pago em</th>
                    <td>{{ $contaPagar->pago_em?->format('d/m/Y') ?? '-' }}</td>
                </tr>
                @endif
                @if($contaPagar->observacoes)
                <tr>
                    <th>Observacoes</th>
                    <td>{{ $contaPagar->observacoes }}</td>
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
                        <tr class="{{ $parcela->id === $contaPagar->id ? 'table-active' : '' }}">
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

        @if($contaPagar->status === 'pendente')
        <div class="d-flex gap-2 mt-4">
            <form method="POST" action="{{ route('app.contas-pagar.baixar', $contaPagar) }}"
                data-confirm="Confirma o pagamento desta conta?">
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
