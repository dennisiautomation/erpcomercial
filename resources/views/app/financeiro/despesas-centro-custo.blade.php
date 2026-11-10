@extends('layouts.app')

@section('title', 'Despesas por Centro de Custo')

@section('content')
@php $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.'); @endphp

<x-erp.page-header title="Despesas por Centro de Custo" subtitle="Quanto cada centro de custo consumiu — pessoal, outras despesas e custos" icon="pie-chart">
    <a href="{{ route('app.dre.index', ['data_inicio' => $dataInicio->format('Y-m-d'), 'data_fim' => $dataFim->format('Y-m-d'), 'regime' => $regime === 'competencia' ? 'competencia' : 'pagamento']) }}" class="btn btn-erp-outline">
        <i class="bi bi-bar-chart-line me-1"></i> DRE
    </a>
</x-erp.page-header>

<x-erp.filter-bar :action="route('app.financeiro.despesas-centro-custo')">
    <div class="col-md-2">
        <label for="data_inicio" class="form-label fw-semibold small text-muted">Data Início</label>
        <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="{{ $dataInicio->format('Y-m-d') }}">
    </div>
    <div class="col-md-2">
        <label for="data_fim" class="form-label fw-semibold small text-muted">Data Fim</label>
        <input type="date" name="data_fim" id="data_fim" class="form-control" value="{{ $dataFim->format('Y-m-d') }}">
    </div>
    <div class="col-md-4">
        <label for="regime" class="form-label fw-semibold small text-muted">Considerar</label>
        <select name="regime" id="regime" class="form-select">
            <option value="pagamento" @selected($regime === 'pagamento')>O que foi pago no período</option>
            <option value="competencia" @selected($regime === 'competencia')>O mês a que a despesa pertence (pago ou não)</option>
            <option value="vencimento" @selected($regime === 'vencimento')>O que vence no período (pago ou não)</option>
        </select>
    </div>
    @if($unidades->count() > 1)
    <div class="col-md-2">
        <label for="unidade_id" class="form-label fw-semibold small text-muted">Loja</label>
        <select name="unidade_id" id="unidade_id" class="form-select">
            <option value="">Todas</option>
            @foreach($unidades as $u)
                <option value="{{ $u->id }}" @selected($unidadeId === $u->id)>{{ $u->nome }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="col-auto">
        <a href="{{ route('app.financeiro.despesas-centro-custo') }}" class="btn btn-erp-outline"><i class="bi bi-x-lg me-1"></i> Limpar</a>
    </div>
</x-erp.filter-bar>

<div class="row g-3 mb-4" id="cardsCentro">
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="wallet2" color="primary" :value="number_format($totais['total'], 2, ',', '.')" label="Total do período" prefix="R$ " />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="people" color="info" :value="number_format($totais['pessoal'], 2, ',', '.')" label="Pessoal" prefix="R$ " />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="receipt" color="warning" :value="number_format($totais['outras'] + $totais['custos'], 2, ',', '.')" label="Outras despesas e custos" prefix="R$ " />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="check-circle" color="success" :value="number_format($totais['pago'], 2, ',', '.')" label="Já pago" prefix="R$ " />
    </div>
</div>

<x-erp.card>
    <div class="table-responsive">
        <table class="erp-table" id="tabelaCentros">
            <thead>
                <tr>
                    <th>Centro de custo</th>
                    <th class="text-end">Pessoal</th>
                    <th class="text-end">Outras despesas</th>
                    <th class="text-end">Custos</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">% do total</th>
                    <th class="text-end">Já pago</th>
                </tr>
            </thead>
            <tbody>
                @forelse($centros as $c)
                    <tr class="fw-semibold" data-centro="{{ $c['nome'] }}">
                        <td>
                            {{ $c['nome'] }}
                            <small class="text-muted fw-normal">· {{ $c['contas'] }} {{ $c['contas'] === 1 ? 'conta' : 'contas' }}</small>
                        </td>
                        <td class="text-end">{{ $c['pessoal'] > 0 ? $brl($c['pessoal']) : '-' }}</td>
                        <td class="text-end">{{ $c['outras'] > 0 ? $brl($c['outras']) : '-' }}</td>
                        <td class="text-end">{{ $c['custos'] > 0 ? $brl($c['custos']) : '-' }}</td>
                        <td class="text-end">{{ $brl($c['total']) }}</td>
                        <td class="text-end">{{ number_format($c['pct'], 1, ',', '.') }}%</td>
                        <td class="text-end">{{ $c['pago'] > 0 ? $brl($c['pago']) : '-' }}</td>
                    </tr>
                    @foreach($c['detalhe'] as $d)
                        <tr class="text-muted small">
                            <td style="padding-left: 2rem;">{{ $d['nome'] }} <span class="text-muted">· {{ $d['contas'] }}</span></td>
                            <td colspan="3"></td>
                            <td class="text-end">{{ $brl($d['total']) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="7"><x-erp.empty-state icon="pie-chart" title="Nenhuma despesa no período" description="Troque o período ou o 'Considerar' — por pagamento só entra o que já foi pago." /></td></tr>
                @endforelse
                @if($centros->isNotEmpty())
                    <tr class="table-dark fw-bold">
                        <td>Total</td>
                        <td class="text-end">{{ $brl($totais['pessoal']) }}</td>
                        <td class="text-end">{{ $brl($totais['outras']) }}</td>
                        <td class="text-end">{{ $brl($totais['custos']) }}</td>
                        <td class="text-end">{{ $brl($totais['total']) }}</td>
                        <td class="text-end">100,0%</td>
                        <td class="text-end">{{ $brl($totais['pago']) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
    <div class="form-text mt-2">
        Pessoal = contas lançadas pela folha, pagas a funcionário ou classificadas nos grupos "Pessoal" do plano de contas.
        Este relatório só lê o Contas a Pagar.
    </div>
</x-erp.card>
@endsection
