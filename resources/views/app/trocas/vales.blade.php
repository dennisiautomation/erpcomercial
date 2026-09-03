@extends('layouts.app')

@section('title', 'Vales')

@section('content')
<x-erp.page-header title="Vales (crédito na loja)" icon="ticket-perforated"
    subtitle="Créditos gerados nas trocas — o cliente usa no PDV pelo código">
    <a href="{{ route('app.trocas.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i> Trocas</a>
</x-erp.page-header>

<x-erp.filter-bar :action="route('app.trocas.vales')">
    <div class="col-md-3">
        <input type="text" name="busca" class="form-control form-control-sm" placeholder="Código do vale ou cliente" value="{{ request('busca') }}">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
            <option value="ativo" @selected($status === 'ativo')>Ativos</option>
            <option value="utilizado" @selected($status === 'utilizado')>Utilizados</option>
            <option value="expirado" @selected($status === 'expirado')>Vencidos</option>
            <option value="cancelado" @selected($status === 'cancelado')>Cancelados</option>
            <option value="todos" @selected($status === 'todos')>Todos</option>
        </select>
    </div>
</x-erp.filter-bar>

<x-erp.data-table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Emitido</th>
            <th>Cliente</th>
            <th>Origem</th>
            <th class="text-end">Valor</th>
            <th class="text-end">Saldo</th>
            <th>Validade</th>
            <th>Situação</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($vales as $vale)
        <tr>
            <td><code class="fs-6">{{ $vale->codigo }}</code></td>
            <td class="text-nowrap"><small>{{ $vale->created_at->format('d/m/Y') }} · {{ $vale->unidade->nome ?? '' }}</small></td>
            <td>{{ $vale->cliente->nome_razao_social ?? 'Consumidor' }}</td>
            <td><small>@if($vale->devolucao){{ $vale->devolucao->tipoLabel() }} da venda #{{ $vale->devolucao->venda->numero ?? '?' }}@else -@endif</small></td>
            <td class="text-end">R$ {{ number_format($vale->valor, 2, ',', '.') }}</td>
            <td class="text-end fw-semibold">R$ {{ number_format($vale->saldo, 2, ',', '.') }}</td>
            <td><small>{{ $vale->validade?->format('d/m/Y') ?? 'sem validade' }}</small></td>
            <td><span class="badge bg-{{ $vale->statusColor() }}">{{ $vale->statusLabel() }}</span></td>
            <td class="text-end text-nowrap">
                <a href="{{ route('app.trocas.vales.imprimir', $vale) }}?print=1" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimir vale"><i class="bi bi-printer"></i></a>
                @if($vale->devolucao)<a href="{{ route('app.trocas.show', $vale->devolucao) }}" class="btn btn-sm btn-outline-secondary" title="Ver troca"><i class="bi bi-eye"></i></a>@endif
                @if($vale->status === 'ativo' && \App\Http\Middleware\CheckPermission::can(auth()->user()->perfil?->value ?? '', 'trocas', 'editar'))
                <form method="POST" action="{{ route('app.trocas.vales.cancelar', $vale) }}" class="d-inline"
                      data-confirm="Cancelar o vale {{ $vale->codigo }}? O saldo de R$ {{ number_format($vale->saldo, 2, ',', '.') }} deixa de valer para o cliente.">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger" title="Cancelar vale"><i class="bi bi-x-circle"></i></button>
                </form>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="9"><x-erp.empty-state title="Nenhum vale" icon="ticket-perforated" description="Os vales nascem nas trocas em que o cliente devolve mais do que leva." /></td></tr>
        @endforelse
    </tbody>
    <x-slot:pagination>{{ $vales->links() }}</x-slot:pagination>
</x-erp.data-table>
@endsection
