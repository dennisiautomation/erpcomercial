@extends('layouts.app')

@section('title', 'DRE por Unidade')

@section('content')
<x-erp.page-header title="DRE por Unidade" icon="columns-gap">
    <a href="{{ route('app.dre.index', ['data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'regime' => $regime]) }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.dre.por-unidade')">
    <div class="col-md-3">
        <label for="data_inicio" class="form-label">Data Início</label>
        <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="{{ $dataInicio }}">
    </div>
    <div class="col-md-3">
        <label for="data_fim" class="form-label">Data Fim</label>
        <input type="date" name="data_fim" id="data_fim" class="form-control" value="{{ $dataFim }}">
    </div>
    <div class="col-md-4">
        <label for="regime" class="form-label">Considerar</label>
        <select name="regime" id="regime" class="form-select">
            <option value="pagamento" @selected($regime === 'pagamento')>O que foi pago e recebido (caixa)</option>
            <option value="competencia" @selected($regime === 'competencia')>O mês a que pertence (competência)</option>
        </select>
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
            <table class="erp-table" id="tabelaDreUnidades">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        @foreach($dresPorUnidade as $item)
                            <th class="text-end">{{ $item['unidade']->nome }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{-- linhas do consolidado; cada loja entra pela CHAVE da linha (não pela posição) --}}
                    @foreach($linhasRef as $linhaRef)
                        <tr data-chave="{{ $linhaRef['chave'] }}"
                            class="{{ $linhaRef['tipo'] === 'final' ? 'table-dark fw-bold' : '' }}
                                   {{ $linhaRef['tipo'] === 'resultado' ? 'fw-bold border-top' : '' }}
                                   {{ in_array($linhaRef['tipo'], ['header', 'grupo'], true) ? 'fw-semibold' : '' }}">
                            <td class="{{ $linhaRef['tipo'] === 'detalhe' ? 'text-muted' : '' }}" style="padding-left: {{ 0.75 + $linhaRef['nivel'] * 1.25 }}rem;">
                                {{ $linhaRef['rotulo'] }}
                            </td>
                            @foreach($dresPorUnidade as $item)
                                @php $valor = $item['porChave'][$linhaRef['chave']] ?? 0; @endphp
                                <td class="text-end {{ $valor < 0 ? 'text-danger' : '' }}">
                                    @if($valor < 0)
                                        (R$ {{ number_format(abs($valor), 2, ',', '.') }})
                                    @else
                                        R$ {{ number_format($valor, 2, ',', '.') }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-erp.card>
@endif
@endsection
