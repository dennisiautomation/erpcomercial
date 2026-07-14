@extends('layouts.app')

@section('title', 'Historico de Caixas')

@section('content')
<x-erp.page-header title="Historico de Caixas" subtitle="Aberturas, fechamentos e conferencias" icon="cash-stack">
    <a href="{{ route('app.caixa.abrir') }}" class="btn btn-erp-primary">
        <i class="bi bi-unlock me-1"></i> Abrir Caixa
    </a>
</x-erp.page-header>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.caixa.index')">
    <div class="col-md-2">
        <label class="form-label fw-semibold small text-muted">Status</label>
        <select name="status" class="form-select">
            <option value="">Todos</option>
            <option value="aberto" {{ request('status') == 'aberto' ? 'selected' : '' }}>Aberto</option>
            <option value="fechado" {{ request('status') == 'fechado' ? 'selected' : '' }}>Fechado</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label fw-semibold small text-muted">De</label>
        <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
    </div>
    <div class="col-md-2">
        <label class="form-label fw-semibold small text-muted">Ate</label>
        <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
    </div>
    <div class="col-auto">
        <a href="{{ route('app.caixa.index') }}" class="btn btn-erp-outline">
            <i class="bi bi-x-lg me-1"></i> Limpar
        </a>
    </div>
</x-erp.filter-bar>

<x-erp.data-table>
    <thead>
        <tr>
            <th>Caixa</th>
            <th>Operador</th>
            <th>Abertura</th>
            <th>Fechamento</th>
            <th class="text-end">Valor Abertura</th>
            <th class="text-end">Esperado (dinheiro)</th>
            <th class="text-end">Contado</th>
            <th class="text-end">Diferenca</th>
            <th class="text-center">Status</th>
            <th class="text-center">Acoes</th>
        </tr>
    </thead>
    <tbody>
        @forelse($caixas as $cx)
        @php
            $diferenca = $cx->status->value === 'fechado' && $cx->valor_esperado !== null
                ? (float) $cx->valor_fechamento - (float) $cx->valor_esperado
                : null;
        @endphp
        <tr>
            <td>
                <div class="fw-semibold">#{{ $cx->numero_caixa }}</div>
                @if($cx->unidade)
                    <small class="text-muted">{{ $cx->unidade->nome }}</small>
                @endif
            </td>
            <td><small>{{ $cx->operador->name ?? '-' }}</small></td>
            <td>
                <div class="fw-semibold">{{ $cx->aberto_em?->format('d/m/Y') }}</div>
                <small class="text-muted">{{ $cx->aberto_em?->format('H:i') }}</small>
            </td>
            <td>
                @if($cx->fechado_em)
                    <div class="fw-semibold">{{ $cx->fechado_em->format('d/m/Y') }}</div>
                    <small class="text-muted">{{ $cx->fechado_em->format('H:i') }}</small>
                @else
                    <span class="text-muted">-</span>
                @endif
            </td>
            <td class="text-end">R$ {{ number_format($cx->valor_abertura, 2, ',', '.') }}</td>
            <td class="text-end">
                {{ $cx->valor_esperado !== null ? 'R$ ' . number_format($cx->valor_esperado, 2, ',', '.') : '-' }}
            </td>
            <td class="text-end">
                {{ $cx->valor_fechamento !== null ? 'R$ ' . number_format($cx->valor_fechamento, 2, ',', '.') : '-' }}
            </td>
            <td class="text-end">
                @if($diferenca === null)
                    <span class="text-muted">-</span>
                @elseif(abs($diferenca) < 0.02)
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Confere</span>
                @elseif($diferenca > 0)
                    <span class="text-warning fw-bold">+ R$ {{ number_format($diferenca, 2, ',', '.') }}</span>
                @else
                    <span class="text-danger fw-bold">- R$ {{ number_format(abs($diferenca), 2, ',', '.') }}</span>
                @endif
            </td>
            <td class="text-center">
                <span class="badge bg-{{ $cx->status->color() }} rounded-pill px-3">{{ $cx->status->label() }}</span>
            </td>
            <td class="text-center">
                <div class="action-btns">
                    <a href="{{ route('app.caixa.show', $cx) }}" class="btn btn-sm btn-erp-outline" title="Extrato">
                        <i class="bi bi-eye"></i>
                    </a>
                </div>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="10">
                <x-erp.empty-state icon="cash-stack" title="Nenhum caixa encontrado"
                    description="Os caixas abertos e fechados aparecem aqui." />
            </td>
        </tr>
        @endforelse
    </tbody>
    <x-slot name="pagination">
        @if($caixas->hasPages())
            {{ $caixas->links() }}
        @endif
    </x-slot>
</x-erp.data-table>
@endsection
