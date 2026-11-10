@extends('layouts.app')

@section('title', 'Contas a Pagar')

@section('content')
<x-erp.page-header title="Contas a Pagar" subtitle="Despesas, salários, impostos e benefícios — o que vence e o que já foi pago" icon="wallet2">
    <a href="{{ route('app.export.contas-pagar') }}" class="btn btn-erp-outline"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar</a>
    <a href="{{ route('app.contas-pagar.create') }}" class="btn btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Conta
    </a>
</x-erp.page-header>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <x-erp.stat-card icon="clock-history" color="warning" :value="number_format($totalPendente, 2, ',', '.')" label="Total Pendente" prefix="R$ " />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="exclamation-triangle" color="danger" :value="number_format($totalVencido, 2, ',', '.')" label="Total Vencido" prefix="R$ " />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="check-circle" color="success" :value="number_format($pagoMes, 2, ',', '.')" label="Pago no Mês" prefix="R$ " />
    </div>
</div>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.contas-pagar.index')">
    <div class="col-md-2">
        <label for="campo-status" class="form-label fw-semibold small text-muted">Status</label>
        <select id="campo-status" name="status" class="form-select">
            <option value="">Todos</option>
            <option value="pendente" {{ request('status') == 'pendente' ? 'selected' : '' }}>Pendente</option>
            <option value="paga" {{ request('status') == 'paga' ? 'selected' : '' }}>Paga</option>
            <option value="vencida" {{ request('status') == 'vencida' ? 'selected' : '' }}>Vencida</option>
            <option value="cancelada" {{ request('status') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
        </select>
    </div>
    <div class="col-md-3">
        <label for="campo-fornecedor_id" class="form-label fw-semibold small text-muted">Fornecedor</label>
        <select id="campo-fornecedor_id" name="fornecedor_id" class="form-select">
            <option value="">Todos</option>
            @foreach($fornecedores as $fornecedor)
                <option value="{{ $fornecedor->id }}" {{ request('fornecedor_id') == $fornecedor->id ? 'selected' : '' }}>
                    {{ $fornecedor->razao_social }}
                </option>
            @endforeach
        </select>
    </div>
    @if($planoContas->isNotEmpty())
    <div class="col-md-3">
        <label for="campo-plano_conta_id" class="form-label fw-semibold small text-muted">Plano de contas</label>
        <select id="campo-plano_conta_id" name="plano_conta_id" class="form-select">
            <option value="">Todos</option>
            @foreach($planoContas as $p)
                <option value="{{ $p->id }}" {{ request('plano_conta_id') == $p->id ? 'selected' : '' }}>{{ $p->codigo }} — {{ $p->nome }}</option>
            @endforeach
        </select>
    </div>
    @endif
    @if($centrosCusto->isNotEmpty())
    <div class="col-md-2">
        <label for="campo-centro_custo_id" class="form-label fw-semibold small text-muted">Centro de custo</label>
        <select id="campo-centro_custo_id" name="centro_custo_id" class="form-select">
            <option value="">Todos</option>
            @foreach($centrosCusto as $cc)
                <option value="{{ $cc->id }}" {{ request('centro_custo_id') == $cc->id ? 'selected' : '' }}>{{ $cc->nome }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="col-md-2">
        <label for="campo-vencimento_inicio" class="form-label fw-semibold small text-muted">Vencimento De</label>
        <input id="campo-vencimento_inicio" type="date" name="vencimento_inicio" class="form-control" value="{{ request('vencimento_inicio') }}">
    </div>
    <div class="col-md-2">
        <label for="campo-vencimento_fim" class="form-label fw-semibold small text-muted">Vencimento Até</label>
        <input id="campo-vencimento_fim" type="date" name="vencimento_fim" class="form-control" value="{{ request('vencimento_fim') }}">
    </div>
    <div class="col-auto">
        <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-erp-outline">
            <i class="bi bi-x-lg me-1"></i> Limpar
        </a>
    </div>
</x-erp.filter-bar>

@if(request('competencia') || request('folha'))
<div class="alert alert-info border-0 bg-info bg-opacity-10 small d-flex justify-content-between align-items-center">
    <span><i class="bi bi-funnel me-1"></i>
        Mostrando {{ request('folha') ? 'só as contas lançadas pela folha' : 'as contas' }}{{ request('competencia') ? ' do mês de competência ' . \Carbon\Carbon::parse(request('competencia') . '-01')->format('m/Y') : '' }}.
    </span>
    <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-sm btn-erp-outline">Ver todas</a>
</div>
@endif

{{-- Table --}}
<x-erp.data-table>
    <thead>
        <tr>
            <th>#</th>
            <th>Favorecido</th>
            <th>Descrição</th>
            <th>Categoria</th>
            <th class="text-center">Parcela</th>
            <th>Vencimento</th>
            <th class="text-end">Valor</th>
            <th class="text-center">Status</th>
            <th class="text-center">Ações</th>
        </tr>
    </thead>
    <tbody>
        @forelse($contas as $conta)
        @php
            $isOverdue = $conta->status === 'pendente' && $conta->vencimento->isPast();
            $daysOverdue = $isOverdue ? (int) $conta->vencimento->diffInDays(now()) : 0;
        @endphp
        <tr class="{{ $isOverdue ? 'border-start border-danger border-3' : '' }}">
            <td class="text-muted small">#{{ $conta->id }}</td>
            <td>
                <div class="fw-semibold">{{ $conta->favorecido_label }}</div>
                @if($conta->favorecido_tipo === 'funcionario')
                    <small class="text-muted"><i class="bi bi-person-badge me-1"></i>Funcionário</small>
                @endif
            </td>
            <td>
                <div>{{ Str::limit($conta->descricao, 35) }}</div>
                @if($conta->forma_pagamento)
                    <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $conta->forma_pagamento)) }}</small>
                @endif
            </td>
            <td>
                @if($conta->categoria)
                    <span class="badge bg-light text-dark border rounded-pill">{{ $conta->categoria }}</span>
                @else
                    <span class="text-muted">-</span>
                @endif
                @if($conta->centro_custo)
                    <div><small class="text-muted">{{ $conta->centro_custo }}</small></div>
                @endif
            </td>
            <td class="text-center">
                <span class="badge bg-light text-dark border rounded-pill">{{ $conta->parcela }}/{{ $conta->total_parcelas }}</span>
            </td>
            <td>
                <div class="fw-semibold {{ $isOverdue ? 'text-danger' : '' }}">{{ $conta->vencimento->format('d/m/Y') }}</div>
                @if($isOverdue)
                    <small class="text-danger">{{ $daysOverdue }} {{ $daysOverdue === 1 ? 'dia' : 'dias' }} em atraso</small>
                @elseif($conta->status === 'pendente')
                    <small class="text-muted">{{ $conta->vencimento->diffForHumans() }}</small>
                @endif
            </td>
            <td class="text-end">
                <div class="fw-bold">R$ {{ number_format($conta->valor, 2, ',', '.') }}</div>
                @if($conta->valor_pago && $conta->valor_pago > 0)
                    <small class="text-success">Pago: R$ {{ number_format($conta->valor_pago, 2, ',', '.') }}</small>
                @endif
            </td>
            <td class="text-center">
                @php
                    $statusValue = $isOverdue ? 'vencida' : $conta->status;
                @endphp
                <x-erp.status-badge :status="$statusValue" />
            </td>
            <td class="text-center">
                <div class="action-btns">
                    <a href="{{ route('app.contas-pagar.show', $conta) }}" class="btn btn-sm btn-erp-outline" title="Detalhes">
                        <i class="bi bi-eye"></i>
                    </a>
                    @if($conta->status === 'pendente')
                    <a href="{{ route('app.contas-pagar.edit', $conta) }}" class="btn btn-sm btn-erp-outline" title="Editar (ajustar valor ou vencimento)">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-success" title="Dar baixa"
                        data-baixar="{{ route('app.contas-pagar.baixar', $conta) }}"
                        data-valor="{{ number_format($conta->valor, 2, '.', '') }}"
                        data-forma="{{ $conta->forma_pagamento }}"
                        data-descricao="{{ $conta->favorecido_label }} — {{ $conta->descricao }}">
                        <i class="bi bi-check-lg"></i>
                    </button>
                    <form method="POST" action="{{ route('app.contas-pagar.destroy', $conta) }}"
                        data-confirm="Confirma a exclusão desta conta?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="9">
                <x-erp.empty-state icon="wallet2" title="Nenhuma conta encontrada" />
            </td>
        </tr>
        @endforelse
    </tbody>
    <x-slot name="pagination">
        @if($contas->hasPages())
            {{ $contas->links() }}
        @endif
    </x-slot>
</x-erp.data-table>

@include('app.financeiro.contas-pagar._baixa-modal')
@endsection
