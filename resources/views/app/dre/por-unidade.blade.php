@extends('layouts.app')

@section('title', 'DRE por Unidade')

@section('content')
<x-erp.page-header title="DRE por Unidade" icon="columns-gap">
    <a href="{{ route('app.dre.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.dre.por-unidade')">
    <div class="col-md-4">
        <label for="data_inicio" class="form-label">Data Inicio</label>
        <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="{{ $dataInicio }}">
    </div>
    <div class="col-md-4">
        <label for="data_fim" class="form-label">Data Fim</label>
        <input type="date" name="data_fim" id="data_fim" class="form-control" value="{{ $dataFim }}">
    </div>
</x-erp.filter-bar>

@if(empty($dresPorUnidade))
    <x-erp.card>
        <x-erp.empty-state icon="building" title="Nenhuma unidade encontrada" />
    </x-erp.card>
@else
    {{-- Summary Cards --}}
    <div class="row mb-4">
        @foreach($dresPorUnidade as $item)
            <div class="col-md-4 mb-3">
                <x-erp.stat-card
                    icon="building"
                    :color="$item['dre']['resultadoLiquido'] >= 0 ? 'success' : 'danger'"
                    :value="number_format($item['dre']['resultadoLiquido'], 2, ',', '.')"
                    :label="$item['unidade']->nome"
                    prefix="R$ " />
            </div>
        @endforeach
    </div>

    {{-- Comparative Table --}}
    <x-erp.card>
        <div class="table-responsive">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Descricao</th>
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
                            <td class="{{ $linhaRef['tipo'] === 'detalhe' ? 'text-muted' : '' }}">
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
    </x-erp.card>
@endif
@endsection
