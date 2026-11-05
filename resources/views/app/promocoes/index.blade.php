@extends('layouts.app')

@section('title', 'Promoções')

@php
    $classe = ['agendada' => 'pendente', 'vigente' => 'ativa', 'encerrada' => 'fechado', 'encerrada_antes' => 'cancelada'];
@endphp

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-megaphone me-2"></i>Promoções</h4>
        <div class="subtitle">Preço promocional por período, nas lojas escolhidas: à vista e no cartão de cada produto.</div>
    </div>
    <a href="{{ route('app.promocoes.create') }}" class="btn btn-erp btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova promoção
    </a>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('app.promocoes.index') }}" class="row g-3 align-items-end erp-form">
        <div class="col-md-4">
            <label for="filtro-status" class="form-label">Situação</label>
            <select id="filtro-status" name="status" class="form-select" onchange="this.form.submit()">
                <option value="">Todas</option>
                <option value="vigente" @selected($filtro === 'vigente')>Em andamento</option>
                <option value="agendada" @selected($filtro === 'agendada')>Agendadas</option>
                <option value="encerrada" @selected($filtro === 'encerrada')>Concluídas</option>
                <option value="encerrada_antes" @selected($filtro === 'encerrada_antes')>Encerradas antes do fim</option>
            </select>
        </div>
    </form>
</div>

<div class="erp-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Promoção</th>
                        <th>Período</th>
                        <th>Lojas</th>
                        <th class="text-center">Produtos</th>
                        <th class="text-center">Situação</th>
                        <th class="text-center" style="width: 170px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promocoes as $p)
                        <tr>
                            <td><a href="{{ route('app.promocoes.show', $p) }}"><strong>{{ $p->nome }}</strong></a></td>
                            <td class="text-nowrap">{{ $p->data_inicio->format('d/m/Y') }} a {{ $p->data_fim->format('d/m/Y') }}</td>
                            <td>{{ $p->unidades->pluck('nome')->join(', ') }}</td>
                            <td class="text-center">{{ $p->itens_count }}</td>
                            <td class="text-center"><span class="badge-status {{ $classe[$p->status()] }}">{{ $p->statusRotulo() }}</span></td>
                            <td class="text-center text-nowrap">
                                <a href="{{ route('app.promocoes.show', $p) }}" class="btn btn-sm btn-erp btn-erp-outline" title="Ver"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('app.promocoes.create', ['duplicar' => $p->id]) }}" class="btn btn-sm btn-erp btn-erp-outline" title="Duplicar: nova promoção com estes produtos e preços"><i class="bi bi-copy"></i> Duplicar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-megaphone fs-3 d-block mb-2"></i>
                                Nenhuma promoção {{ $filtro !== '' ? 'nesta situação' : 'ainda' }}.
                                <a href="{{ route('app.promocoes.create') }}">Criar a primeira</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
