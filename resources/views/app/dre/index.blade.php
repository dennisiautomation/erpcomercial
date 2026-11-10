@extends('layouts.app')

@section('title', 'DRE - Demonstrativo de Resultado')

@section('content')
<x-erp.page-header title="DRE - Demonstrativo de Resultado" icon="bar-chart-line">
    <a href="{{ route('app.dre.por-unidade', ['data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'regime' => $regime]) }}" class="btn btn-erp-outline">
        <i class="bi bi-columns-gap me-1"></i> Por Unidade
    </a>
    <a href="{{ route('app.dre.exportar', ['data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'unidade_id' => $unidadeId, 'regime' => $regime]) }}" class="btn btn-erp-outline">
        <i class="bi bi-download me-1"></i> Exportar CSV
    </a>
</x-erp.page-header>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.dre.index')">
    <div class="col-md-3">
        <label for="data_inicio" class="form-label">Data Início</label>
        <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="{{ $dataInicio }}">
    </div>
    <div class="col-md-3">
        <label for="data_fim" class="form-label">Data Fim</label>
        <input type="date" name="data_fim" id="data_fim" class="form-control" value="{{ $dataFim }}">
    </div>
    <div class="col-md-3">
        <label for="regime" class="form-label">Considerar</label>
        <select name="regime" id="regime" class="form-select">
            <option value="pagamento" @selected($regime === 'pagamento')>O que foi pago e recebido (caixa)</option>
            <option value="competencia" @selected($regime === 'competencia')>O mês a que pertence (competência)</option>
        </select>
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

<div class="alert alert-info border-0 bg-info bg-opacity-10 small" id="dreRegimeAviso">
    <i class="bi bi-info-circle me-1"></i>
    @if($regime === 'competencia')
        <strong>Por competência:</strong> cada despesa entra no mês a que pertence, paga ou não (salário de outubro pago em novembro conta em outubro).
        Conta sem mês de competência entra pelo vencimento. As receitas entram na data em que foram lançadas, recebidas ou não.
    @else
        <strong>Por caixa:</strong> só entra o que já foi pago e recebido, na data do pagamento. Conta pendente não aparece.
    @endif
</div>

{{-- Result Summary --}}
<div class="row g-3 mb-4" id="dreCards">
    <div class="col-md-6 col-xl-3">
        <x-erp.stat-card
            icon="graph-up-arrow"
            :color="$dre['resultadoLiquido'] >= 0 ? 'success' : 'danger'"
            :value="number_format($dre['resultadoLiquido'], 2, ',', '.')"
            label="Resultado Liquido"
            prefix="R$ " />
    </div>
    <div class="col-md-6 col-xl-3">
        <x-erp.stat-card icon="cash-coin" color="primary" :value="number_format($dre['receitaBruta'], 2, ',', '.')" label="Receita Bruta" prefix="R$ " />
    </div>
    <div class="col-md-6 col-xl-3">
        <x-erp.stat-card icon="people" color="info" :value="number_format($dre['despesasPessoal'], 2, ',', '.')"
            :label="'Despesas com Pessoal' . ($dre['receitaBruta'] > 0 ? ' (' . number_format(($dre['despesasPessoal'] / $dre['receitaBruta']) * 100, 1, ',', '.') . '% da receita)' : '')" prefix="R$ " />
    </div>
    <div class="col-md-6 col-xl-3">
        <x-erp.stat-card icon="percent" color="warning"
            :value="($dre['receitaBruta'] > 0 ? number_format(($dre['resultadoLiquido'] / $dre['receitaBruta']) * 100, 1, ',', '.') : '0,0') . '%'"
            label="Margem Liquida" />
    </div>
</div>

{{-- DRE Table --}}
<x-erp.card>
    <div class="table-responsive">
        <table class="erp-table" id="tabelaDre">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th class="text-end">Valor</th>
                    <th class="text-end">% Receita</th>
                    <th class="text-end">Período Anterior</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dre['linhas'] as $index => $linha)
                    <tr data-chave="{{ $linha['chave'] }}"
                        class="{{ $linha['tipo'] === 'final' ? 'table-dark fw-bold' : '' }}
                               {{ $linha['tipo'] === 'resultado' ? 'fw-bold border-top' : '' }}
                               {{ in_array($linha['tipo'], ['header', 'grupo'], true) ? 'fw-semibold' : '' }}">
                        <td class="{{ $linha['tipo'] === 'detalhe' ? 'text-muted' : '' }}" style="padding-left: {{ 0.75 + $linha['nivel'] * 1.25 }}rem;">
                            {{ $linha['rotulo'] }}
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
                            @if($anteriorPorChave->has($linha['chave']))
                                @php $valorAnterior = $anteriorPorChave[$linha['chave']]; @endphp
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
