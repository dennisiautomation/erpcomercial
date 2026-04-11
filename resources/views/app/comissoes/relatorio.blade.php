@extends('layouts.app')

@section('title', 'Relatorio de Comissoes')

@section('content')
<x-erp.page-header title="Relatorio de Comissoes" icon="file-earmark-bar-graph">
    <a href="{{ route('app.comissoes.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.comissoes.relatorio')">
    <div class="col-md-4">
        <label for="data_inicio" class="form-label">Data Inicio</label>
        <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="{{ $dataInicio }}">
    </div>
    <div class="col-md-4">
        <label for="data_fim" class="form-label">Data Fim</label>
        <input type="date" name="data_fim" id="data_fim" class="form-control" value="{{ $dataFim }}">
    </div>
</x-erp.filter-bar>

@forelse($relatorio as $userId => $comissoes)
    @php
        $vendedorNome = $comissoes->first()->vendedor->name ?? 'Vendedor Desconhecido';
        $totalVendas = $comissoes->sum('valor_venda');
        $totalComissoes = $comissoes->sum('valor_comissao');
        $mediaPercentual = $comissoes->avg('percentual');
        $totalPendente = $comissoes->where('status', 'pendente')->sum('valor_comissao');
        $totalPago = $comissoes->where('status', 'paga')->sum('valor_comissao');
    @endphp

    <x-erp.card :title="$vendedorNome" icon="person" class="mb-4">
        <div class="d-flex gap-3 mb-3 flex-wrap">
            <span class="badge bg-primary-subtle text-primary fs-6">
                Vendas: R$ {{ number_format($totalVendas, 2, ',', '.') }}
            </span>
            <span class="badge bg-success-subtle text-success fs-6">
                Comissoes: R$ {{ number_format($totalComissoes, 2, ',', '.') }}
            </span>
            <span class="badge bg-secondary-subtle text-secondary fs-6">
                Media: {{ number_format($mediaPercentual, 1, ',', '.') }}%
            </span>
        </div>

        {{-- Summary --}}
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="border rounded p-2 text-center">
                    <div class="text-muted small">Total Vendas</div>
                    <div class="fw-bold">{{ $comissoes->count() }} vendas</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-2 text-center">
                    <div class="text-muted small">Pendente</div>
                    <div class="fw-bold text-warning">R$ {{ number_format($totalPendente, 2, ',', '.') }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-2 text-center">
                    <div class="text-muted small">Pago</div>
                    <div class="fw-bold text-success">R$ {{ number_format($totalPago, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>

        {{-- Detail Table --}}
        <div class="table-responsive">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Venda</th>
                        <th class="text-end">Valor Venda</th>
                        <th class="text-end">%</th>
                        <th class="text-end">Comissao</th>
                        <th>Status</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($comissoes as $comissao)
                        <tr>
                            <td>
                                @if($comissao->venda)
                                    <a href="{{ route('app.vendas.show', $comissao->venda_id) }}" class="text-decoration-none">
                                        #{{ $comissao->venda_id }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-end">R$ {{ number_format($comissao->valor_venda, 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($comissao->percentual, 1, ',', '.') }}%</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($comissao->valor_comissao, 2, ',', '.') }}</td>
                            <td>
                                <x-erp.status-badge :status="$comissao->status" />
                            </td>
                            <td>{{ $comissao->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-erp.card>
@empty
    <x-erp.card>
        <x-erp.empty-state icon="file-earmark-bar-graph" title="Nenhuma comissao no periodo selecionado" />
    </x-erp.card>
@endforelse
@endsection
