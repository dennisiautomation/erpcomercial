@extends('layouts.app')

@section('title', 'Novo Pedido')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cart-plus me-2"></i>Novo Pedido</h4>
    <a href="{{ route('app.pedidos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.pedidos.store') }}" id="formPedido">
    @csrf

    <div class="row g-4">
        {{-- Cliente --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-person me-1"></i> Cliente</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Buscar Cliente</label>
                        <div class="input-group">
                            <input type="text" id="clienteBusca" class="form-control" placeholder="Digite o nome ou CPF/CNPJ..." autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" id="btnBuscarCliente">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div id="clienteResultados" class="list-group mt-1 position-absolute" style="z-index:1000; display:none;"></div>
                        <input type="hidden" name="cliente_id" id="clienteId" value="{{ old('cliente_id') }}" required>
                        <div id="clienteSelecionado" class="mt-2" style="display:none;">
                            <span class="badge bg-primary fs-6" id="clienteNome"></span>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="btnRemoverCliente">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                    @error('cliente_id')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-gear me-1"></i> Detalhes</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Vendedor</label>
                        <select name="vendedor_id" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($vendedores as $v)
                                <option value="{{ $v->id }}" {{ old('vendedor_id') == $v->id ? 'selected' : '' }}>
                                    {{ $v->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Condicao de Pagamento</label>
                        <select name="condicao_pagamento" class="form-select">
                            <option value="">Selecione...</option>
                            <option value="a_vista" {{ old('condicao_pagamento') === 'a_vista' ? 'selected' : '' }}>A Vista</option>
                            <option value="30_dias" {{ old('condicao_pagamento') === '30_dias' ? 'selected' : '' }}>30 Dias</option>
                            <option value="30_60_dias" {{ old('condicao_pagamento') === '30_60_dias' ? 'selected' : '' }}>30/60 Dias</option>
                            <option value="30_60_90_dias" {{ old('condicao_pagamento') === '30_60_90_dias' ? 'selected' : '' }}>30/60/90 Dias</option>
                            <option value="cartao" {{ old('condicao_pagamento') === 'cartao' ? 'selected' : '' }}>Cartao</option>
                            <option value="boleto" {{ old('condicao_pagamento') === 'boleto' ? 'selected' : '' }}>Boleto</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Itens --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul me-1"></i> Itens</span>
                    <button type="button" class="btn btn-sm btn-success" id="btnAddItem">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar Item
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="tabelaItens">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:35%">Produto</th>
                                    <th style="width:10%">Qtd</th>
                                    <th style="width:15%">Preco Unit.</th>
                                    <th style="width:10%">Desc. %</th>
                                    <th style="width:15%">Total</th>
                                    <th style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody id="itensBody"></tbody>
                        </table>
                    </div>
                </div>
                @error('itens')
                    <div class="card-footer text-danger small">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Totais e Observacoes --}}
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-chat-text me-1"></i> Observacoes</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Observacoes Internas</label>
                        <textarea name="observacoes_internas" class="form-control" rows="3">{{ old('observacoes_internas') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observacoes para o Cliente</label>
                        <textarea name="observacoes_externas" class="form-control" rows="3">{{ old('observacoes_externas') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-calculator me-1"></i> Totais</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <strong id="subtotalDisplay">R$ 0,00</strong>
                    </div>
                    <div class="row mb-2">
                        <div class="col-6">
                            <label class="form-label small">Desconto (%)</label>
                            <input type="number" name="desconto_percentual" id="descontoPerc" class="form-control form-control-sm" step="0.01" min="0" max="100" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Desconto (R$)</label>
                            <input type="number" name="desconto_valor" id="descontoValor" class="form-control form-control-sm" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fs-5">TOTAL:</span>
                        <strong class="fs-5 text-success" id="totalDisplay">R$ 0,00</strong>
                    </div>
                </div>
            </div>

            <div class="mt-3 text-end">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Salvar Pedido
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemIndex = 0;

    function addItemRow() {
        const row = document.createElement('tr');
        row.setAttribute('data-index', itemIndex);
        row.innerHTML = `
            <td>
                <input type="text" class="form-control form-control-sm produto-busca" placeholder="Buscar produto..." autocomplete="off">
                <div class="produto-resultados list-group mt-1 position-absolute" style="z-index:1000; display:none;"></div>
                <input type="hidden" name="itens[${itemIndex}][produto_id]" class="produto-id" required>
                <small class="produto-nome text-muted"></small>
            </td>
            <td><input type="number" name="itens[${itemIndex}][quantidade]" class="form-control form-control-sm item-qtd" step="0.001" min="0.001" value="1" required></td>
            <td><input type="number" name="itens[${itemIndex}][preco_unitario]" class="form-control form-control-sm item-preco" step="0.01" min="0" value="0" required></td>
            <td><input type="number" name="itens[${itemIndex}][desconto_percentual]" class="form-control form-control-sm item-desc-perc" step="0.01" min="0" max="100" value="0"></td>
            <td><span class="item-total fw-semibold">R$ 0,00</span></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-item"><i class="bi bi-x-lg"></i></button></td>
        `;
        document.getElementById('itensBody').appendChild(row);
        itemIndex++;
        bindRowEvents(row);
    }

    function bindRowEvents(row) {
        const buscaInput = row.querySelector('.produto-busca');
        const resultadosDiv = row.querySelector('.produto-resultados');
        const produtoIdInput = row.querySelector('.produto-id');
        const produtoNome = row.querySelector('.produto-nome');
        let searchTimeout;

        buscaInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const termo = this.value.trim();
            if (termo.length < 2) { resultadosDiv.style.display = 'none'; return; }
            searchTimeout = setTimeout(() => {
                fetch(`{{ url('app/pdv/buscar-produto') }}/${encodeURIComponent(termo)}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                })
                .then(r => r.json())
                .then(produtos => {
                    resultadosDiv.innerHTML = '';
                    produtos.forEach(p => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action small';
                        item.textContent = `${p.codigo_interno || ''} - ${p.descricao} - R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.', ',')}`;
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            produtoIdInput.value = p.id;
                            buscaInput.value = p.descricao;
                            produtoNome.textContent = `Cod: ${p.codigo_interno || '-'} | ${p.unidade_medida || 'UN'}`;
                            row.querySelector('.item-preco').value = parseFloat(p.preco_venda).toFixed(2);
                            resultadosDiv.style.display = 'none';
                            calcularTotais();
                        });
                        resultadosDiv.appendChild(item);
                    });
                    resultadosDiv.style.display = produtos.length ? 'block' : 'none';
                });
            }, 300);
        });

        row.querySelectorAll('.item-qtd, .item-preco, .item-desc-perc').forEach(input => {
            input.addEventListener('input', calcularTotais);
        });

        row.querySelector('.btn-remove-item').addEventListener('click', function() {
            row.remove();
            calcularTotais();
        });
    }

    function calcularTotais() {
        let subtotal = 0;
        document.querySelectorAll('#itensBody tr').forEach(row => {
            const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
            const preco = parseFloat(row.querySelector('.item-preco')?.value) || 0;
            const descPerc = parseFloat(row.querySelector('.item-desc-perc')?.value) || 0;
            const bruto = qtd * preco;
            const desconto = bruto * (descPerc / 100);
            const total = bruto - desconto;
            row.querySelector('.item-total').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
            subtotal += total;
        });

        document.getElementById('subtotalDisplay').textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');

        const descPerc = parseFloat(document.getElementById('descontoPerc').value) || 0;
        let descValor = parseFloat(document.getElementById('descontoValor').value) || 0;

        if (descPerc > 0) {
            descValor = subtotal * (descPerc / 100);
            document.getElementById('descontoValor').value = descValor.toFixed(2);
        }

        const total = subtotal - descValor;
        document.getElementById('totalDisplay').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    }

    document.getElementById('descontoPerc').addEventListener('input', calcularTotais);
    document.getElementById('descontoValor').addEventListener('input', function() {
        document.getElementById('descontoPerc').value = 0;
        calcularTotais();
    });

    document.getElementById('btnAddItem').addEventListener('click', () => addItemRow());

    // Cliente search
    const clienteBusca = document.getElementById('clienteBusca');
    const clienteResultados = document.getElementById('clienteResultados');
    let clienteTimeout;

    clienteBusca.addEventListener('input', function() {
        clearTimeout(clienteTimeout);
        const termo = this.value.trim();
        if (termo.length < 2) { clienteResultados.style.display = 'none'; return; }
        clienteTimeout = setTimeout(() => {
            fetch(`{{ url('app/clientes/buscar') }}?q=${encodeURIComponent(termo)}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            })
            .then(r => r.json())
            .then(clientes => {
                clienteResultados.innerHTML = '';
                clientes.forEach(c => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = `${c.nome_razao_social} - ${c.cpf_cnpj || ''}`;
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('clienteId').value = c.id;
                        document.getElementById('clienteNome').textContent = c.nome_razao_social;
                        document.getElementById('clienteSelecionado').style.display = 'block';
                        clienteBusca.style.display = 'none';
                        document.getElementById('btnBuscarCliente').style.display = 'none';
                        clienteResultados.style.display = 'none';
                    });
                    clienteResultados.appendChild(item);
                });
                clienteResultados.style.display = clientes.length ? 'block' : 'none';
            });
        }, 300);
    });

    document.getElementById('btnRemoverCliente').addEventListener('click', function() {
        document.getElementById('clienteId').value = '';
        document.getElementById('clienteSelecionado').style.display = 'none';
        clienteBusca.style.display = 'block';
        document.getElementById('btnBuscarCliente').style.display = 'block';
        clienteBusca.value = '';
    });

    addItemRow();

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.produto-busca')) document.querySelectorAll('.produto-resultados').forEach(d => d.style.display = 'none');
        if (!e.target.closest('#clienteBusca')) clienteResultados.style.display = 'none';
    });
});
</script>
@endpush
