@extends('layouts.app')

@section('title', 'Multilojas - Comparar Unidades')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Comparar Unidades</h4>
    <a href="{{ route('app.multilojas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.multilojas.comparar') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Data Inicio</label>
                    <input type="date" name="data_inicio" class="form-control"
                           value="{{ $dataInicio->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Data Fim</label>
                    <input type="date" name="data_fim" class="form-control"
                           value="{{ $dataFim->format('Y-m-d') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Unidades</label>
                    <div class="border rounded p-2" style="max-height: 120px; overflow-y: auto;">
                        @foreach($unidades as $unidade)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="unidades[]"
                                       value="{{ $unidade->id }}" id="unidade_{{ $unidade->id }}"
                                       {{ in_array($unidade->id, $unidadesSelecionadas) ? 'checked' : '' }}>
                                <label class="form-check-label" for="unidade_{{ $unidade->id }}">
                                    {{ $unidade->nome }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i> Comparar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Comparison Results --}}
@if(count($comparacao) > 0)
    @php
        $maxFaturamento = collect($comparacao)->max('faturamento');
        $maxVendas = collect($comparacao)->max('total_vendas');
        $maxTicket = collect($comparacao)->max('ticket_medio');
    @endphp

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Resultado da Comparacao</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Metrica</th>
                            @foreach($comparacao as $dados)
                                <th class="text-center">{{ $dados['unidade']->nome }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Faturamento --}}
                        <tr>
                            <td class="fw-semibold"><i class="bi bi-currency-dollar me-1"></i> Faturamento</td>
                            @foreach($comparacao as $dados)
                                <td class="text-center">
                                    <span class="fw-bold {{ $dados['faturamento'] == $maxFaturamento && $maxFaturamento > 0 ? 'text-success' : '' }}">
                                        R$ {{ number_format($dados['faturamento'], 2, ',', '.') }}
                                    </span>
                                    @if($dados['faturamento'] == $maxFaturamento && $maxFaturamento > 0)
                                        <i class="bi bi-arrow-up-circle-fill text-success ms-1"></i>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        {{-- Vendas --}}
                        <tr>
                            <td class="fw-semibold"><i class="bi bi-receipt me-1"></i> Total Vendas</td>
                            @foreach($comparacao as $dados)
                                <td class="text-center">
                                    <span class="fw-bold {{ $dados['total_vendas'] == $maxVendas && $maxVendas > 0 ? 'text-success' : '' }}">
                                        {{ $dados['total_vendas'] }}
                                    </span>
                                    @if($dados['total_vendas'] == $maxVendas && $maxVendas > 0)
                                        <i class="bi bi-arrow-up-circle-fill text-success ms-1"></i>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        {{-- Ticket Medio --}}
                        <tr>
                            <td class="fw-semibold"><i class="bi bi-graph-up me-1"></i> Ticket Medio</td>
                            @foreach($comparacao as $dados)
                                <td class="text-center">
                                    <span class="fw-bold {{ $dados['ticket_medio'] == $maxTicket && $maxTicket > 0 ? 'text-success' : '' }}">
                                        R$ {{ number_format($dados['ticket_medio'], 2, ',', '.') }}
                                    </span>
                                    @if($dados['ticket_medio'] == $maxTicket && $maxTicket > 0)
                                        <i class="bi bi-arrow-up-circle-fill text-success ms-1"></i>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        {{-- Devolucoes --}}
                        <tr>
                            <td class="fw-semibold"><i class="bi bi-arrow-return-left me-1"></i> Devolucoes/Cancelamentos</td>
                            @foreach($comparacao as $dados)
                                <td class="text-center">
                                    <span class="{{ $dados['devolucoes'] > 0 ? 'text-danger fw-bold' : '' }}">
                                        {{ $dados['devolucoes'] }}
                                    </span>
                                    @if($dados['devolucoes'] > 0)
                                        <i class="bi bi-arrow-down-circle-fill text-danger ms-1"></i>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Percentage Comparison --}}
    @if(count($comparacao) >= 2)
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-percent me-2"></i>Diferenca Percentual (em relacao a melhor unidade)</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($comparacao as $dados)
                        <div class="col-md-{{ 12 / count($comparacao) }}">
                            <div class="card border h-100">
                                <div class="card-body text-center">
                                    <h6 class="fw-bold">{{ $dados['unidade']->nome }}</h6>
                                    @if($maxFaturamento > 0)
                                        @php $pct = ($dados['faturamento'] / $maxFaturamento) * 100; @endphp
                                        <div class="mb-2">
                                            <small class="text-muted">Faturamento</small>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : ($pct >= 70 ? 'bg-warning' : 'bg-danger') }}"
                                                     style="width: {{ $pct }}%"></div>
                                            </div>
                                            <small class="{{ $pct >= 100 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($pct, 0) }}%
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@elseif(!empty($unidadesSelecionadas))
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i> Nenhum dado encontrado para o periodo selecionado.
    </div>
@else
    <div class="alert alert-secondary">
        <i class="bi bi-info-circle me-1"></i> Selecione as unidades e o periodo para comparar.
    </div>
@endif
@endsection
