@extends('layouts.app')

@section('title', 'Movimentações de Estoque')

@section('content')
<x-erp.page-header title="Movimentações de Estoque" subtitle="Controle de entradas, saídas, ajustes e transferências" icon="arrow-left-right">
    {{-- Saldo inicial da migração: discreto, ao lado do botão de sempre --}}
    <x-erp.import-buttons :importRoute="route('app.import.estoque')" templateType="estoque" />
    <a href="{{ route('app.movimentacoes.create') }}" class="btn btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Movimentação
    </a>
</x-erp.page-header>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="box-arrow-in-down" color="success" :value="$totalEntradas" label="Entradas (mês)" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="box-arrow-up" color="danger" :value="$totalSaidas" label="Saídas (mês)" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="pencil-square" color="warning" :value="$totalAjustes" label="Ajustes (mês)" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="arrow-left-right" color="primary" :value="$totalTransferencias" label="Transferências (mês)" />
    </div>
</div>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.movimentacoes.index')">
    <div class="col-md-3">
        <label for="campo-produto_id" class="form-label fw-semibold small text-muted">Produto</label>
        <select id="campo-produto_id" name="produto_id" class="form-select">
            <option value="">Todos os produtos</option>
            @foreach($produtos as $produto)
                <option value="{{ $produto->id }}" {{ request('produto_id') == $produto->id ? 'selected' : '' }}>
                    {{ $produto->descricao }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label for="campo-tipo" class="form-label fw-semibold small text-muted">Tipo</label>
        <select id="campo-tipo" name="tipo" class="form-select">
            <option value="">Todos</option>
            <option value="entrada" {{ request('tipo') == 'entrada' ? 'selected' : '' }}>Entrada</option>
            <option value="saida" {{ request('tipo') == 'saida' ? 'selected' : '' }}>Saída</option>
            <option value="ajuste" {{ request('tipo') == 'ajuste' ? 'selected' : '' }}>Ajuste</option>
            <option value="perda" {{ request('tipo') == 'perda' ? 'selected' : '' }}>Perda</option>
            <option value="bonificacao" {{ request('tipo') == 'bonificacao' ? 'selected' : '' }}>Bonificacao</option>
            <option value="transferencia" {{ request('tipo') == 'transferencia' ? 'selected' : '' }}>Transferência</option>
            <option value="devolucao" {{ request('tipo') == 'devolucao' ? 'selected' : '' }}>Devolução</option>
        </select>
    </div>
    <div class="col-md-2">
        <label for="campo-data_inicio" class="form-label fw-semibold small text-muted">De</label>
        <input id="campo-data_inicio" type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
    </div>
    <div class="col-md-2">
        <label for="campo-data_fim" class="form-label fw-semibold small text-muted">Até</label>
        <input id="campo-data_fim" type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
    </div>
    <div class="col-auto">
        <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-erp-outline">
            <i class="bi bi-x-lg me-1"></i> Limpar
        </a>
    </div>
</x-erp.filter-bar>

{{-- Table --}}
<x-erp.data-table>
    <thead>
        <tr>
            <th>Data</th>
            <th>Produto</th>
            <th>Tipo</th>
            <th class="text-end">Quantidade</th>
            <th class="text-center">Estoque</th>
            <th>Usuário</th>
            <th>Origem</th>
            <th class="text-center">Ações</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movimentacoes as $mov)
        <tr>
            <td>
                <div class="fw-semibold">{{ $mov->created_at->format('d/m/Y') }}</div>
                <small class="text-muted">{{ $mov->created_at->format('H:i') }}</small>
            </td>
            <td>
                <div class="fw-semibold">{{ $mov->produto->descricao ?? '-' }}</div>
                @if($mov->unidade)
                    <small class="text-muted">{{ $mov->unidade->nome }}</small>
                @endif
            </td>
            <td>
                @php
                    $badgeClass = match($mov->tipo->value) {
                        'entrada', 'devolucao' => 'bg-success',
                        'saida', 'perda' => 'bg-danger',
                        'ajuste' => 'bg-warning text-dark',
                        'bonificacao' => 'bg-info text-dark',
                        'transferencia' => 'bg-primary',
                        default => 'bg-secondary',
                    };
                @endphp
                <span class="badge {{ $badgeClass }} rounded-pill px-3">{{ $mov->tipo->label() }}</span>
            </td>
            <td class="text-end">
                @if(in_array($mov->tipo->value, ['entrada', 'devolucao', 'ajuste']))
                    <span class="text-success fw-bold">
                        <i class="bi bi-arrow-up-short"></i>{{ number_format($mov->quantidade, 3, ',', '.') }}
                    </span>
                @else
                    <span class="text-danger fw-bold">
                        <i class="bi bi-arrow-down-short"></i>{{ number_format($mov->quantidade, 3, ',', '.') }}
                    </span>
                @endif
            </td>
            <td class="text-center">
                <span class="text-muted">{{ number_format($mov->quantidade_anterior, 3, ',', '.') }}</span>
                <i class="bi bi-arrow-right mx-1 text-muted small"></i>
                <span class="fw-semibold">{{ number_format($mov->quantidade_posterior, 3, ',', '.') }}</span>
            </td>
            <td>
                <small>{{ $mov->user->name ?? '-' }}</small>
            </td>
            <td>
                @if($mov->origem_tipo)
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-link-45deg me-1"></i>{{ class_basename($mov->origem_tipo) }} #{{ $mov->origem_id }}
                    </span>
                @else
                    <span class="badge bg-light text-muted border">Manual</span>
                @endif
            </td>
            <td class="text-center">
                <div class="action-btns">
                    <a href="{{ route('app.movimentacoes.show', $mov) }}" class="btn btn-sm btn-erp-outline" title="Detalhes">
                        <i class="bi bi-eye"></i>
                    </a>
                </div>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="8">
                <x-erp.empty-state icon="arrow-left-right" title="Nenhuma movimentação encontrada" />
            </td>
        </tr>
        @endforelse
    </tbody>
    <x-slot name="pagination">
        @if($movimentacoes->hasPages())
            {{ $movimentacoes->links() }}
        @endif
    </x-slot>
</x-erp.data-table>
@endsection
