@extends('layouts.app')

@section('title', 'Relatorio de Comissoes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Relatorio de Comissoes</h4>
    <a href="{{ route('app.comissoes.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.comissoes.relatorio') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="data_inicio" class="form-label">Data Inicio</label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="{{ $dataInicio }}">
            </div>
            <div class="col-md-4">
                <label for="data_fim" class="form-label">Data Fim</label>
                <input type="date" name="data_fim" id="data_fim" class="form-control" value="{{ $dataFim }}">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

@forelse($relatorio as $userId => $comissoes)
    @php
        $vendedorNome = $comissoes->first()->vendedor->name ?? 'Vendedor Desconhecido';
        $totalVendas = $comissoes->sum('valor_venda');
        $totalComissoes = $comissoes->sum('valor_comissao');
        $mediaPercentual = $comissoes->avg('percentual');
        $totalPendente = $comissoes->where('status', 'pendente')->sum('valor_comissao');
        $totalPago = $comissoes->where('status', 'paga')->sum('valor_comissao');
    @endphp

    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-person me-1"></i> {{ $vendedorNome }}
                </h5>
                <div class="d-flex gap-3">
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
            </div>
        </div>
        <div class="card-body">
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
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
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
                                    @if($comissao->status === 'pendente')
                                        <span class="badge bg-warning-subtle text-warning">Pendente</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">Paga</span>
                                    @endif
                                </td>
                                <td>{{ $comissao->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@empty
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-file-earmark-bar-graph fs-1"></i>
            <p class="mt-2">Nenhuma comissao no periodo selecionado.</p>
        </div>
    </div>
@endforelse
@endsection
