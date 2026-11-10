@extends('layouts.app')
@php $errors = $errors ?? new \Illuminate\Support\ViewErrorBag(); @endphp

@section('title', 'Folha — ' . $funcionario->name)

@section('content')
<x-erp.page-header :title="'Salário e vale — ' . $funcionario->name" subtitle="O que a folha lança todo mês no Contas a Pagar para este funcionário" icon="person-badge">
    <a href="{{ route('app.folha.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('app.folha.ficha.update', $funcionario) }}" id="formFicha">
            @csrf
            @method('PUT')

            <x-erp.form-section title="Contrato" icon="briefcase">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="cargo" class="form-label fw-semibold">Cargo</label>
                        <input type="text" name="cargo" id="cargo" maxlength="100" class="form-control @error('cargo') is-invalid @enderror"
                               value="{{ old('cargo', $ficha->cargo) }}" placeholder="Ex: Vendedora, Caixa, Gerente">
                        @error('cargo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="admissao" class="form-label fw-semibold">Admissão</label>
                        <input type="date" name="admissao" id="admissao" class="form-control @error('admissao') is-invalid @enderror"
                               value="{{ old('admissao', $ficha->admissao?->format('Y-m-d')) }}">
                        @error('admissao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="demissao" class="form-label fw-semibold">Desligamento</label>
                        <input type="date" name="demissao" id="demissao" class="form-control @error('demissao') is-invalid @enderror"
                               value="{{ old('demissao', $ficha->demissao?->format('Y-m-d')) }}">
                        @error('demissao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-text mb-3">A folha só lança os meses entre a admissão e o desligamento. Em branco = sem limite.</div>
            </x-erp.form-section>

            <x-erp.form-section title="Salário" icon="cash-coin">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="salario" class="form-label fw-semibold">Salário do mês <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="salario" id="salario" step="0.01" min="0" class="form-control @error('salario') is-invalid @enderror"
                                   value="{{ old('salario', $ficha->salario) }}" required>
                        </div>
                        @error('salario')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text">O valor que a empresa paga ao funcionário (líquido). O sistema não calcula descontos.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="dia_pagamento" class="form-label fw-semibold">Dia do pagamento <span class="text-danger">*</span></label>
                        <input type="number" name="dia_pagamento" id="dia_pagamento" min="1" max="31" class="form-control form-control-lg @error('dia_pagamento') is-invalid @enderror"
                               value="{{ old('dia_pagamento', $ficha->dia_pagamento ?? 5) }}" required>
                        @error('dia_pagamento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold d-block">Paga quando</label>
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="paga_mes_seguinte" value="0">
                            <input class="form-check-input" type="checkbox" name="paga_mes_seguinte" value="1" id="paga_mes_seguinte"
                                   @checked(old('paga_mes_seguinte', $ficha->paga_mes_seguinte ?? true))>
                            <label class="form-check-label" for="paga_mes_seguinte">No mês seguinte ao trabalhado</label>
                        </div>
                        <div class="form-text">Ligado: salário de outubro vence em novembro. Desligado: vence no próprio mês.</div>
                    </div>
                </div>
            </x-erp.form-section>

            <x-erp.form-section title="Vale (adiantamento)" icon="calendar2-week">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="vale_valor" class="form-label fw-semibold">Valor do vale</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="vale_valor" id="vale_valor" step="0.01" min="0" class="form-control @error('vale_valor') is-invalid @enderror"
                                   value="{{ old('vale_valor', $ficha->vale_valor) }}">
                        </div>
                        @error('vale_valor')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text">Zero = sem vale.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="vale_dia" class="form-label fw-semibold">Dia do vale</label>
                        <input type="number" name="vale_dia" id="vale_dia" min="1" max="31" class="form-control @error('vale_dia') is-invalid @enderror"
                               value="{{ old('vale_dia', $ficha->vale_dia ?? 20) }}">
                        @error('vale_dia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body py-2 small" id="resumoFicha" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
                <div class="form-text mb-3">O vale vence no próprio mês trabalhado e é descontado do salário: a folha lança o vale e, no dia do pagamento, só o que falta.</div>
            </x-erp.form-section>

            <x-erp.form-section title="Onde a despesa entra" icon="diagram-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="unidade_id" class="form-label fw-semibold">Loja</label>
                        <select name="unidade_id" id="unidade_id" class="form-select @error('unidade_id') is-invalid @enderror">
                            <option value="">A loja em que você estiver ao lançar</option>
                            @foreach($unidades as $u)
                                <option value="{{ $u->id }}" @selected((string) old('unidade_id', $ficha->unidade_id) === (string) $u->id)>{{ $u->nome }}</option>
                            @endforeach
                        </select>
                        @error('unidade_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="centro_custo_id" class="form-label fw-semibold">Centro de custo</label>
                        <select name="centro_custo_id" id="centro_custo_id" class="form-select @error('centro_custo_id') is-invalid @enderror">
                            <option value="">Nenhum</option>
                            @foreach($centrosCusto as $cc)
                                <option value="{{ $cc->id }}" @selected((string) old('centro_custo_id', $ficha->centro_custo_id) === (string) $cc->id)>{{ $cc->nome }}</option>
                            @endforeach
                        </select>
                        @error('centro_custo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if($centrosCusto->isEmpty())
                            <div class="form-text"><a href="{{ route('app.centros-custo.create') }}" target="_blank">Cadastre os centros de custo</a> para escolher aqui.</div>
                        @endif
                    </div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="na_folha" value="0">
                    <input class="form-check-input" type="checkbox" name="na_folha" value="1" id="na_folha" @checked(old('na_folha', $ficha->na_folha ?? true))>
                    <label class="form-check-label fw-semibold" for="na_folha">Entra na folha</label>
                    <div class="form-text">Desligue para parar de lançar este funcionário sem apagar os dados.</div>
                </div>
                <div class="mb-3">
                    <label for="observacoes" class="form-label fw-semibold">Observações</label>
                    <textarea name="observacoes" id="observacoes" rows="2" maxlength="500" class="form-control">{{ old('observacoes', $ficha->observacoes) }}</textarea>
                </div>
            </x-erp.form-section>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-erp-primary btn-lg px-4"><i class="bi bi-check-lg me-1"></i> Salvar</button>
                <a href="{{ route('app.folha.index') }}" class="btn btn-erp-outline btn-lg">Cancelar</a>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <x-erp.card title="Como funciona" icon="lightbulb">
            <div class="small text-muted">
                <p>Aqui fica o valor de costume. Ao <strong>lançar a folha</strong>, o sistema cria as contas dos meses que você escolher no Contas a Pagar.</p>
                <p>Mudou o salário? Salve aqui e lance de novo marcando <strong>atualizar pendentes</strong>.</p>
                <p class="mb-0">Um mês saiu diferente (falta, hora extra)? Ajuste só aquela conta no Contas a Pagar, em Editar ou na baixa.</p>
            </div>
        </x-erp.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const sal = document.getElementById('salario'), vale = document.getElementById('vale_valor');
    const diaP = document.getElementById('dia_pagamento'), diaV = document.getElementById('vale_dia');
    const seg = document.getElementById('paga_mes_seguinte'), out = document.getElementById('resumoFicha');
    const brl = v => 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function resumo() {
        const s = parseFloat(sal.value) || 0, v = parseFloat(vale.value) || 0;
        out.textContent = '';
        const linha = (t, forte) => { const d = document.createElement('div'); d.textContent = t; if (forte) d.className = 'fw-semibold'; out.appendChild(d); };
        if (s <= 0) { linha('Informe o salário para ver o que será lançado.'); return; }
        if (v > s) { const d = document.createElement('div'); d.className = 'text-danger fw-semibold'; d.textContent = 'O vale está maior que o salário.'; out.appendChild(d); return; }
        linha('Todo mês a folha lança:', true);
        if (v > 0) linha('• Vale: ' + brl(v) + ' no dia ' + (diaV.value || 20));
        linha('• Salário: ' + brl(s - v) + ' no dia ' + (diaP.value || 5) + (seg.checked ? ' do mês seguinte' : ''));
    }
    [sal, vale, diaP, diaV, seg].forEach(el => el.addEventListener('input', resumo));
    seg.addEventListener('change', resumo);
    resumo();
})();
</script>
@endpush
