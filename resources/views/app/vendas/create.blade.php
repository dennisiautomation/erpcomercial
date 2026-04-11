@extends('layouts.app')

@section('title', 'Nova Venda (Balcao)')

@section('content')
<x-erp.page-header title="Nova Venda (Balcao)" subtitle="Registre uma venda de balcao" icon="cart-plus">
    <a href="{{ route('app.vendas.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</x-erp.page-header>

<form method="POST" action="{{ route('app.vendas.store') }}" class="erp-form" id="formVendaBalcao">
    @csrf

    <x-erp.form-section title="Cliente e Vendedor" icon="people">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Cliente</label>
                <input type="hidden" name="cliente_id" id="cliente_id" value="{{ old('cliente_id') }}">
                <input type="text" id="cliente_search" class="form-control @error('cliente_id') is-invalid @enderror"
                       placeholder="Buscar por nome ou CPF/CNPJ..." value="{{ old('cliente_nome') }}"
                       data-autocomplete="{{ route('app.search.clientes') }}" autocomplete="off">
                <div id="cliente_results" class="list-group position-absolute w-100" style="z-index:1050;max-height:200px;overflow-y:auto;display:none;"></div>
                @error('cliente_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <small class="form-text text-muted">Opcional. Busque pelo nome ou documento.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">Vendedor</label>
                <select name="vendedor_id" class="form-select @error('vendedor_id') is-invalid @enderror">
                    <option value="">-- Selecione --</option>
                    @foreach($vendedores as $v)
                        <option value="{{ $v->id }}" {{ old('vendedor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                    @endforeach
                </select>
                @error('vendedor_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </x-erp.form-section>

    <x-erp.form-section title="Itens da Venda" icon="box-seam">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Adicionar Produto</label>
                <input type="text" id="produto_search" class="form-control" placeholder="Buscar produto por nome, codigo..." data-autocomplete="{{ route('app.search.produtos') }}" autocomplete="off">
                <div id="produto_results" class="list-group position-absolute w-100" style="z-index:1050;max-height:200px;overflow-y:auto;display:none;"></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="erp-table" id="tabelaItens">
                <thead>
                    <tr>
                        <th style="width:40%">Produto</th>
                        <th style="width:12%">Qtd</th>
                        <th style="width:15%">Preco Unit.</th>
                        <th style="width:13%">Desconto</th>
                        <th style="width:15%">Total</th>
                        <th style="width:5%"></th>
                    </tr>
                </thead>
                <tbody id="itensBody">
                    {{-- Dynamic rows via JS --}}
                </tbody>
            </table>
        </div>

        <div id="emptyItens" class="text-center text-muted py-4">
            <i class="bi bi-cart fs-1 d-block mb-2"></i>
            Nenhum produto adicionado. Busque um produto acima.
        </div>
    </x-erp.form-section>

    <x-erp.form-section title="Pagamento" icon="credit-card">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label required">Forma de Pagamento <span class="text-danger">*</span></label>
                <select name="forma_pagamento" class="form-select @error('forma_pagamento') is-invalid @enderror" required>
                    <option value="">Selecione</option>
                    <option value="dinheiro" {{ old('forma_pagamento') === 'dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                    <option value="cartao_credito" {{ old('forma_pagamento') === 'cartao_credito' ? 'selected' : '' }}>Cartao de Credito</option>
                    <option value="cartao_debito" {{ old('forma_pagamento') === 'cartao_debito' ? 'selected' : '' }}>Cartao de Debito</option>
                    <option value="pix" {{ old('forma_pagamento') === 'pix' ? 'selected' : '' }}>PIX</option>
                    <option value="boleto" {{ old('forma_pagamento') === 'boleto' ? 'selected' : '' }}>Boleto</option>
                </select>
                @error('forma_pagamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Desconto (R$)</label>
                <div class="input-group">
                    <span class="input-group-text">R$</span>
                    <input type="number" name="desconto_valor" id="descontoGeral" class="form-control @error('desconto_valor') is-invalid @enderror" value="{{ old('desconto_valor', '0.00') }}" step="0.01" min="0">
                </div>
                @error('desconto_valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Observacoes</label>
                <input type="text" name="observacoes" class="form-control @error('observacoes') is-invalid @enderror" value="{{ old('observacoes') }}" placeholder="Observacoes opcionais">
                @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </x-erp.form-section>

    {{-- Totais --}}
    <x-erp.card title="Resumo" icon="calculator">
        <div class="row text-center">
            <div class="col-md-4">
                <small class="text-muted d-block">Subtotal</small>
                <span class="fs-4 fw-semibold" id="displaySubtotal">R$ 0,00</span>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Desconto</small>
                <span class="fs-4 fw-semibold text-danger" id="displayDesconto">- R$ 0,00</span>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Total</small>
                <span class="fs-3 fw-bold text-success" id="displayTotal">R$ 0,00</span>
            </div>
        </div>
    </x-erp.card>

    <div class="d-flex gap-2 mt-3 mb-4">
        <button type="submit" class="btn btn-erp-primary btn-lg" id="btnFinalizar" disabled>
            <i class="bi bi-check-circle me-1"></i> Finalizar Venda
        </button>
        <a href="{{ route('app.vendas.index') }}" class="btn btn-erp-outline">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let itens = [];
    let itemIndex = 0;

    const itensBody = document.getElementById('itensBody');
    const emptyItens = document.getElementById('emptyItens');
    const btnFinalizar = document.getElementById('btnFinalizar');
    const descontoInput = document.getElementById('descontoGeral');

    // --- Autocomplete helper ---
    function setupAutocomplete(inputId, resultsId, url, onSelect) {
        const input = document.getElementById(inputId);
        const results = document.getElementById(resultsId);
        let debounce;

        input.addEventListener('input', function () {
            clearTimeout(debounce);
            const q = this.value.trim();
            if (q.length < 2) { results.style.display = 'none'; return; }
            debounce = setTimeout(() => {
                fetch(url + '?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        results.innerHTML = '';
                        if (!data.length) { results.style.display = 'none'; return; }
                        data.forEach(item => {
                            const a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action';
                            a.textContent = item.label || item.nome_razao_social || item.descricao || item.name;
                            a.addEventListener('click', function (e) {
                                e.preventDefault();
                                onSelect(item);
                                results.style.display = 'none';
                            });
                            results.appendChild(a);
                        });
                        results.style.display = 'block';
                    });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    }

    // --- Cliente autocomplete ---
    setupAutocomplete('cliente_search', 'cliente_results', '{{ route("app.search.clientes") }}', function (item) {
        document.getElementById('cliente_id').value = item.id;
        document.getElementById('cliente_search').value = item.nome_razao_social + (item.cpf_cnpj ? ' - ' + item.cpf_cnpj : '');
    });

    // --- Produto autocomplete ---
    setupAutocomplete('produto_search', 'produto_results', '{{ route("app.search.produtos") }}', function (item) {
        addItem(item);
        document.getElementById('produto_search').value = '';
    });

    function addItem(produto) {
        const idx = itemIndex++;
        const item = {
            idx: idx,
            produto_id: produto.id,
            descricao: produto.descricao || produto.label,
            quantidade: 1,
            preco_unitario: parseFloat(produto.preco_venda || 0),
            desconto_valor: 0,
        };
        itens.push(item);
        renderItens();
    }

    function renderItens() {
        itensBody.innerHTML = '';
        emptyItens.style.display = itens.length ? 'none' : 'block';
        btnFinalizar.disabled = itens.length === 0;

        itens.forEach((item, i) => {
            const totalItem = Math.max(0, (item.preco_unitario * item.quantidade) - item.desconto_valor);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    ${item.descricao}
                    <input type="hidden" name="itens[${i}][produto_id]" value="${item.produto_id}">
                </td>
                <td>
                    <input type="number" name="itens[${i}][quantidade]" class="form-control form-control-sm" value="${item.quantidade}" min="0.001" step="0.001" data-idx="${i}" data-field="quantidade">
                </td>
                <td>
                    <input type="number" name="itens[${i}][preco_unitario]" class="form-control form-control-sm" value="${item.preco_unitario.toFixed(2)}" min="0" step="0.01" data-idx="${i}" data-field="preco_unitario">
                </td>
                <td>
                    <input type="number" name="itens[${i}][desconto_valor]" class="form-control form-control-sm" value="${item.desconto_valor.toFixed(2)}" min="0" step="0.01" data-idx="${i}" data-field="desconto_valor">
                </td>
                <td class="fw-bold text-end">R$ ${totalItem.toFixed(2).replace('.', ',')}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove="${i}" title="Remover">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            itensBody.appendChild(tr);
        });

        // Bind change events
        itensBody.querySelectorAll('input[data-field]').forEach(input => {
            input.addEventListener('change', function () {
                const idx = parseInt(this.dataset.idx);
                const field = this.dataset.field;
                itens[idx][field] = parseFloat(this.value) || 0;
                updateTotais();
                renderItens();
            });
        });

        // Bind remove buttons
        itensBody.querySelectorAll('[data-remove]').forEach(btn => {
            btn.addEventListener('click', function () {
                const idx = parseInt(this.dataset.remove);
                itens.splice(idx, 1);
                renderItens();
            });
        });

        updateTotais();
    }

    function updateTotais() {
        let subtotal = 0;
        itens.forEach(item => {
            subtotal += Math.max(0, (item.preco_unitario * item.quantidade) - item.desconto_valor);
        });
        const desconto = parseFloat(descontoInput.value) || 0;
        const total = Math.max(0, subtotal - desconto);

        document.getElementById('displaySubtotal').textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
        document.getElementById('displayDesconto').textContent = '- R$ ' + desconto.toFixed(2).replace('.', ',');
        document.getElementById('displayTotal').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    }

    descontoInput.addEventListener('input', updateTotais);

    // Restore old items if validation failed
    @if(old('itens'))
        @foreach(old('itens') as $i => $oldItem)
            itens.push({
                idx: itemIndex++,
                produto_id: {{ $oldItem['produto_id'] ?? 0 }},
                descricao: 'Produto #{{ $oldItem['produto_id'] ?? '' }}',
                quantidade: {{ $oldItem['quantidade'] ?? 1 }},
                preco_unitario: {{ $oldItem['preco_unitario'] ?? 0 }},
                desconto_valor: {{ $oldItem['desconto_valor'] ?? 0 }},
            });
        @endforeach
        renderItens();
    @endif
});
</script>
@endpush
