@extends('layouts.app')
@php $errors = $errors ?? new \Illuminate\Support\ViewErrorBag(); @endphp

@section('title', 'Folha de Pagamento')

@section('content')
@php
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $mesesNome = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $mesLabel = fn ($d) => $mesesNome[$d->month] . '/' . $d->year;
    $totalSalarios = $fichas->where('na_folha', true)->sum(fn ($f) => (float) $f->salario);
    $totalItens = $itens->where('ativo', true)->sum(fn ($i) => (float) $i->valor);
    $faltaLancar = $resumo->sum(fn ($r) => max(0, $r['linhas'] - $r['lancadas']));
@endphp

<x-erp.page-header title="Folha de Pagamento" subtitle="Salários, vales, guias e benefícios — lançados no Contas a Pagar para programar e dar baixa" icon="people">
    <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-wallet2 me-1"></i> Contas a Pagar
    </a>
</x-erp.page-header>

<div class="alert alert-info border-0 bg-info bg-opacity-10 small">
    <i class="bi bi-info-circle me-1"></i>
    A folha <strong>não paga nada</strong>: ela lança as contas no <strong>Contas a Pagar</strong>. É lá que você ajusta o valor do mês (guia, vale-transporte) e dá a baixa.
    O fluxo de caixa e o DRE leem o Contas a Pagar.
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <x-erp.stat-card icon="person-badge" color="primary" :value="number_format($totalSalarios, 2, ',', '.')" label="Salários por mês" prefix="R$ " />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="bank" color="warning" :value="number_format($totalItens, 2, ',', '.')" label="Guias e benefícios por mês" prefix="R$ " />
    </div>
    <div class="col-md-4">
        <x-erp.stat-card icon="calendar-check" color="success" :value="number_format($linhasMes->sum('valor'), 2, ',', '.')" :label="'Folha de ' . $mesLabel($inicio)" prefix="R$ " />
    </div>
</div>

{{-- 1. Funcionários --}}
<x-erp.card title="1. Funcionários — salário e vale" icon="person-badge" class="mb-4">
    <div class="table-responsive">
        <table class="erp-table" id="tabelaFichas">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Cargo</th>
                    <th class="text-end">Salário</th>
                    <th class="text-end">Vale</th>
                    <th>Pagamento</th>
                    <th>Centro de custo</th>
                    <th class="text-center">Na folha</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($funcionarios as $f)
                    @php $ficha = $fichas->get($f->id); @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $f->name }}</div>
                            @if($f->status !== 'ativo')<small class="text-danger">Usuário inativo</small>@endif
                            @if($ficha?->demissao)<small class="text-muted d-block">Desligado em {{ $ficha->demissao->format('d/m/Y') }}</small>@endif
                        </td>
                        <td>{{ $ficha?->cargo ?: '-' }}</td>
                        <td class="text-end">{{ $ficha && $ficha->salario > 0 ? $brl($ficha->salario) : '-' }}</td>
                        <td class="text-end">
                            @if($ficha && $ficha->vale_valor > 0)
                                {{ $brl($ficha->vale_valor) }}<br><small class="text-muted">dia {{ $ficha->vale_dia }}</small>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($ficha && $ficha->salario > 0)
                                dia {{ $ficha->dia_pagamento }}<br>
                                <small class="text-muted">{{ $ficha->paga_mes_seguinte ? 'do mês seguinte' : 'do próprio mês' }}</small>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $ficha?->centroCusto?->nome ?? '-' }}</td>
                        <td class="text-center">
                            @if(! $ficha || $ficha->salario <= 0)
                                <span class="badge bg-light text-dark border">Sem salário</span>
                            @elseif($ficha->na_folha)
                                <span class="badge bg-success">Sim</span>
                            @else
                                <span class="badge bg-secondary">Não</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('app.folha.ficha.edit', $f) }}" class="btn btn-sm btn-erp-outline" title="Salário e vale de {{ $f->name }}">
                                <i class="bi bi-pencil me-1"></i>{{ $ficha ? 'Editar' : 'Preencher' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-erp.empty-state icon="people" title="Nenhum funcionário cadastrado" description="Cadastre em Cadastros → Funcionários." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-erp.card>

{{-- 2. Guias e benefícios --}}
<x-erp.card title="2. Guias e benefícios — valor que você digita" icon="bank" class="mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div class="small text-muted">INSS, FGTS, IRRF, vale-transporte, vale-refeição, plano de saúde. O sistema não calcula imposto: você põe o valor de costume e ajusta o de cada mês no Contas a Pagar quando a guia chegar.</div>
        <a href="{{ route('app.folha.itens.create') }}" class="btn btn-erp-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Nova guia ou benefício
        </a>
    </div>
    <div class="table-responsive">
        <table class="erp-table" id="tabelaItens">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Tipo</th>
                    <th>Paga para</th>
                    <th class="text-end">Valor por mês</th>
                    <th>Vencimento</th>
                    <th>Plano de contas</th>
                    <th class="text-center">Ativo</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($itens as $item)
                    <tr class="{{ $item->ativo ? '' : 'text-muted' }}">
                        <td>
                            <div class="fw-semibold">{{ $item->nome }}</div>
                            @if($item->funcionario)<small class="text-muted">de {{ $item->funcionario->name }}</small>@else<small class="text-muted">da empresa</small>@endif
                        </td>
                        <td>{{ \App\Models\FolhaItem::TIPOS[$item->tipo] ?? $item->tipo }}</td>
                        <td>{{ $item->favorecido_nome ?: ($item->funcionario?->name ?? $item->nome) }}</td>
                        <td class="text-end fw-semibold">{{ $brl($item->valor) }}</td>
                        <td>dia {{ $item->dia_vencimento }}<br><small class="text-muted">{{ $item->vence_mes_seguinte ? 'do mês seguinte' : 'do próprio mês' }}</small></td>
                        <td>{{ $item->planoConta ? $item->planoConta->codigo . ' — ' . $item->planoConta->nome : '-' }}</td>
                        <td class="text-center">
                            <span class="badge {{ $item->ativo ? 'bg-success' : 'bg-secondary' }}">{{ $item->ativo ? 'Sim' : 'Não' }}</span>
                        </td>
                        <td class="text-center">
                            <div class="action-btns">
                                <a href="{{ route('app.folha.itens.edit', $item) }}" class="btn btn-sm btn-erp-outline" title="Editar"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('app.folha.itens.destroy', $item) }}"
                                      data-confirm="Excluir &quot;{{ $item->nome }}&quot; da folha? As contas que já foram lançadas continuam no Contas a Pagar.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-erp.empty-state icon="bank" title="Nenhuma guia ou benefício" description="Cadastre INSS, FGTS, vale-transporte e o que mais repete todo mês." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-erp.card>

{{-- 3. Lançar --}}
<x-erp.card title="3. Lançar no Contas a Pagar" icon="calendar-plus" class="mb-4">
    <form method="GET" action="{{ route('app.folha.index') }}" class="row g-3 align-items-end mb-3" id="formPeriodo">
        <div class="col-md-3">
            <label for="inicio" class="form-label fw-semibold">A partir do mês</label>
            <input type="month" name="inicio" id="inicio" class="form-control" value="{{ $inicio->format('Y-m') }}" required>
        </div>
        <div class="col-md-3">
            <label for="meses" class="form-label fw-semibold">Quantos meses</label>
            <select name="meses" id="meses" class="form-select">
                @foreach([1, 3, 6, 12, 24] as $n)
                    <option value="{{ $n }}" @selected($meses === $n)>{{ $n }} {{ $n === 1 ? 'mês' : 'meses' }}</option>
                @endforeach
                @if(! in_array($meses, [1, 3, 6, 12, 24], true))
                    <option value="{{ $meses }}" selected>{{ $meses }} meses</option>
                @endif
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-erp-outline"><i class="bi bi-eye me-1"></i> Ver período</button>
        </div>
    </form>

    <div class="table-responsive mb-3">
        <table class="erp-table" id="tabelaResumo">
            <thead>
                <tr>
                    <th>Mês da folha</th>
                    <th class="text-end">Previsto</th>
                    <th class="text-center">Contas</th>
                    <th class="text-end">Já lançado</th>
                    <th class="text-end">Pago</th>
                    <th class="text-center">Situação</th>
                    <th class="text-center">Ver</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumo as $r)
                    @php $falta = max(0, $r['linhas'] - $r['lancadas']); @endphp
                    <tr>
                        <td class="fw-semibold">{{ $mesLabel($r['competencia']) }}</td>
                        <td class="text-end">{{ $brl($r['previsto']) }}</td>
                        <td class="text-center">{{ $r['lancadas'] }}/{{ $r['linhas'] }}</td>
                        <td class="text-end">{{ $r['lancadas'] ? $brl($r['lancado']) : '-' }}</td>
                        <td class="text-end">{{ $r['pagas'] ? $brl($r['pago']) : '-' }}</td>
                        <td class="text-center">
                            @if($r['linhas'] === 0 && $r['lancadas'] === 0)
                                <span class="badge bg-light text-dark border">Nada a lançar</span>
                            @elseif($falta === 0)
                                <span class="badge bg-success">Lançado</span>
                            @elseif($r['lancadas'] > 0)
                                <span class="badge bg-warning text-dark">Faltam {{ $falta }}</span>
                            @else
                                <span class="badge bg-secondary">Não lançado</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($r['lancadas'])
                                <a class="btn btn-sm btn-erp-outline" title="Ver as contas deste mês no Contas a Pagar"
                                   href="{{ route('app.contas-pagar.index', ['competencia' => $r['competencia']->format('Y-m'), 'folha' => 1]) }}"><i class="bi bi-box-arrow-up-right"></i></a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('app.folha.lancar') }}" id="formLancar"
          data-confirm="Lançar a folha de {{ $mesLabel($inicio) }}{{ $meses > 1 ? ' até ' . $mesLabel($inicio->copy()->addMonthsNoOverflow($meses - 1)) : '' }} no Contas a Pagar? O que já foi lançado não é duplicado.">
        @csrf
        <input type="hidden" name="inicio" value="{{ $inicio->format('Y-m') }}">
        <input type="hidden" name="meses" value="{{ $meses }}">
        <div class="form-check mb-3">
            <input type="hidden" name="atualizar_pendentes" value="0">
            <input class="form-check-input" type="checkbox" name="atualizar_pendentes" value="1" id="atualizar_pendentes">
            <label class="form-check-label" for="atualizar_pendentes">
                <strong>Atualizar o valor das contas pendentes já lançadas</strong>
                <span class="d-block small text-muted">Marque depois de mudar um salário ou o valor de uma guia aqui na folha. Atenção: sobrescreve ajustes que você fez à mão em contas pendentes deste período. Conta paga nunca muda.</span>
            </label>
        </div>
        <button type="submit" class="btn btn-erp-primary btn-lg" @disabled($linhasMes->isEmpty() && $resumo->sum('linhas') === 0)>
            <i class="bi bi-calendar-plus me-1"></i>
            Lançar {{ $meses === 1 ? $mesLabel($inicio) : $meses . ' meses' }} no Contas a Pagar
        </button>
        @if($faltaLancar === 0 && $resumo->sum('linhas') > 0)
            <span class="ms-2 text-success small"><i class="bi bi-check-circle me-1"></i>Este período já está todo lançado.</span>
        @endif
    </form>
</x-erp.card>

{{-- Prévia do primeiro mês --}}
@if($linhasMes->isNotEmpty())
<x-erp.card :title="'O que entra na folha de ' . $mesLabel($inicio)" icon="list-check">
    <div class="table-responsive">
        <table class="erp-table" id="tabelaPrevia">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Conta</th>
                    <th>Paga para</th>
                    <th>Vencimento</th>
                    <th>Plano de contas</th>
                    <th class="text-end">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($linhasMes as $l)
                    <tr>
                        <td>{{ $l['grupo'] }}</td>
                        <td>{{ $l['descricao'] }}</td>
                        <td>{{ $l['favorecido'] }}</td>
                        <td>{{ $l['vencimento']->format('d/m/Y') }}</td>
                        <td>{{ $l['plano'] ? $l['plano']->codigo . ' — ' . $l['plano']->nome : '-' }}</td>
                        <td class="text-end fw-semibold">{{ $brl($l['valor']) }}</td>
                    </tr>
                @endforeach
                <tr class="table-active">
                    <td colspan="5" class="fw-bold">Total do mês</td>
                    <td class="text-end fw-bold">{{ $brl($linhasMes->sum('valor')) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @if($linhasMes->contains(fn ($l) => ! $l['plano']))
        <div class="form-text mt-2">
            Há linhas sem plano de contas — elas caem em "sem classificação" no DRE.
            <a href="{{ route('app.plano-contas.index') }}">Crie as contas padrão</a> (salário e vale se ligam sozinhos) e escolha o plano em cada guia.
        </div>
    @endif
</x-erp.card>
@endif
@endsection
