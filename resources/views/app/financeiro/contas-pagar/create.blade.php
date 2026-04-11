@extends('layouts.app')

@section('title', 'Nova Conta a Pagar')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-plus-circle me-2"></i>Nova Conta a Pagar</h4>
        <p class="text-muted mb-0 small">Cadastre uma nova despesa ou conta parcelada</p>
    </div>
    <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('app.contas-pagar.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="fornecedor_id" class="form-label fw-semibold">Fornecedor <span class="text-danger">*</span></label>
                        <select name="fornecedor_id" id="fornecedor_id" class="form-select form-select-lg @error('fornecedor_id') is-invalid @enderror" required>
                            <option value="">Selecione o fornecedor...</option>
                            @foreach($fornecedores as $fornecedor)
                                <option value="{{ $fornecedor->id }}" {{ old('fornecedor_id') == $fornecedor->id ? 'selected' : '' }}>
                                    {{ $fornecedor->razao_social }}
                                </option>
                            @endforeach
                        </select>
                        @error('fornecedor_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="descricao" class="form-label fw-semibold">Descricao <span class="text-danger">*</span></label>
                        <input type="text" name="descricao" id="descricao"
                            class="form-control @error('descricao') is-invalid @enderror"
                            value="{{ old('descricao') }}" placeholder="Ex: Compra de material, Aluguel, Servico..." required>
                        @error('descricao')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="valor" class="form-label fw-semibold">Valor <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="valor" id="valor" step="0.01" min="0.01"
                                    class="form-control @error('valor') is-invalid @enderror"
                                    value="{{ old('valor') }}" placeholder="0,00" required>
                            </div>
                            @error('valor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="vencimento" class="form-label fw-semibold">Vencimento <span class="text-danger">*</span></label>
                            <input type="date" name="vencimento" id="vencimento"
                                class="form-control form-control-lg @error('vencimento') is-invalid @enderror"
                                value="{{ old('vencimento') }}" required>
                            @error('vencimento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="parcelas" class="form-label fw-semibold">Parcelas</label>
                            <div class="input-group input-group-lg">
                                <input type="number" name="parcelas" id="parcelas" min="1" max="48"
                                    class="form-control @error('parcelas') is-invalid @enderror"
                                    value="{{ old('parcelas', 1) }}">
                                <span class="input-group-text">x</span>
                            </div>
                            @error('parcelas')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="categoria" class="form-label fw-semibold">Categoria</label>
                            <select name="categoria" id="categoria" class="form-select @error('categoria') is-invalid @enderror">
                                <option value="">Selecione...</option>
                                @php
                                    $categorias = ['Aluguel', 'Agua', 'Energia', 'Internet', 'Telefone', 'Fornecedor', 'Imposto', 'Folha de Pagamento', 'Marketing', 'Manutencao', 'Frete', 'Outro'];
                                @endphp
                                @foreach($categorias as $cat)
                                    <option value="{{ $cat }}" {{ old('categoria') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                            </select>
                            @error('categoria')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="centro_custo" class="form-label fw-semibold">Centro de Custo</label>
                            <input type="text" name="centro_custo" id="centro_custo"
                                class="form-control @error('centro_custo') is-invalid @enderror"
                                value="{{ old('centro_custo') }}" placeholder="Ex: Administrativo">
                            @error('centro_custo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="forma_pagamento" class="form-label fw-semibold">Forma de Pagamento</label>
                            <select name="forma_pagamento" id="forma_pagamento" class="form-select">
                                <option value="">Selecione...</option>
                                <option value="boleto" {{ old('forma_pagamento') == 'boleto' ? 'selected' : '' }}>Boleto</option>
                                <option value="pix" {{ old('forma_pagamento') == 'pix' ? 'selected' : '' }}>PIX</option>
                                <option value="transferencia" {{ old('forma_pagamento') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                                <option value="debito_automatico" {{ old('forma_pagamento') == 'debito_automatico' ? 'selected' : '' }}>Debito Automatico</option>
                                <option value="dinheiro" {{ old('forma_pagamento') == 'dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                            </select>
                        </div>
                    </div>

                    {{-- Recorrente --}}
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
                                <label for="recorrencia_tipo" class="form-label fw-semibold small">Tipo de Recorrencia</label>
                                <select name="recorrencia_tipo" id="recorrencia_tipo" class="form-select">
                                    <option value="mensal" {{ old('recorrencia_tipo') == 'mensal' ? 'selected' : '' }}>Mensal</option>
                                    <option value="bimestral" {{ old('recorrencia_tipo') == 'bimestral' ? 'selected' : '' }}>Bimestral</option>
                                    <option value="trimestral" {{ old('recorrencia_tipo') == 'trimestral' ? 'selected' : '' }}>Trimestral</option>
                                    <option value="semestral" {{ old('recorrencia_tipo') == 'semestral' ? 'selected' : '' }}>Semestral</option>
                                    <option value="anual" {{ old('recorrencia_tipo') == 'anual' ? 'selected' : '' }}>Anual</option>
                                </select>
                                <div class="alert alert-info border-0 bg-info bg-opacity-10 mt-2 mb-0 small">
                                    <i class="bi bi-info-circle me-1"></i> Serao geradas automaticamente 12 parcelas com o intervalo selecionado.
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Parcelas Preview --}}
                    <div id="parcelas-preview" class="card border-0 bg-light mb-4 d-none">
                        <div class="card-header bg-primary bg-opacity-10 border-0">
                            <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-list-ol me-1"></i> Previa das Parcelas</h6>
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
                        <label for="observacoes" class="form-label fw-semibold">Observacoes</label>
                        <textarea name="observacoes" id="observacoes" rows="3"
                            class="form-control @error('observacoes') is-invalid @enderror"
                            placeholder="Informacoes adicionais...">{{ old('observacoes') }}</textarea>
                        @error('observacoes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr class="my-4">

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-check-lg me-1"></i> Salvar Conta
                        </button>
                        <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Sidebar help --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-lightbulb me-1"></i> Dicas</h6>
                <div class="small text-muted">
                    <div class="mb-3">
                        <div class="fw-semibold text-dark mb-1">Parcelamento</div>
                        O valor sera dividido igualmente entre as parcelas. Cada parcela vence com intervalo de 1 mes.
                    </div>
                    <div class="mb-3">
                        <div class="fw-semibold text-dark mb-1">Conta Recorrente</div>
                        Ative para despesas fixas como aluguel, internet, etc. Serao geradas 12 parcelas automaticamente.
                    </div>
                    <div class="mb-0">
                        <div class="fw-semibold text-dark mb-1">Categorias</div>
                        Categorize suas despesas para facilitar a analise no fluxo de caixa e DRE.
                    </div>
                </div>
            </div>
        </div>
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
