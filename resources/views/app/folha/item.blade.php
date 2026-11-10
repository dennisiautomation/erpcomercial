@extends('layouts.app')
@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $novo = ! $item->exists;
@endphp

@section('title', $novo ? 'Nova guia ou benefício' : 'Editar ' . $item->nome)

@section('content')
<x-erp.page-header :title="$novo ? 'Nova guia ou benefício' : 'Editar — ' . $item->nome" subtitle="Despesa de pessoal que repete todo mês, com o valor que você digita" icon="bank">
    <a href="{{ route('app.folha.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="{{ $novo ? route('app.folha.itens.store') : route('app.folha.itens.update', $item) }}" id="formItem">
            @csrf
            @unless($novo) @method('PUT') @endunless

            <x-erp.form-section title="O que é" icon="tag">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="tipo" class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo" id="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                            @foreach(\App\Models\FolhaItem::TIPOS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('tipo', $item->tipo) === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label for="nome" class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" id="nome" maxlength="100" list="nomesComuns" class="form-control @error('nome') is-invalid @enderror"
                               value="{{ old('nome', $item->nome) }}" placeholder="Ex: Guia INSS, FGTS, Vale-transporte" required>
                        <datalist id="nomesComuns">
                            <option value="Guia INSS"></option><option value="Guia FGTS"></option><option value="IRRF sobre salários"></option>
                            <option value="Vale-transporte"></option><option value="Vale-refeição"></option><option value="Plano de saúde"></option>
                        </datalist>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="funcionario_id" class="form-label fw-semibold">De quem é</label>
                        <select name="funcionario_id" id="funcionario_id" class="form-select @error('funcionario_id') is-invalid @enderror">
                            <option value="">Da empresa (uma conta só por mês)</option>
                            @foreach($funcionarios as $f)
                                <option value="{{ $f->id }}" @selected((string) old('funcionario_id', $item->funcionario_id) === (string) $f->id)>{{ $f->name }}</option>
                            @endforeach
                        </select>
                        @error('funcionario_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Guia de imposto costuma ser da empresa. Benefício pode ser de um funcionário.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="favorecido_nome" class="form-label fw-semibold">Paga para</label>
                        <input type="text" name="favorecido_nome" id="favorecido_nome" maxlength="150" class="form-control @error('favorecido_nome') is-invalid @enderror"
                               value="{{ old('favorecido_nome', $item->favorecido_nome) }}" placeholder="Ex: INSS, Caixa (FGTS), operadora">
                        @error('favorecido_nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Em branco: paga ao próprio funcionário (ou usa o nome do item, se for da empresa).</div>
                    </div>
                </div>
            </x-erp.form-section>

            <x-erp.form-section title="Valor e vencimento" icon="currency-dollar">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="valor" class="form-label fw-semibold">Valor por mês <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="valor" id="valor" step="0.01" min="0" class="form-control @error('valor') is-invalid @enderror"
                                   value="{{ old('valor', $item->valor) }}" required>
                        </div>
                        @error('valor')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text">O valor de costume. O de cada mês você ajusta no Contas a Pagar.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="dia_vencimento" class="form-label fw-semibold">Dia do vencimento <span class="text-danger">*</span></label>
                        <input type="number" name="dia_vencimento" id="dia_vencimento" min="1" max="31" class="form-control form-control-lg @error('dia_vencimento') is-invalid @enderror"
                               value="{{ old('dia_vencimento', $item->dia_vencimento ?? 20) }}" required>
                        @error('dia_vencimento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold d-block">Vence quando</label>
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="vence_mes_seguinte" value="0">
                            <input class="form-check-input" type="checkbox" name="vence_mes_seguinte" value="1" id="vence_mes_seguinte"
                                   @checked(old('vence_mes_seguinte', $item->vence_mes_seguinte ?? true))>
                            <label class="form-check-label" for="vence_mes_seguinte">No mês seguinte ao da folha</label>
                        </div>
                        <div class="form-text">Ligado: INSS/FGTS de outubro vencem em novembro. Desligue para vale-transporte comprado no próprio mês.</div>
                    </div>
                </div>
            </x-erp.form-section>

            <x-erp.form-section title="Onde a despesa entra" icon="diagram-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label for="plano_conta_id" class="form-label fw-semibold">Plano de contas</label>
                        <select name="plano_conta_id" id="plano_conta_id" class="form-select @error('plano_conta_id') is-invalid @enderror">
                            <option value="">Sem classificação</option>
                            @foreach($planoContas->groupBy(fn ($p) => $p->parent?->nome ?? 'Outras') as $grupo => $contas)
                                <optgroup label="{{ $grupo }}">
                                    @foreach($contas as $p)
                                        <option value="{{ $p->id }}" @selected((string) old('plano_conta_id', $item->plano_conta_id) === (string) $p->id)>{{ $p->codigo }} — {{ $p->nome }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('plano_conta_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if($planoContas->isEmpty())
                            <div class="form-text"><a href="{{ route('app.plano-contas.index') }}" target="_blank">Crie as contas padrão</a> para separar encargos e benefícios no DRE.</div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label for="centro_custo_id" class="form-label fw-semibold">Centro de custo</label>
                        <select name="centro_custo_id" id="centro_custo_id" class="form-select @error('centro_custo_id') is-invalid @enderror">
                            <option value="">Nenhum</option>
                            @foreach($centrosCusto as $cc)
                                <option value="{{ $cc->id }}" @selected((string) old('centro_custo_id', $item->centro_custo_id) === (string) $cc->id)>{{ $cc->nome }}</option>
                            @endforeach
                        </select>
                        @error('centro_custo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="unidade_id" class="form-label fw-semibold">Loja</label>
                        <select name="unidade_id" id="unidade_id" class="form-select @error('unidade_id') is-invalid @enderror">
                            <option value="">A loja atual</option>
                            @foreach($unidades as $u)
                                <option value="{{ $u->id }}" @selected((string) old('unidade_id', $item->unidade_id) === (string) $u->id)>{{ $u->nome }}</option>
                            @endforeach
                        </select>
                        @error('unidade_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="ativo" value="0">
                    <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativo" @checked(old('ativo', $item->ativo ?? true))>
                    <label class="form-check-label fw-semibold" for="ativo">Ativo (entra na folha)</label>
                </div>
            </x-erp.form-section>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-erp-primary btn-lg px-4"><i class="bi bi-check-lg me-1"></i> Salvar</button>
                <a href="{{ route('app.folha.index') }}" class="btn btn-erp-outline btn-lg">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
