@extends('layouts.app')

@section('title', 'Caixa #' . $caixa->numero_caixa)

@section('content')
@php
    $diferenca = $caixa->status->value === 'fechado' && $caixa->valor_esperado !== null
        ? (float) $caixa->valor_fechamento - (float) $caixa->valor_esperado
        : null;
    $formasLabels = \App\Models\MovimentacaoCaixa::FORMAS_LABELS;
@endphp

<x-erp.page-header
    title="Caixa #{{ $caixa->numero_caixa }}"
    subtitle="{{ $caixa->unidade->nome ?? '' }} — Operador: {{ $caixa->operador->name ?? '-' }}"
    icon="cash-stack">
    <span class="badge bg-{{ $caixa->status->color() }} rounded-pill px-3 me-2 align-self-center">
        {{ $caixa->status->label() }}
    </span>
    <a href="{{ route('app.caixa.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

{{-- Resumo --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="unlock" color="secondary"
            :value="'R$ ' . number_format($resumo['abertura'], 2, ',', '.')" label="Abertura" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="bag-check" color="success"
            :value="'R$ ' . number_format($resumo['vendas'], 2, ',', '.')" label="Vendas (total)" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="cash-coin" color="primary"
            :value="'R$ ' . number_format($resumo['esperado_dinheiro'], 2, ',', '.')" label="Esperado em dinheiro" />
    </div>
    <div class="col-6 col-lg-3">
        @if($diferenca === null)
            <x-erp.stat-card icon="hourglass-split" color="secondary" value="—" label="Diferenca (aguarda fechamento)" />
        @elseif(abs($diferenca) < 0.02)
            <x-erp.stat-card icon="check-circle" color="success" value="Confere" label="Diferenca" />
        @elseif($diferenca > 0)
            <x-erp.stat-card icon="exclamation-triangle" color="warning"
                :value="'+ R$ ' . number_format($diferenca, 2, ',', '.')" label="Sobra no fechamento" />
        @else
            <x-erp.stat-card icon="x-circle" color="danger"
                :value="'- R$ ' . number_format(abs($diferenca), 2, ',', '.')" label="Falta no fechamento" />
        @endif
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        {{-- Vendas por forma de pagamento --}}
        <x-erp.card title="Vendas por forma de pagamento" icon="credit-card">
            @forelse($resumo['vendas_por_forma'] as $forma => $valor)
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>
                        @if($forma === 'dinheiro')<i class="bi bi-cash-coin text-success me-1"></i>@endif
                        {{ $formasLabels[$forma] ?? ucfirst($forma) }}
                        @if($forma !== 'dinheiro')
                            <small class="text-muted">(não entra na gaveta)</small>
                        @endif
                    </span>
                    <strong>R$ {{ number_format($valor, 2, ',', '.') }}</strong>
                </div>
            @empty
                <p class="text-muted mb-0">Nenhuma venda neste caixa.</p>
            @endforelse

            <div class="d-flex justify-content-between pt-3">
                <span class="text-muted">Suprimentos</span>
                <span class="text-info">+ R$ {{ number_format($resumo['suprimentos'], 2, ',', '.') }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Sangrias</span>
                <span class="text-danger">- R$ {{ number_format($resumo['sangrias'], 2, ',', '.') }}</span>
            </div>
        </x-erp.card>

        {{-- Dados do fechamento --}}
        <div class="mt-4">
        <x-erp.card title="Fechamento" icon="lock">
            <div class="d-flex justify-content-between py-1">
                <span class="text-muted">Aberto em</span>
                <span>{{ $caixa->aberto_em?->format('d/m/Y H:i') }}</span>
            </div>
            <div class="d-flex justify-content-between py-1">
                <span class="text-muted">Fechado em</span>
                <span>{{ $caixa->fechado_em?->format('d/m/Y H:i') ?? '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1">
                <span class="text-muted">Esperado (dinheiro)</span>
                <span>{{ $caixa->valor_esperado !== null ? 'R$ ' . number_format($caixa->valor_esperado, 2, ',', '.') : '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1">
                <span class="text-muted">Contado</span>
                <span>{{ $caixa->valor_fechamento !== null ? 'R$ ' . number_format($caixa->valor_fechamento, 2, ',', '.') : '—' }}</span>
            </div>
            @if($caixa->observacoes)
                <hr>
                <small class="text-muted d-block">Observações</small>
                <p class="mb-0">{{ $caixa->observacoes }}</p>
            @endif
        </x-erp.card>
        </div>
    </div>

    <div class="col-lg-8">
        {{-- Extrato de movimentações --}}
        <x-erp.card title="Extrato do caixa" icon="list-ul">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Tipo</th>
                            <th>Descrição</th>
                            <th>Forma</th>
                            <th>Usuário</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($caixa->movimentacoes->sortBy('id') as $mov)
                        @php
                            $badge = match($mov->tipo->value) {
                                'abertura' => 'bg-secondary',
                                'venda' => 'bg-success',
                                'suprimento' => 'bg-info text-dark',
                                'sangria' => 'bg-danger',
                                'fechamento' => 'bg-dark',
                                default => 'bg-light text-dark',
                            };
                            $sinal = $mov->tipo->sinal();
                        @endphp
                        <tr>
                            <td><small>{{ $mov->created_at->format('d/m H:i') }}</small></td>
                            <td><span class="badge {{ $badge }} rounded-pill">{{ $mov->tipo->label() }}</span></td>
                            <td><small>{{ $mov->descricao ?? '-' }}</small></td>
                            <td>
                                @if($mov->formaLabel())
                                    <small class="{{ $mov->forma_pagamento === 'dinheiro' ? 'text-success fw-semibold' : 'text-muted' }}">
                                        {{ $mov->formaLabel() }}
                                    </small>
                                @else
                                    <small class="text-muted">-</small>
                                @endif
                            </td>
                            <td><small>{{ $mov->user->name ?? '-' }}</small></td>
                            <td class="text-end fw-semibold {{ $sinal > 0 ? 'text-success' : ($sinal < 0 ? 'text-danger' : '') }}">
                                {{ $sinal < 0 ? '-' : ($sinal > 0 ? '+' : '') }} R$ {{ number_format($mov->valor, 2, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">Nenhuma movimentação.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-erp.card>
    </div>
</div>
@endsection
