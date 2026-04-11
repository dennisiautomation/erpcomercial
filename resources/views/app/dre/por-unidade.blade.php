@extends('layouts.app')

@section('title', 'DRE por Unidade')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-columns-gap me-2"></i>DRE por Unidade</h4>
    <a href="{{ route('app.dre.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.dre.por-unidade') }}" class="row g-3 align-items-end">
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

@if(empty($dresPorUnidade))
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-building fs-1"></i>
            <p class="mt-2">Nenhuma unidade encontrada.</p>
        </div>
    </div>
@else
    {{-- Summary Cards --}}
    <div class="row mb-4">
        @foreach($dresPorUnidade as $item)
            <div class="col-md-4 mb-3">
                <div class="card border-start border-4 {{ $item['dre']['resultadoLiquido'] >= 0 ? 'border-success' : 'border-danger' }}">
                    <div class="card-body text-center">
                        <div class="text-muted small">{{ $item['unidade']->nome }}</div>
                        <div class="fs-5 fw-bold {{ $item['dre']['resultadoLiquido'] >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format($item['dre']['resultadoLiquido'], 2, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Comparative Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Descricao</th>
                            @foreach($dresPorUnidade as $item)
                                <th class="text-end">{{ $item['unidade']->nome }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $firstDre = collect($dresPorUnidade)->first();
                            $totalLinhas = count($firstDre['dre']['linhas']);
                        @endphp

                        @for($i = 0; $i < $totalLinhas; $i++)
                            @php $linhaRef = $firstDre['dre']['linhas'][$i]; @endphp
                            <tr class="{{ $linhaRef['tipo'] === 'final' ? 'table-dark fw-bold' : '' }}
                                       {{ $linhaRef['tipo'] === 'resultado' ? 'fw-bold border-top' : '' }}
                                       {{ $linhaRef['tipo'] === 'header' ? 'fw-semibold' : '' }}">
                                <td class="ps-3 {{ $linhaRef['tipo'] === 'detalhe' ? 'text-muted' : '' }}">
                                    {{ $linhaRef['descricao'] }}
                                </td>
                                @foreach($dresPorUnidade as $item)
                                    @php $valor = $item['dre']['linhas'][$i]['valor'] ?? 0; @endphp
                                    <td class="text-end {{ $valor < 0 ? 'text-danger' : '' }}">
                                        @if($valor < 0)
                                            (R$ {{ number_format(abs($valor), 2, ',', '.') }})
                                        @else
                                            R$ {{ number_format($valor, 2, ',', '.') }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
