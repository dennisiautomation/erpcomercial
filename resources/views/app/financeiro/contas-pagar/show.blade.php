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
        <x-erp.card title="Informações" icon="info-circle">
            <div class="table-responsive">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">{{ ['fornecedor' => 'Fornecedor', 'funcionario' => 'Funcionário', 'outro' => 'Pagar para'][$contaPagar->favorecido_tipo] }}</th>
                    <td>{{ $contaPagar->favorecido_label }}</td>
                </tr>
                <tr>
                    <th>Descrição</th>
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
                    <th>Mês de competência</th>
                    <td>{{ $contaPagar->competencia?->format('m/Y') ?? '-' }}</td>
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
                    <th>{{ $contaPagar->plano_conta_id ? 'Plano de contas' : 'Categoria' }}</th>
                    <td>
                        @if($contaPagar->planoConta)
                            {{ $contaPagar->planoConta->codigo }} — {{ $contaPagar->planoConta->nome }}
                        @else
                            {{ $contaPagar->categoria ?? '-' }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Centro de Custo</th>
                    <td>{{ $contaPagar->centroCusto->nome ?? $contaPagar->centro_custo ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Recorrente</th>
                    <td>{{ $contaPagar->recorrente ? 'Sim - ' . ucfirst($contaPagar->recorrencia_tipo ?? 'mensal') : 'Nao' }}</td>
                </tr>
                @if($contaPagar->forma_pagamento)
                <tr>
                    <th>Forma de pagamento</th>
                    <td>{{ ucfirst(str_replace('_', ' ', $contaPagar->forma_pagamento)) }}</td>
                </tr>
                @endif
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
                    <th>Observações</th>
                    <td>{{ $contaPagar->observacoes }}</td>
                </tr>
                @endif
            </table>
            </div>
        </x-erp.card>

        {{-- Parcelas --}}
        @if($parcelas->count() > 1)
        <x-erp.card title="Histórico de Parcelas" icon="list-ol" class="mt-4">
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
            <button type="button" class="btn btn-success"
                data-baixar="{{ route('app.contas-pagar.baixar', $contaPagar) }}"
                data-valor="{{ number_format($contaPagar->valor, 2, '.', '') }}"
                data-forma="{{ $contaPagar->forma_pagamento }}"
                data-descricao="{{ $contaPagar->favorecido_label }} — {{ $contaPagar->descricao }}">
                <i class="bi bi-check-circle me-1"></i> Dar baixa
            </button>
            <a href="{{ route('app.contas-pagar.edit', $contaPagar) }}" class="btn btn-erp-outline">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
        </div>
        @elseif($contaPagar->status === 'paga')
        <div class="d-flex gap-2 mt-4">
            <form method="POST" action="{{ route('app.contas-pagar.estornar', $contaPagar) }}"
                data-confirm="Estornar a baixa? A conta volta para pendente e sai do que foi pago.">
                @csrf
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Estornar baixa
                </button>
            </form>
        </div>
        @endif
    </div>
</div>

@include('app.financeiro.contas-pagar._baixa-modal')
@endsection
