@extends('layouts.app')
@php $errors = $errors ?? new \Illuminate\Support\ViewErrorBag(); @endphp

@section('title', 'Nova Conta a Pagar')

@section('content')
<x-erp.page-header title="Nova Conta a Pagar" subtitle="Despesa, salário, imposto ou benefício — avulsa, parcelada ou recorrente" icon="plus-circle">
    <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('app.contas-pagar.store') }}">
            @csrf

            @include('app.financeiro.contas-pagar._campos', ['conta' => null])

            {{-- Parcelas: só na criação --}}
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="parcelas" class="form-label fw-semibold">Parcelas</label>
                    <div class="input-group">
                        <input type="number" name="parcelas" id="parcelas" min="1" max="48"
                            class="form-control @error('parcelas') is-invalid @enderror"
                            value="{{ old('parcelas', 1) }}">
                        <span class="input-group-text">x</span>
                    </div>
                    @error('parcelas')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Divide o valor. Para despesa que repete todo mês, use a recorrência abaixo.</div>
                </div>
            </div>

            <x-erp.form-section title="Recorrência" icon="arrow-repeat">
                <div class="card border-0 bg-light mb-4">
                    <div class="card-body">
                        <div class="form-check form-switch mb-0">
                            <input type="hidden" name="recorrente" value="0">
                            <input class="form-check-input" type="checkbox" name="recorrente" value="1" id="recorrente"
                                {{ old('recorrente') ? 'checked' : '' }} style="transform:scale(1.2);">
                            <label class="form-check-label fw-bold ms-2" for="recorrente">
                                <i class="bi bi-arrow-repeat me-1"></i> Conta Recorrente
                            </label>
                        </div>
                        <div id="recorrencia-options" class="{{ old('recorrente') ? '' : 'd-none' }} mt-3">
                            <label for="recorrencia_tipo" class="form-label fw-semibold small">Repete a cada</label>
                            <select name="recorrencia_tipo" id="recorrencia_tipo" class="form-select">
                                <option value="mensal" {{ old('recorrencia_tipo') == 'mensal' ? 'selected' : '' }}>Mensal</option>
                                <option value="bimestral" {{ old('recorrencia_tipo') == 'bimestral' ? 'selected' : '' }}>Bimestral</option>
                                <option value="trimestral" {{ old('recorrencia_tipo') == 'trimestral' ? 'selected' : '' }}>Trimestral</option>
                                <option value="semestral" {{ old('recorrencia_tipo') == 'semestral' ? 'selected' : '' }}>Semestral</option>
                                <option value="anual" {{ old('recorrencia_tipo') == 'anual' ? 'selected' : '' }}>Anual</option>
                            </select>
                            <div class="alert alert-info border-0 bg-info bg-opacity-10 mt-2 mb-0 small">
                                <i class="bi bi-info-circle me-1"></i> Os próximos 12 vencimentos já ficam lançados com este valor. Quando o valor de um mês mudar (guia de imposto, vale-transporte), ajuste em Editar ou na hora de dar a baixa.
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Parcelas Preview --}}
                <div id="parcelas-preview" class="card border-0 bg-light mb-4 d-none">
                    <div class="card-header bg-primary bg-opacity-10 border-0">
                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-list-ol me-1"></i> Prévia das Parcelas</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3">Parcela</th>
                                        <th>Vencimento</th>
                                        <th class="text-end pe-3">Valor</th>
                                    </tr>
                                </thead>
                                <tbody id="parcelas-list"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="observacoes" class="form-label fw-semibold">Observações</label>
                    <textarea name="observacoes" id="observacoes" rows="3"
                        class="form-control @error('observacoes') is-invalid @enderror"
                        placeholder="Informações adicionais...">{{ old('observacoes') }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-erp-primary btn-lg px-4">
                        <i class="bi bi-check-lg me-1"></i> Salvar Conta
                    </button>
                    <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-erp-outline btn-lg">Cancelar</a>
                </div>
            </x-erp.form-section>
        </form>
    </div>

    {{-- Sidebar help --}}
    <div class="col-lg-4">
        <x-erp.card title="Dicas" icon="lightbulb">
            <div class="small text-muted">
                <div class="mb-3">
                    <div class="fw-semibold text-dark mb-1">Parcelamento</div>
                    O valor sera dividido igualmente entre as parcelas. Cada parcela vence com intervalo de 1 mês.
                </div>
                <div class="mb-3">
                    <div class="fw-semibold text-dark mb-1">Conta Recorrente</div>
                    Para o que repete: salário, vale, guias de INSS/FGTS, vale-transporte, aluguel. Os próximos 12 meses já entram no fluxo de caixa; o valor de cada mês você ajusta antes de pagar.
                </div>
                <div class="mb-0">
                    <div class="fw-semibold text-dark mb-1">Plano de contas e centro de custo</div>
                    Classifique a despesa para ela aparecer separada no DRE (salários, encargos, benefícios).
                </div>
            </div>
        </x-erp.card>

        @if($planoContas->isEmpty())
        <x-erp.card title="Plano de contas" icon="diagram-3" class="mt-4">
            <p class="small text-muted mb-3">Esta empresa ainda não tem plano de contas. Crie o padrão com um clique: salários, vale, 13º, férias, INSS, FGTS, vale-transporte, aluguel, energia e outras.</p>
            <form method="POST" action="{{ route('app.plano-contas.padrao') }}">
                @csrf
                <button type="submit" class="btn btn-erp-outline w-100">
                    <i class="bi bi-magic me-1"></i> Criar contas padrão
                </button>
            </form>
            <div class="form-text">Você volta para esta tela; o que já digitou aqui precisa ser preenchido de novo.</div>
        </x-erp.card>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Toggle recorrencia options
    document.getElementById('recorrente').addEventListener('change', function() {
        document.getElementById('recorrencia-options').classList.toggle('d-none', !this.checked);
        updateParcelasPreview();
    });

    // Parcelas preview
    function updateParcelasPreview() {
        const valor = parseFloat(document.getElementById('valor').value) || 0;
        const vencimento = document.getElementById('vencimento').value;
        const recorrente = document.getElementById('recorrente').checked;
        const parcelas = recorrente ? 12 : (parseInt(document.getElementById('parcelas').value) || 1);
        const preview = document.getElementById('parcelas-preview');
        const list = document.getElementById('parcelas-list');

        if (valor > 0 && vencimento && parcelas > 1) {
            const valorParcela = recorrente ? valor : Math.floor((valor / parcelas) * 100) / 100;
            let html = '';
            let date = new Date(vencimento + 'T12:00:00');

            // Determine month increment for recurrence
            let meses = 1;
            if (recorrente) {
                const tipo = document.getElementById('recorrencia_tipo').value;
                meses = {'mensal':1,'bimestral':2,'trimestral':3,'semestral':6,'anual':12}[tipo] || 1;
            }

            for (let i = 1; i <= Math.min(parcelas, 48); i++) {
                const vp = (!recorrente && i === parcelas)
                    ? (valor - (valorParcela * (parcelas - 1)))
                    : valorParcela;
                const dateStr = date.toLocaleDateString('pt-BR');
                const valorStr = parseFloat(vp).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                html += `<tr>
                    <td class="ps-3"><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">${i}/${parcelas}</span></td>
                    <td>${dateStr}</td>
                    <td class="text-end pe-3 fw-semibold">R$ ${valorStr}</td>
                </tr>`;

                date.setMonth(date.getMonth() + meses);
            }

            list.innerHTML = html;
            preview.classList.remove('d-none');
        } else {
            preview.classList.add('d-none');
        }
    }

    document.getElementById('valor').addEventListener('input', updateParcelasPreview);
    document.getElementById('parcelas').addEventListener('input', updateParcelasPreview);
    document.getElementById('vencimento').addEventListener('change', updateParcelasPreview);
    document.getElementById('recorrencia_tipo').addEventListener('change', updateParcelasPreview);

    // Trigger on load
    updateParcelasPreview();
</script>
@endpush
