@extends('layouts.app')

@section('title', 'Detalhes da Movimentacao')

@section('content')
<x-erp.page-header title="Movimentacao #{{ $movimentacao->id }}" icon="eye">
    <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row g-4">
    <div class="col-lg-8">
        <x-erp.card title="Dados da Movimentacao" icon="info-circle">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Data</th>
                    <td>{{ $movimentacao->created_at->format('d/m/Y H:i:s') }}</td>
                </tr>
                <tr>
                    <th>Tipo</th>
                    <td>
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
                    </td>
                </tr>
                <tr>
                    <th>Produto</th>
                    <td>{{ $movimentacao->produto->descricao ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Quantidade</th>
                    <td>{{ number_format($movimentacao->quantidade, 3, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Estoque Anterior</th>
                    <td>{{ number_format($movimentacao->quantidade_anterior, 3, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Estoque Posterior</th>
                    <td>{{ number_format($movimentacao->quantidade_posterior, 3, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Custo Unitario</th>
                    <td>R$ {{ number_format($movimentacao->custo_unitario, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Usuario</th>
                    <td>{{ $movimentacao->user->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Unidade</th>
                    <td>{{ $movimentacao->unidade->nome ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Origem</th>
                    <td>
                        @if($movimentacao->origem_tipo)
                            {{ class_basename($movimentacao->origem_tipo) }} #{{ $movimentacao->origem_id }}
                        @else
                            Entrada Manual
                        @endif
                    </td>
                </tr>
                @if($movimentacao->observacoes)
                <tr>
                    <th>Observacoes</th>
                    <td>{{ $movimentacao->observacoes }}</td>
                </tr>
                @endif
            </table>
        </x-erp.card>
    </div>
</div>
@endsection
