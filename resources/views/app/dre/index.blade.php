@extends('layouts.app')

@section('title', 'DRE - Demonstrativo de Resultado')

@section('content')
<x-erp.page-header title="DRE - Demonstrativo de Resultado" icon="bar-chart-line">
    <a href="{{ route('app.dre.por-unidade', ['data_inicio' => $dataInicio, 'data_fim' => $dataFim]) }}" class="btn btn-erp-outline">
        <i class="bi bi-columns-gap me-1"></i> Por Unidade
    </a>
    <a href="{{ route('app.dre.exportar', ['data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'unidade_id' => $unidadeId]) }}" class="btn btn-erp-outline">
        <i class="bi bi-download me-1"></i> Exportar CSV
    </a>
</x-erp.page-header>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.dre.index')">
    <div class="col-md-3">
        <label for="data_inicio" class="form-label">Data Inicio</label>
        <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="{{ $dataInicio }}">
    </div>
    <div class="col-md-3">
        <label for="data_fim" class="form-label">Data Fim</label>
        <input type="date" name="data_fim" id="data_fim" class="form-control" value="{{ $dataFim }}">
    </div>
    <div class="col-md-3">
        <label for="unidade_id" class="form-label">Unidade</label>
        <select name="unidade_id" id="unidade_id" class="form-select">
            <option value="">Todas</option>
            @foreach($unidades as $unidade)
                <option value="{{ $unidade->id }}" {{ $unidadeId == $unidade->id ? 'selected' : '' }}>
                    {{ $unidade->nome }}
                </option>
            @endforeach
        </select>
    </div>
</x-erp.filter-bar>

{{-- Result Summary --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <x-erp.stat-card
            icon="graph-up-arrow"
            :color="$dre['resultadoLiquido'] >= 0 ? 'success' : 'danger'"
            :value="number_format($dre['resultadoLiquido'], 2, ',', '.')"
            label="Resultado Liquido"
            prefix="R$ " />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="cash-coin" color="primary" :value="number_format($dre['receitaBruta'], 2, ',', '.')" label="Receita Bruta" prefix="R$ " />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="percent" color="warning"
            :value="($dre['receitaBruta'] > 0 ? number_format(($dre['resultadoLiquido'] / $dre['receitaBruta']) * 100, 1, ',', '.') : '0,0') . '%'"
            label="Margem Liquida" />
    </div>
</div>

{{-- DRE Table --}}
<x-erp.card>
    <div class="table-responsive">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Descricao</th>
                    <th class="text-end">Valor</th>
                    <th class="text-end">% Receita</th>
                    <th class="text-end">Periodo Anterior</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dre['linhas'] as $index => $linha)
                    <tr class="{{ $linha['tipo'] === 'final' ? 'table-dark fw-bold' : '' }}
                               {{ $linha['tipo'] === 'resultado' ? 'fw-bold border-top' : '' }}
                               {{ $linha['tipo'] === 'header' ? 'fw-semibold' : '' }}">
                        <td class="{{ $linha['tipo'] === 'detalhe' ? 'text-muted' : '' }}">
                            {{ $linha['descricao'] }}
                        </td>
                        <td class="text-end {{ $linha['valor'] < 0 ? 'text-danger' : '' }}">
                            @if($linha['valor'] < 0)
                                (R$ {{ number_format(abs($linha['valor']), 2, ',', '.') }})
                            @else
                                R$ {{ number_format($linha['valor'], 2, ',', '.') }}
                            @endif
                        </td>
                        <td class="text-end text-muted">
                            {{ $linha['percentual'] }}%
                        </td>
                        <td class="text-end">
                            @if(isset($dreAnterior['linhas'][$index]))
                                @php $valorAnterior = $dreAnterior['linhas'][$index]['valor']; @endphp
                                <span class="{{ $valorAnterior < 0 ? 'text-danger' : 'text-muted' }}">
                                    @if($valorAnterior < 0)
                                        (R$ {{ number_format(abs($valorAnterior), 2, ',', '.') }})
                                    @else
                                        R$ {{ number_format($valorAnterior, 2, ',', '.') }}
                                    @endif
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-erp.card>
@endsection
