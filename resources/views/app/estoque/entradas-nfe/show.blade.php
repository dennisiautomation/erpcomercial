@extends('layouts.app')

@section('title', 'Entrada NF-e ' . $entrada->numero)

@section('content')
@php $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.'); @endphp

<x-erp.page-header title="NF-e nº {{ $entrada->numero }} — no estoque" icon="box-seam"
    subtitle="{{ $entrada->fornecedor?->razao_social ?? $entrada->nome_emitente }}">
    <a href="{{ route('app.entradas-nfe.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="erp-card mb-3">
    <div class="card-body">
        <div class="row g-3 small">
            <div class="col-6 col-md-3">
                <span class="text-muted d-block">Lançada em</span>
                <strong>{{ $entrada->concluida_em?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</strong>
                <span class="text-muted d-block">por {{ $entrada->concluidor?->name }}</span>
            </div>
            <div class="col-6 col-md-3">
                <span class="text-muted d-block">Estoque</span>
                <strong>{{ $entrada->estoque?->nome }}</strong>
            </div>
            <div class="col-6 col-md-3">
                <span class="text-muted d-block">Custo dos produtos</span>
                <strong>{{ $entrada->atualizou_custo ? 'Atualizado' : 'Mantido' }}</strong>
            </div>
            <div class="col-6 col-md-3">
                <span class="text-muted d-block">Contas a pagar</span>
                <strong>{{ $entrada->contas_pagar_geradas ? $entrada->contas_pagar_geradas . ' criada(s)' : 'Não lançadas' }}</strong>
            </div>
            <div class="col-12">
                <span class="text-muted">Chave</span>
                <span class="font-monospace">{{ $entrada->chave_acesso }}</span>
                · <span class="text-muted">Total</span> <strong>R$ {{ $fmt($entrada->valor_total) }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="erp-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Na nota</th>
                    <th class="text-end">Qtd na nota</th>
                    <th>Produto</th>
                    <th class="text-end">Entrou</th>
                    <th class="text-end">Custo un.</th>
                </tr>
            </thead>
            <tbody class="small">
                @foreach($entrada->itens as $item)
                    <tr @class(['text-muted' => $item->acao === 'ignorar'])>
                        <td>{{ $item->descricao }} <span class="text-muted">· cód. {{ $item->codigo_fornecedor }}</span></td>
                        <td class="text-end">{{ $fmt($item->quantidade, 3) }} {{ $item->unidade_comercial }}</td>
                        <td>
                            @if($item->acao === 'ignorar')
                                <em>não lançado</em>
                            @elseif($item->produto)
                                <a href="{{ route('app.produtos.show', $item->produto_id) }}">{{ $item->produto->descricao }}</a>
                                @if($item->acao === 'criar') <span class="badge bg-info-subtle text-info">novo</span> @endif
                            @endif
                        </td>
                        <td class="text-end">{{ $item->acao === 'ignorar' ? '—' : $fmt($item->quantidadeEntrada(), 3) }}</td>
                        <td class="text-end">{{ $item->acao === 'ignorar' ? '—' : 'R$ ' . $fmt($item->custoUnitarioEntrada()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
