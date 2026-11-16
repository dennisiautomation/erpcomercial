@extends('layouts.app')

@section('title', $nomeForma . ' — quem me deve')

@section('content')
<x-erp.page-header :title="$nomeForma . ' — quem me deve'" subtitle="Clientes que compraram para pagar depois, direto com a loja" icon="journal-text">
</x-erp.page-header>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <x-erp.stat-card icon="people" color="primary" :value="$devedores->count()" label="Clientes devendo" />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="clock-history" color="warning" :value="number_format($totalAberto, 2, ',', '.')" label="Total em aberto" prefix="R$ " />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="exclamation-triangle" color="danger" :value="number_format($totalVencido, 2, ',', '.')" label="Já vencido" prefix="R$ " />
    </div>
</div>

<x-erp.filter-bar :action="route('app.fiado.index')">
    <div class="col-md-5">
        <label for="campo-busca" class="form-label fw-semibold small text-muted">Cliente (nome ou CPF)</label>
        <input id="campo-busca" type="search" name="busca" class="form-control" value="{{ request('busca') }}" placeholder="Digite o nome ou o CPF">
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="vencidos" value="1" id="campo-vencidos" @checked(request()->boolean('vencidos'))>
            <label class="form-check-label" for="campo-vencidos">Só quem está com conta vencida</label>
        </div>
    </div>
    <div class="col-auto">
        <a href="{{ route('app.fiado.index') }}" class="btn btn-erp-outline"><i class="bi bi-x-lg me-1"></i> Limpar</a>
    </div>
</x-erp.filter-bar>

<x-erp.data-table>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Telefone</th>
            <th class="text-end">Deve</th>
            <th class="text-end">Vencido</th>
            <th>Vence primeiro</th>
            <th class="text-end">Limite</th>
            <th class="text-center">Ações</th>
        </tr>
    </thead>
    <tbody>
        @forelse($devedores as $d)
        @php $atrasado = (float) $d->vencido > 0.004; @endphp
        <tr class="{{ $atrasado ? 'border-start border-danger border-3' : '' }}">
            <td>
                <div class="fw-semibold">{{ $d->nome_razao_social }}</div>
                @if($d->cpf_cnpj)<small class="text-muted">{{ $d->cpf_cnpj }}</small>@endif
            </td>
            <td>{{ $d->whatsapp ?: ($d->telefone ?: '—') }}</td>
            <td class="text-end fw-bold">R$ {{ number_format($d->devendo, 2, ',', '.') }}</td>
            <td class="text-end {{ $atrasado ? 'text-danger fw-semibold' : 'text-muted' }}">R$ {{ number_format($d->vencido, 2, ',', '.') }}</td>
            <td class="{{ $atrasado ? 'text-danger' : '' }}">{{ \Carbon\Carbon::parse($d->primeiro_venc)->format('d/m/Y') }}</td>
            <td class="text-end">
                @if((float) $d->limite_credito > 0)
                    R$ {{ number_format($d->limite_credito, 2, ',', '.') }}
                @else
                    <small class="text-muted">sem limite</small>
                @endif
            </td>
            <td class="text-center">
                <a href="{{ route('app.fiado.cliente', $d->cliente_id) }}" class="btn btn-sm btn-erp-primary">
                    <i class="bi bi-cash-coin me-1"></i> Ver e receber
                </a>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="7">
                <x-erp.empty-state title="Ninguém devendo" icon="emoji-smile"
                    :description="'Quando um cliente comprar no ' . $nomeForma . ' no PDV, ele aparece aqui até pagar.'" />
            </td>
        </tr>
        @endforelse
    </tbody>
</x-erp.data-table>
@endsection
