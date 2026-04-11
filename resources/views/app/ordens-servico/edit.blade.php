@extends('layouts.app')

@section('title', 'Editar OS #' . $ordemServico->numero)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-wrench-adjustable me-2"></i>Editar OS #{{ $ordemServico->numero }}</h4>
    <a href="{{ route('app.ordens-servico.show', $ordemServico) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.ordens-servico.update', $ordemServico) }}" id="formOS">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- Left Column --}}
        <div class="col-lg-8">
            {{-- Cliente & Equipamento --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-person me-1"></i> Cliente e Equipamento
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select name="cliente_id" class="form-select" required>
                                <option value="">Selecione o cliente...</option>
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->id }}" {{ old('cliente_id', $ordemServico->cliente_id) == $cliente->id ? 'selected' : '' }}>
                                        {{ $cliente->nome_razao_social }} - {{ $cliente->cpf_cnpj }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Equipamento <span class="text-danger">*</span></label>
                            <input type="text" name="equipamento" class="form-control" required
                                   value="{{ old('equipamento', $ordemServico->equipamento) }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Defeito Relatado <span class="text-danger">*</span></label>
                            <textarea name="defeito_relatado" class="form-control" rows="3" required>{{ old('defeito_relatado', $ordemServico->defeito_relatado) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-1"></i> Itens (Produtos e Servicos)</span>
                    <button type="button" class="btn btn-sm btn-success" onclick="adicionarItem()">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar Item
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="tabelaItens">
                            <thead class="table-light">
                                <tr>
                                    <th width="120">Tipo</th>
                                    <th>Item</th>
                                    <th width="100">Qtd</th>
                                    <th width="130">Preco Unit.</th>
                                    <th width="130">Total</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="itensBody">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="row">
                        <div class="col-md-4 offset-md-8 text-end">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Produtos:</span>
                                <strong id="totalProdutos">R$ 0,00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Servicos:</span>
                                <strong id="totalServicos">R$ 0,00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Desconto:</span>
                                <input type="number" name="desconto" class="form-control form-control-sm d-inline-block"
                                       style="width: 120px;" step="0.01" min="0" value="{{ old('desconto', $ordemServico->desconto) }}" onchange="calcularTotais()">
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total:</strong>
                                <strong class="text-success fs-5" id="totalGeral">R$ 0,00</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column --}}
        <div class="col-lg-4">
            {{-- Responsaveis --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-people me-1"></i> Responsaveis
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Vendedor</label>
                        <select name="vendedor_id" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($vendedores as $vendedor)
                                <option value="{{ $vendedor->id }}" {{ old('vendedor_id', $ordemServico->vendedor_id) == $vendedor->id ? 'selected' : '' }}>
                                    {{ $vendedor->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tecnico</label>
                        <select name="tecnico_id" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($tecnicos as $tecnico)
                                <option value="{{ $tecnico->id }}" {{ old('tecnico_id', $ordemServico->tecnico_id) == $tecnico->id ? 'selected' : '' }}>
                                    {{ $tecnico->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Observacoes --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-chat-text me-1"></i> Observacoes
                </div>
                <div class="card-body">
                    <textarea name="observacoes" class="form-control" rows="4">{{ old('observacoes', $ordemServico->observacoes) }}</textarea>
                </div>
            </div>

            {{-- Submit --}}
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Salvar Alteracoes
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const produtos = @json($produtos);
    const servicos = @json($servicos);
    const itensExistentes = @json($ordemServico->itens);
    let itemIndex = 0;

    function adicionarItem(dados = null) {
        const tbody = document.getElementById('itensBody');
        const tr = document.createElement('tr');
        tr.id = `item-${itemIndex}`;

        const tipo = dados ? dados.tipo : 'produto';
        const produtoId = dados ? (dados.produto_id || '') : '';
        const servicoId = dados ? (dados.servico_id || '') : '';
        const descricao = dados ? (dados.descricao || '') : '';
        const quantidade = dados ? dados.quantidade : 1;
        const precoUnitario = dados ? parseFloat(dados.preco_unitario).toFixed(2) : '0.00';
        const total = dados ? parseFloat(dados.total).toFixed(2) : '0.00';

        let selectHtml = '';
        if (tipo === 'produto') {
            selectHtml = `
                <select name="itens[${itemIndex}][produto_id]" class="form-select form-select-sm item-select" data-index="${itemIndex}" onchange="selecionarItem(${itemIndex})">
                    <option value="">Selecione...</option>
                    ${produtos.map(p => `<option value="${p.id}" data-preco="${p.preco_venda}" data-nome="${p.nome}" ${p.id == produtoId ? 'selected' : ''}>${p.nome}</option>`).join('')}
                </select>
                <input type="hidden" name="itens[${itemIndex}][servico_id]" value="">
                <input type="hidden" name="itens[${itemIndex}][descricao]" value="${descricao}" class="item-descricao">
            `;
        } else {
            selectHtml = `
                <select name="itens[${itemIndex}][servico_id]" class="form-select form-select-sm item-select" data-index="${itemIndex}" onchange="selecionarItem(${itemIndex})">
                    <option value="">Selecione...</option>
                    ${servicos.map(s => `<option value="${s.id}" data-preco="${s.preco}" data-nome="${s.nome}" ${s.id == servicoId ? 'selected' : ''}>${s.nome}</option>`).join('')}
                </select>
                <input type="hidden" name="itens[${itemIndex}][produto_id]" value="">
                <input type="hidden" name="itens[${itemIndex}][descricao]" value="${descricao}" class="item-descricao">
            `;
        }

        tr.innerHTML = `
            <td>
                <select name="itens[${itemIndex}][tipo]" class="form-select form-select-sm" onchange="trocarTipo(${itemIndex})">
                    <option value="produto" ${tipo === 'produto' ? 'selected' : ''}>Produto</option>
                    <option value="servico" ${tipo === 'servico' ? 'selected' : ''}>Servico</option>
                </select>
            </td>
            <td>${selectHtml}</td>
            <td>
                <input type="number" name="itens[${itemIndex}][quantidade]" class="form-control form-control-sm item-qtd"
                       step="0.001" min="0.001" value="${quantidade}" onchange="calcularLinhaTotal(${itemIndex})">
            </td>
            <td>
                <input type="number" name="itens[${itemIndex}][preco_unitario]" class="form-control form-control-sm item-preco"
                       step="0.01" min="0" value="${precoUnitario}" onchange="calcularLinhaTotal(${itemIndex})">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm item-total" readonly value="R$ ${parseFloat(total).toFixed(2).replace('.', ',')}">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerItem(${itemIndex})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        itemIndex++;
    }

    function trocarTipo(index) {
        const row = document.getElementById(`item-${index}`);
        const tipo = row.querySelector(`[name="itens[${index}][tipo]"]`).value;
        const selectTd = row.querySelectorAll('td')[1];

        let options = '';
        if (tipo === 'produto') {
            options = produtos.map(p => `<option value="${p.id}" data-preco="${p.preco_venda}" data-nome="${p.nome}">${p.nome}</option>`).join('');
            selectTd.innerHTML = `
                <select name="itens[${index}][produto_id]" class="form-select form-select-sm item-select" data-index="${index}" onchange="selecionarItem(${index})">
                    <option value="">Selecione...</option>
                    ${options}
                </select>
                <input type="hidden" name="itens[${index}][servico_id]" value="">
                <input type="hidden" name="itens[${index}][descricao]" value="" class="item-descricao">
            `;
        } else {
            options = servicos.map(s => `<option value="${s.id}" data-preco="${s.preco}" data-nome="${s.nome}">${s.nome}</option>`).join('');
            selectTd.innerHTML = `
                <select name="itens[${index}][servico_id]" class="form-select form-select-sm item-select" data-index="${index}" onchange="selecionarItem(${index})">
                    <option value="">Selecione...</option>
                    ${options}
                </select>
                <input type="hidden" name="itens[${index}][produto_id]" value="">
                <input type="hidden" name="itens[${index}][descricao]" value="" class="item-descricao">
            `;
        }
    }

    function selecionarItem(index) {
        const row = document.getElementById(`item-${index}`);
        const select = row.querySelector('.item-select');
        const option = select.options[select.selectedIndex];
        const preco = option.getAttribute('data-preco') || 0;
        const nome = option.getAttribute('data-nome') || '';

        row.querySelector('.item-preco').value = parseFloat(preco).toFixed(2);
        row.querySelector('.item-descricao').value = nome;
        calcularLinhaTotal(index);
    }

    function calcularLinhaTotal(index) {
        const row = document.getElementById(`item-${index}`);
        const qtd = parseFloat(row.querySelector('.item-qtd').value) || 0;
        const preco = parseFloat(row.querySelector('.item-preco').value) || 0;
        const total = qtd * preco;
        row.querySelector('.item-total').value = 'R$ ' + total.toFixed(2).replace('.', ',');
        const descInput = row.querySelector('.item-descricao');
        if (!descInput.value) {
            const select = row.querySelector('.item-select');
            descInput.value = select.options[select.selectedIndex]?.getAttribute('data-nome') || '';
        }
        calcularTotais();
    }

    function removerItem(index) {
        document.getElementById(`item-${index}`).remove();
        calcularTotais();
    }

    function calcularTotais() {
        let totalProdutos = 0;
        let totalServicos = 0;

        document.querySelectorAll('#itensBody tr').forEach(row => {
            const tipo = row.querySelector('[name*="[tipo]"]').value;
            const qtd = parseFloat(row.querySelector('.item-qtd').value) || 0;
            const preco = parseFloat(row.querySelector('.item-preco').value) || 0;
            const total = qtd * preco;

            if (tipo === 'produto') totalProdutos += total;
            else totalServicos += total;
        });

        const desconto = parseFloat(document.querySelector('[name="desconto"]').value) || 0;
        const totalGeral = totalProdutos + totalServicos - desconto;

        document.getElementById('totalProdutos').textContent = 'R$ ' + totalProdutos.toFixed(2).replace('.', ',');
        document.getElementById('totalServicos').textContent = 'R$ ' + totalServicos.toFixed(2).replace('.', ',');
        document.getElementById('totalGeral').textContent = 'R$ ' + totalGeral.toFixed(2).replace('.', ',');
    }

    // Load existing items on page load
    document.addEventListener('DOMContentLoaded', function() {
        itensExistentes.forEach(item => adicionarItem(item));
        calcularTotais();
    });
</script>
@endpush
