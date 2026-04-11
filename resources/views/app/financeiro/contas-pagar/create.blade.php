@extends('layouts.app')
@php $errors = $errors ?? new \Illuminate\Support\ViewErrorBag(); @endphp

@section('title', 'Nova Conta a Pagar')

@section('content')
<x-erp.page-header title="Nova Conta a Pagar" subtitle="Cadastre uma nova despesa ou conta parcelada" icon="plus-circle">
    <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('app.contas-pagar.store') }}">
            @csrf

            <x-erp.form-section title="Fornecedor e Descricao" icon="building">
                <div class="mb-4">
                    <label for="fornecedor_id" class="form-label fw-semibold">Fornecedor <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <div class="input-group input-group-lg" id="fornecedorBuscaGroup">
                            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                            <input type="text" id="fornecedorBusca" class="form-control @error('fornecedor_id') is-invalid @enderror"
                                   placeholder="Buscar fornecedor por nome ou CPF/CNPJ..." autocomplete="off">
                        </div>
                        <div id="fornecedorResultados" class="list-group mt-1 position-absolute w-100 shadow-lg"
                             style="z-index:1050; display:none; max-height:300px; overflow-y:auto;"></div>
                        <input type="hidden" name="fornecedor_id" id="fornecedorId" value="{{ old('fornecedor_id') }}" required>
                        <div id="fornecedorSelecionado" class="mt-2" style="display:none;">
                            <div class="d-flex align-items-center bg-primary bg-opacity-10 rounded-3 p-2 ps-3">
                                <i class="bi bi-building text-primary me-2 fs-5"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold" id="fornecedorNome"></div>
                                    <small class="text-muted" id="fornecedorDoc"></small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle ms-2" id="btnRemoverFornecedor" title="Remover fornecedor">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    @error('fornecedor_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
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
            </x-erp.form-section>

            <x-erp.form-section title="Valores e Vencimento" icon="currency-dollar">
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
            </x-erp.form-section>

            <x-erp.form-section title="Recorrencia" icon="arrow-repeat">
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
        </x-erp.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ===== FORNECEDOR SEARCH =====
    const fornecedorBuscarUrl = '{{ route("app.search.fornecedores") }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const fornecedorBusca = document.getElementById('fornecedorBusca');
    const fornecedorResultados = document.getElementById('fornecedorResultados');
    let fornecedorTimeout;

    fornecedorBusca.addEventListener('input', function() {
        clearTimeout(fornecedorTimeout);
        const termo = this.value.trim();
        if (termo.length < 2) { fornecedorResultados.style.display = 'none'; return; }
        fornecedorTimeout = setTimeout(() => {
            fetch(`${fornecedorBuscarUrl}?q=${encodeURIComponent(termo)}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(fornecedores => {
                fornecedorResultados.innerHTML = '';
                if (fornecedores.length === 0) {
                    fornecedorResultados.innerHTML = '<div class="list-group-item text-muted small py-2">Nenhum fornecedor encontrado</div>';
                    fornecedorResultados.style.display = 'block';
                    return;
                }
                fornecedores.forEach(f => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action py-2';
                    item.innerHTML = `
                        <div class="fw-semibold">${f.razao_social}</div>
                        <small class="text-muted">${f.cpf_cnpj || 'Sem documento'}</small>
                    `;
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('fornecedorId').value = f.id;
                        document.getElementById('fornecedorNome').textContent = f.razao_social;
                        document.getElementById('fornecedorDoc').textContent = f.cpf_cnpj || '';
                        document.getElementById('fornecedorSelecionado').style.display = 'block';
                        document.getElementById('fornecedorBuscaGroup').style.display = 'none';
                        fornecedorResultados.style.display = 'none';
                    });
                    fornecedorResultados.appendChild(item);
                });
                fornecedorResultados.style.display = 'block';
            });
        }, 300);
    });

    fornecedorBusca.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') fornecedorResultados.style.display = 'none';
    });

    document.getElementById('btnRemoverFornecedor').addEventListener('click', function() {
        document.getElementById('fornecedorId').value = '';
        document.getElementById('fornecedorSelecionado').style.display = 'none';
        document.getElementById('fornecedorBuscaGroup').style.display = 'flex';
        fornecedorBusca.value = '';
        fornecedorBusca.focus();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#fornecedorBusca') && !e.target.closest('#fornecedorResultados')) {
            fornecedorResultados.style.display = 'none';
        }
    });

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
