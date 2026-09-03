@extends('layouts.app')

@section('title', 'Trocas e Vales')

@section('content')
<x-erp.page-header title="Trocas e Devoluções" icon="arrow-repeat"
    subtitle="O que voltou para a loja e o que foi para o cliente em crédito ou dinheiro">
    <a href="{{ route('app.trocas.vales') }}" class="btn btn-erp-outline">
        <i class="bi bi-ticket-perforated me-1"></i> Vales emitidos
    </a>
    @if(\App\Http\Middleware\CheckPermission::can(auth()->user()->perfil?->value ?? '', 'trocas', 'criar'))
    <a href="{{ route('app.trocas.create') }}" class="btn btn-erp-primary">
        <i class="bi bi-box-arrow-in-left me-1"></i> Registrar devolução
    </a>
    @endif
</x-erp.page-header>

<div class="alert alert-info border-0 bg-info bg-opacity-10 mb-4">
    <div class="d-flex">
        <i class="bi bi-info-circle fs-5 me-2 text-info"></i>
        <div>
            <strong>Troca por outro produto é no PDV (tecla F6):</strong> o caixa localiza a venda, marca o que volta,
            bipa o que o cliente leva e a diferença é cobrada ou vira crédito na hora. Aqui você registra a
            <em>devolução sem levar nada</em> (gera vale ou dinheiro) e acompanha tudo o que já aconteceu.
            A política (prazo, sobra em vale ou dinheiro, senha do gerente) fica em
            <a href="{{ route('app.configuracoes.edit') }}">Configurações da Loja → Trocas</a>.
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><x-erp.stat-card icon="arrow-repeat" color="warning" :value="$stats['mes_qtd']" label="Trocas/devoluções no mês" /></div>
    <div class="col-md-3"><x-erp.stat-card icon="cash" color="danger" :value="'R$ ' . number_format($stats['mes_valor'], 2, ',', '.')" label="Valor devolvido no mês" /></div>
    <div class="col-md-3"><x-erp.stat-card icon="ticket-perforated" color="success" :value="$stats['vales_ativos']" label="Vales ativos" /></div>
    <div class="col-md-3"><x-erp.stat-card icon="wallet2" color="info" :value="'R$ ' . number_format($stats['vales_saldo'], 2, ',', '.')" label="Saldo em vales (a favor de clientes)" /></div>
</div>

<x-erp.filter-bar :action="route('app.trocas.index')">
    <div class="col-md-3">
        <input type="text" name="busca" class="form-control form-control-sm" placeholder="Nº da venda, cliente ou código do vale" value="{{ request('busca') }}">
    </div>
    @if($lojas->count() > 1)
    <div class="col-md-2">
        <select name="loja" class="form-select form-select-sm">
            <option value="todas">Todas as lojas</option>
            @foreach($lojas as $loja)
                <option value="{{ $loja->id }}" @selected(request('loja') == $loja->id)>{{ $loja->nome }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="col-md-2">
        <select name="tipo" class="form-select form-select-sm">
            <option value="">Troca e devolução</option>
            <option value="troca" @selected(request('tipo') === 'troca')>Só trocas</option>
            <option value="devolucao" @selected(request('tipo') === 'devolucao')>Só devoluções</option>
        </select>
    </div>
    <div class="col-md-2"><input type="date" name="data_inicio" class="form-control form-control-sm" value="{{ request('data_inicio') }}"></div>
    <div class="col-md-2"><input type="date" name="data_fim" class="form-control form-control-sm" value="{{ request('data_fim') }}"></div>
</x-erp.filter-bar>

<x-erp.data-table>
    <thead>
        <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Venda</th>
            <th>Cliente</th>
            @if($lojas->count() > 1)<th>Loja</th>@endif
            <th class="text-center">Itens</th>
            <th class="text-end">Valor devolvido</th>
            <th>Sobra</th>
            <th>Por</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($devolucoes as $dev)
        <tr>
            <td class="text-nowrap"><small>{{ $dev->created_at->format('d/m/Y H:i') }}</small></td>
            <td><span class="badge {{ $dev->tipo === 'troca' ? 'bg-info text-dark' : 'bg-warning text-dark' }}">{{ $dev->tipoLabel() }}</span>
                @if($dev->fora_politica)<i class="bi bi-shield-lock text-warning ms-1" title="Fora da política — {{ $dev->motivo_fora_politica }}"></i>@endif</td>
            <td>@if($dev->venda)<a href="{{ route('app.vendas.show', $dev->venda) }}">#{{ $dev->venda->numero }}</a>@else -@endif</td>
            <td>{{ $dev->venda->cliente->nome_razao_social ?? 'Consumidor' }}</td>
            @if($lojas->count() > 1)<td><small>{{ $dev->unidade->nome ?? '-' }}</small></td>@endif
            <td class="text-center">{{ $dev->itens_count }}</td>
            <td class="text-end fw-semibold">R$ {{ number_format($dev->valor_estornado, 2, ',', '.') }}</td>
            <td><small>{{ $dev->formaSobraLabel() }}@if($dev->vale) <code>{{ $dev->vale->codigo }}</code>
                <span class="badge bg-{{ $dev->vale->statusColor() }}">{{ $dev->vale->statusLabel() }}</span>@endif</small></td>
            <td><small>{{ $dev->user->name ?? '-' }}</small></td>
            <td class="text-end text-nowrap">
                <a href="{{ route('app.trocas.show', $dev) }}" class="btn btn-sm btn-outline-secondary" title="Detalhes"><i class="bi bi-eye"></i></a>
                <a href="{{ route('app.trocas.comprovante', $dev) }}?print=1" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimir comprovante"><i class="bi bi-printer"></i></a>
            </td>
        </tr>
        @empty
        <tr><td colspan="10"><x-erp.empty-state title="Nenhuma troca registrada" icon="arrow-repeat" description="Quando o cliente trouxer uma peça de volta, use o F6 no PDV ou o botão Registrar devolução." /></td></tr>
        @endforelse
    </tbody>
    <x-slot:pagination>{{ $devolucoes->links() }}</x-slot:pagination>
</x-erp.data-table>
@endsection
