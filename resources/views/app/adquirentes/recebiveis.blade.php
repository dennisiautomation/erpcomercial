@extends('layouts.app')

@section('title', 'Recebíveis de Cartão')

@section('content')
<x-erp.page-header title="Recebíveis de Cartão" icon="calendar-check"
    subtitle="Previsão de recebimento das máquinas com taxa e valor líquido">
    <a href="{{ route('app.adquirentes.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-credit-card-2-front me-1"></i> Taxas e Prazos
    </a>
</x-erp.page-header>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <x-erp.stat-card icon="cash-stack" color="primary"
            :value="'R$ ' . number_format($totais['bruto'], 2, ',', '.')" label="Bruto no período" />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="percent" color="danger"
            :value="'R$ ' . number_format($totais['taxas'], 2, ',', '.')" label="Taxas das máquinas" />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="wallet2" color="success"
            :value="'R$ ' . number_format($totais['liquido'], 2, ',', '.')" label="Líquido a receber" />
    </div>
</div>

<x-erp.filter-bar :action="route('app.adquirentes.recebiveis')">
    <div class="col-md-3">
        <label class="form-label">De</label>
        <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Até</label>
        <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="">Todos</option>
            <option value="pendente" @selected(request('status') === 'pendente')>Pendente</option>
            <option value="paga" @selected(request('status') === 'paga')>Recebida</option>
        </select>
    </div>
</x-erp.filter-bar>

<x-erp.card title="Previsão de recebimentos" icon="list-ul" class="mt-3">
    <x-erp.data-table>
        <thead>
            <tr>
                <th>Previsto para</th>
                <th>Descrição</th>
                <th>Máquina</th>
                <th>Parcela</th>
                <th class="text-end">Bruto</th>
                <th class="text-end">Taxa</th>
                <th class="text-end">Líquido</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contas as $conta)
            <tr>
                <td>{{ $conta->vencimento?->format('d/m/Y') }}</td>
                <td>{{ $conta->descricao }}</td>
                <td>{{ $conta->adquirenteTaxa?->nome ?? '—' }}</td>
                <td>{{ $conta->parcela }}/{{ $conta->total_parcelas }}</td>
                <td class="text-end">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                <td class="text-end text-danger">{{ $conta->taxa_percentual !== null ? number_format($conta->taxa_percentual, 2, ',', '.') . '%' : '—' }}</td>
                <td class="text-end fw-semibold">{{ $conta->valor_liquido !== null ? 'R$ ' . number_format($conta->valor_liquido, 2, ',', '.') : '—' }}</td>
                <td><x-erp.status-badge :status="$conta->status" /></td>
            </tr>
            @empty
            <tr>
                <td colspan="8">
                    <x-erp.empty-state title="Nenhum recebível de cartão" icon="calendar-x"
                        description="As vendas no cartão passam a gerar recebíveis aqui quando houver regras de máquina cadastradas." />
                </td>
            </tr>
            @endforelse
        </tbody>
        <x-slot:pagination>{{ $contas->links() }}</x-slot:pagination>
    </x-erp.data-table>
</x-erp.card>
@endsection
