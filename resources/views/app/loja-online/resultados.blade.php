@extends('layouts.app')

@section('title', 'Loja online — Resultados')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Resultados: visitas, pedidos e o que mais vende">
    <div class="btn-group">
        @foreach([7, 30, 90] as $d)<a class="btn btn-outline-secondary {{ $r['dias'] === $d ? 'active' : '' }}" href="{{ route('app.loja-online.resultados', ['dias' => $d]) }}">{{ $d }} dias</a>@endforeach
    </div>
</x-erp.page-header>

@include('app.loja-online._abas')

@php $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.'); $maior = max(1, collect($r['porDia'])->max('visitas')); $maiorValor = max(1, collect($r['porDia'])->max('valor')); @endphp

<div class="row g-3 mb-3">
    @foreach([
        ['Visitas', number_format($r['visitas'], 0, ',', '.'), 'pessoas que abriram a loja'],
        ['Puseram no carrinho', number_format($r['carrinhos'], 0, ',', '.'), $r['visitas'] ? round($r['carrinhos'] / $r['visitas'] * 100) . '% das visitas' : '—'],
        ['Chegaram ao fechamento', number_format($r['fechamentos'], 0, ',', '.'), $r['visitas'] ? round($r['fechamentos'] / $r['visitas'] * 100) . '% das visitas' : '—'],
        ['Pedidos pagos', $r['pagos'] . ' de ' . $r['pedidos'], $r['conversao'] !== null ? 'conversão de ' . str_replace('.', ',', (string) $r['conversao']) . '%' : 'sem visitas contadas'],
        ['Faturamento', $moeda($r['faturamento']), 'pedidos pagos no período'],
        ['Ticket médio', $moeda($r['ticket']), 'por pedido pago'],
    ] as [$rotulo, $valor, $nota])
        <div class="col-6 col-lg-2"><div class="border rounded p-3 h-100 bg-body"><div class="text-muted small">{{ $rotulo }}</div><div class="fs-4 fw-bold" data-indicador="{{ $rotulo }}">{{ $valor }}</div><div class="small text-muted">{{ $nota }}</div></div></div>
    @endforeach
</div>

<x-erp.card title="Dia a dia" icon="calendar3" class="mb-3">
    <div class="table-responsive" style="max-height:420px">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Dia</th><th style="width:38%">Visitas</th><th class="text-end">Pedidos pagos</th><th style="width:30%">Vendido</th></tr></thead>
            <tbody>
            @foreach(array_reverse($r['porDia']) as $d)
                <tr>
                    <td>{{ $d['dia'] }}</td>
                    <td><div class="d-flex align-items-center gap-2"><div class="bg-primary rounded" style="height:8px;width:{{ max(0, round($d['visitas'] / $maior * 100)) }}%"></div><span class="small">{{ $d['visitas'] }}</span></div></td>
                    <td class="text-end">{{ $d['pedidos'] }}</td>
                    <td><div class="d-flex align-items-center gap-2"><div class="bg-success rounded" style="height:8px;width:{{ max(0, round($d['valor'] / $maiorValor * 100)) }}%"></div><span class="small text-nowrap">{{ $d['valor'] > 0 ? $moeda($d['valor']) : '—' }}</span></div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-erp.card>

<div class="row g-3">
    <div class="col-lg-6"><x-erp.card title="Mais vendidos" icon="trophy">
        @forelse($r['maisVendidos'] as $m)<div class="d-flex justify-content-between border-bottom py-1"><span>{{ $m->descricao }}</span><span class="text-nowrap ms-2"><strong>{{ $moeda($m->total) }}</strong> <span class="text-muted small">· {{ rtrim(rtrim(number_format((float) $m->qtd, 3, ',', '.'), '0'), ',') }} un.</span></span></div>
        @empty<p class="text-muted mb-0">Nenhum pedido pago no período.</p>@endforelse
    </x-erp.card></div>
    <div class="col-lg-6"><x-erp.card title="Mais vistos" icon="eye">
        @forelse($r['maisVistos'] as $m)<div class="d-flex justify-content-between border-bottom py-1"><span>{{ $m->descricao }}</span><span class="text-nowrap ms-2">{{ $m->vistas }} visita(s)</span></div>
        @empty<p class="text-muted mb-0">Ainda sem visitas contadas.</p>@endforelse
    </x-erp.card></div>
</div>
<p class="small text-muted mt-3">As visitas começaram a ser contadas em 15/11/2026 — uma por pessoa por dia, sem contar a equipe logada no ERP nem robôs de busca. Pedidos e valores vêm dos pedidos do ERP.</p>
@endsection
