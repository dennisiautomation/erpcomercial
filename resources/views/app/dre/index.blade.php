@extends('layouts.app')

@section('title', 'DRE - Demonstrativo de Resultado')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>DRE - Demonstrativo de Resultado</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('app.dre.por-unidade', ['data_inicio' => $dataInicio, 'data_fim' => $dataFim]) }}" class="btn btn-outline-primary">
            <i class="bi bi-columns-gap me-1"></i> Por Unidade
        </a>
        <a href="{{ route('app.dre.exportar', ['data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'unidade_id' => $unidadeId]) }}" class="btn btn-outline-success">
            <i class="bi bi-download me-1"></i> Exportar CSV
        </a>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.dre.index') }}" class="row g-3 align-items-end">
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
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Result Summary --}}
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-start border-4 {{ $dre['resultadoLiquido'] >= 0 ? 'border-success' : 'border-danger' }}">
            <div class="card-body text-center">
                <div class="text-muted small">Resultado Liquido</div>
                <div class="fs-3 fw-bold {{ $dre['resultadoLiquido'] >= 0 ? 'text-success' : 'text-danger' }}">
                    R$ {{ number_format($dre['resultadoLiquido'], 2, ',', '.') }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start border-4 border-primary">
            <div class="card-body text-center">
                <div class="text-muted small">Receita Bruta</div>
                <div class="fs-3 fw-bold text-primary">
                    R$ {{ number_format($dre['receitaBruta'], 2, ',', '.') }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start border-4 border-warning">
            <div class="card-body text-center">
                <div class="text-muted small">Margem Liquida</div>
                <div class="fs-3 fw-bold text-warning">
                    {{ $dre['receitaBruta'] > 0 ? number_format(($dre['resultadoLiquido'] / $dre['receitaBruta']) * 100, 1, ',', '.') : '0,0' }}%
                </div>
            </div>
        </div>
    </div>
</div>

{{-- DRE Table --}}
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Descricao</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">% Receita</th>
                        <th class="text-end pe-3">Periodo Anterior</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dre['linhas'] as $index => $linha)
                        <tr class="{{ $linha['tipo'] === 'final' ? 'table-dark fw-bold' : '' }}
                                   {{ $linha['tipo'] === 'resultado' ? 'fw-bold border-top' : '' }}
                                   {{ $linha['tipo'] === 'header' ? 'fw-semibold' : '' }}">
                            <td class="ps-3 {{ $linha['tipo'] === 'detalhe' ? 'text-muted' : '' }}">
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
                            <td class="text-end pe-3">
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
    </div>
</div>
@endsection
