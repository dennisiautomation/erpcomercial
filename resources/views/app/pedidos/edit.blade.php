@extends('layouts.app')

@section('title', 'Editar Pedido #' . $pedido->numero)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Pedido #{{ $pedido->numero }}</h4>
    <a href="{{ route('app.pedidos.show', $pedido) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.pedidos.update', $pedido) }}" id="formPedido">
    @csrf
    @method('PUT')

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-person me-1"></i> Cliente</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Buscar Cliente</label>
                        <div class="input-group" id="clienteBuscaGroup" style="{{ $pedido->cliente ? 'display:none' : '' }}">
                            <input type="text" id="clienteBusca" class="form-control" placeholder="Digite o nome ou CPF/CNPJ..." autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" id="btnBuscarCliente"><i class="bi bi-search"></i></button>
                        </div>
                        <div id="clienteResultados" class="list-group mt-1 position-absolute" style="z-index:1000; display:none;"></div>
                        <input type="hidden" name="cliente_id" id="clienteId" value="{{ $pedido->cliente_id }}" required>
                        <div id="clienteSelecionado" class="mt-2" style="{{ $pedido->cliente ? '' : 'display:none' }}">
                            <span class="badge bg-primary fs-6" id="clienteNome">{{ $pedido->cliente->nome_razao_social ?? '' }}</span>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="btnRemoverCliente"><i class="bi bi-x"></i></button>
                        </div>
                    </div>
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
                                <option value="{{ $v->id }}" {{ $pedido->vendedor_id == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Condicao de Pagamento</label>
                        <select name="condicao_pagamento" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach(['a_vista' => 'A Vista', '30_dias' => '30 Dias', '30_60_dias' => '30/60 Dias', '30_60_90_dias' => '30/60/90 Dias', 'cartao' => 'Cartao', 'boleto' => 'Boleto'] as $val => $label)
                                <option value="{{ $val }}" {{ $pedido->condicao_pagamento === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul me-1"></i> Itens</span>
                    <button type="button" class="btn btn-sm btn-success" id="btnAddItem"><i class="bi bi-plus-lg me-1"></i> Adicionar Item</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
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
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-chat-text me-1"></i> Observacoes</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Observacoes Internas</label>
                        <textarea name="observacoes_internas" class="form-control" rows="3">{{ $pedido->observacoes_internas }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observacoes para o Cliente</label>
                        <textarea name="observacoes_externas" class="form-control" rows="3">{{ $pedido->observacoes_externas }}</textarea>
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
                            <input type="number" name="desconto_percentual" id="descontoPerc" class="form-control form-control-sm" step="0.01" min="0" max="100" value="{{ $pedido->desconto_percentual ?? 0 }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Desconto (R$)</label>
                            <input type="number" name="desconto_valor" id="descontoValor" class="form-control form-control-sm" step="0.01" min="0" value="{{ $pedido->desconto_valor ?? 0 }}">
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
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i> Atualizar Pedido</button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemIndex = 0;
    const itensExistentes = @json($pedido->itens->map(fn($i) => [
        'produto_id' => $i->produto_id,
        'descricao' => $i->descricao ?? $i->produto?->descricao,
        'quantidade' => $i->quantidade,
        'preco_unitario' => $i->preco_unitario,
        'desconto_percentual' => $i->desconto_percentual,
    ]));

    function addItemRow(data = null) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <input type="text" class="form-control form-control-sm produto-busca" placeholder="Buscar produto..." autocomplete="off" value="${data ? data.descricao : ''}">
                <div class="produto-resultados list-group mt-1 position-absolute" style="z-index:1000; display:none;"></div>
                <input type="hidden" name="itens[${itemIndex}][produto_id]" class="produto-id" value="${data ? data.produto_id : ''}" required>
            </td>
            <td><input type="number" name="itens[${itemIndex}][quantidade]" class="form-control form-control-sm item-qtd" step="0.001" min="0.001" value="${data ? data.quantidade : 1}" required></td>
            <td><input type="number" name="itens[${itemIndex}][preco_unitario]" class="form-control form-control-sm item-preco" step="0.01" min="0" value="${data ? parseFloat(data.preco_unitario).toFixed(2) : '0'}" required></td>
            <td><input type="number" name="itens[${itemIndex}][desconto_percentual]" class="form-control form-control-sm item-desc-perc" step="0.01" min="0" max="100" value="${data ? data.desconto_percentual : 0}"></td>
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

        row.querySelectorAll('.item-qtd, .item-preco, .item-desc-perc').forEach(input => input.addEventListener('input', calcularTotais));
        row.querySelector('.btn-remove-item').addEventListener('click', function() { row.remove(); calcularTotais(); });
    }

    function calcularTotais() {
        let subtotal = 0;
        document.querySelectorAll('#itensBody tr').forEach(row => {
            const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
            const preco = parseFloat(row.querySelector('.item-preco')?.value) || 0;
            const descPerc = parseFloat(row.querySelector('.item-desc-perc')?.value) || 0;
            const total = (qtd * preco) - (qtd * preco * descPerc / 100);
            row.querySelector('.item-total').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
            subtotal += total;
        });
        document.getElementById('subtotalDisplay').textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
        const descPerc = parseFloat(document.getElementById('descontoPerc').value) || 0;
        let descValor = parseFloat(document.getElementById('descontoValor').value) || 0;
        if (descPerc > 0) { descValor = subtotal * (descPerc / 100); document.getElementById('descontoValor').value = descValor.toFixed(2); }
        document.getElementById('totalDisplay').textContent = 'R$ ' + (subtotal - descValor).toFixed(2).replace('.', ',');
    }

    document.getElementById('descontoPerc').addEventListener('input', calcularTotais);
    document.getElementById('descontoValor').addEventListener('input', function() { document.getElementById('descontoPerc').value = 0; calcularTotais(); });
    document.getElementById('btnAddItem').addEventListener('click', () => addItemRow());

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
                        document.getElementById('clienteBuscaGroup').style.display = 'none';
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
        document.getElementById('clienteBuscaGroup').style.display = 'flex';
        clienteBusca.value = '';
    });

    itensExistentes.forEach(item => addItemRow(item));
    if (itensExistentes.length === 0) addItemRow();
    calcularTotais();

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.produto-busca')) document.querySelectorAll('.produto-resultados').forEach(d => d.style.display = 'none');
        if (!e.target.closest('#clienteBusca')) clienteResultados.style.display = 'none';
    });
});
</script>
@endpush
