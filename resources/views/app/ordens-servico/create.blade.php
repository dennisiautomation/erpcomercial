@extends('layouts.app')

@section('title', 'Nova Ordem de Servico')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-wrench-adjustable me-2"></i>Nova Ordem de Servico</h4>
    <a href="{{ route('app.ordens-servico.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.ordens-servico.store') }}" id="formOS">
    @csrf

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
                            <label class="form-label fw-semibold">Cliente <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <div class="input-group" id="clienteBuscaGroup">
                                    <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                                    <input type="text" id="clienteBusca" class="form-control @error('cliente_id') is-invalid @enderror"
                                           placeholder="Buscar cliente por nome ou CPF/CNPJ..." autocomplete="off">
                                </div>
                                <div id="clienteResultados" class="list-group mt-1 position-absolute w-100 shadow-lg"
                                     style="z-index:1050; display:none; max-height:300px; overflow-y:auto;"></div>
                                <input type="hidden" name="cliente_id" id="clienteId" value="{{ old('cliente_id') }}" required>
                                <div id="clienteSelecionado" class="mt-2" style="display:none;">
                                    <div class="d-flex align-items-center bg-primary bg-opacity-10 rounded-3 p-2 ps-3">
                                        <i class="bi bi-person-check text-primary me-2 fs-5"></i>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold" id="clienteNome"></div>
                                            <small class="text-muted" id="clienteDoc"></small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle ms-2" id="btnRemoverCliente" title="Remover cliente">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @error('cliente_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Equipamento <span class="text-danger">*</span></label>
                            <input type="text" name="equipamento" class="form-control @error('equipamento') is-invalid @enderror" required
                                   value="{{ old('equipamento') }}" placeholder="Ex: Notebook Dell Inspiron 15, Impressora HP LaserJet...">
                            @error('equipamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Defeito Relatado <span class="text-danger">*</span></label>
                            <textarea name="defeito_relatado" class="form-control @error('defeito_relatado') is-invalid @enderror" rows="3" required
                                      placeholder="Descreva o defeito relatado pelo cliente...">{{ old('defeito_relatado') }}</textarea>
                            @error('defeito_relatado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-1"></i> Itens (Produtos e Servicos)</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="adicionarItem('produto')">
                            <i class="bi bi-box me-1"></i> Produto
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="adicionarItem('servico')">
                            <i class="bi bi-tools me-1"></i> Servico
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="tabelaItens">
                            <thead class="table-light">
                                <tr>
                                    <th width="100">Tipo</th>
                                    <th>Item</th>
                                    <th width="90">Qtd</th>
                                    <th width="130">Preco Unit.</th>
                                    <th width="130">Total</th>
                                    <th width="45"></th>
                                </tr>
                            </thead>
                            <tbody id="itensBody">
                                {{-- Dynamic rows --}}
                            </tbody>
                        </table>
                    </div>
                    <div id="emptyState" class="text-center text-muted py-4">
                        <i class="bi bi-plus-circle d-block fs-3 mb-1 opacity-50"></i>
                        <small>Clique nos botoes acima para adicionar itens</small>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="row">
                        <div class="col-md-5 offset-md-7">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Produtos:</span>
                                <strong id="totalProdutos">R$ 0,00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Servicos:</span>
                                <strong id="totalServicos">R$ 0,00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2 align-items-center">
                                <span class="text-muted">Desconto (R$):</span>
                                <input type="number" name="desconto" class="form-control form-control-sm text-end"
                                       style="width: 130px;" step="0.01" min="0" value="{{ old('desconto', '0') }}" onchange="calcularTotais()">
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <strong class="fs-5">Total:</strong>
                                <strong class="fs-5 text-success" id="totalGeral">R$ 0,00</strong>
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
                        <label class="form-label fw-semibold">Vendedor</label>
                        <select name="vendedor_id" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($vendedores as $vendedor)
                                <option value="{{ $vendedor->id }}" {{ old('vendedor_id') == $vendedor->id ? 'selected' : '' }}>
                                    {{ $vendedor->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Tecnico</label>
                        <select name="tecnico_id" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($tecnicos as $tecnico)
                                <option value="{{ $tecnico->id }}" {{ old('tecnico_id') == $tecnico->id ? 'selected' : '' }}>
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
                    <textarea name="observacoes" class="form-control" rows="4"
                              placeholder="Observacoes internas...">{{ old('observacoes') }}</textarea>
                </div>
            </div>

            {{-- Submit --}}
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Criar Ordem de Servico
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    // ===== CLIENTE SEARCH =====
    const clientesBuscarUrl = '{{ route("app.search.clientes") }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const clienteBusca = document.getElementById('clienteBusca');
    const clienteResultados = document.getElementById('clienteResultados');
    let clienteTimeout;

    clienteBusca.addEventListener('input', function() {
        clearTimeout(clienteTimeout);
        const termo = this.value.trim();
        if (termo.length < 2) { clienteResultados.style.display = 'none'; return; }
        clienteTimeout = setTimeout(() => {
            fetch(`${clientesBuscarUrl}?q=${encodeURIComponent(termo)}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(clientes => {
                clienteResultados.innerHTML = '';
                if (clientes.length === 0) {
                    clienteResultados.innerHTML = '<div class="list-group-item text-muted small py-2">Nenhum cliente encontrado</div>';
                    clienteResultados.style.display = 'block';
                    return;
                }
                clientes.forEach(c => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action py-2';
                    item.innerHTML = `
                        <div class="fw-semibold">${c.nome_razao_social}</div>
                        <small class="text-muted">${c.cpf_cnpj || 'Sem documento'}</small>
                    `;
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('clienteId').value = c.id;
                        document.getElementById('clienteNome').textContent = c.nome_razao_social;
                        document.getElementById('clienteDoc').textContent = c.cpf_cnpj || '';
                        document.getElementById('clienteSelecionado').style.display = 'block';
                        document.getElementById('clienteBuscaGroup').style.display = 'none';
                        clienteResultados.style.display = 'none';
                    });
                    clienteResultados.appendChild(item);
                });
                clienteResultados.style.display = 'block';
            });
        }, 300);
    });

    clienteBusca.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') clienteResultados.style.display = 'none';
    });

    document.getElementById('btnRemoverCliente').addEventListener('click', function() {
        document.getElementById('clienteId').value = '';
        document.getElementById('clienteSelecionado').style.display = 'none';
        document.getElementById('clienteBuscaGroup').style.display = 'flex';
        clienteBusca.value = '';
        clienteBusca.focus();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#clienteBusca') && !e.target.closest('#clienteResultados')) {
            clienteResultados.style.display = 'none';
        }
    });

    const produtos = @json($produtos);
    const servicos = @json($servicos);
    let itemIndex = 0;

    function adicionarItem(tipo) {
        const tbody = document.getElementById('itensBody');
        const emptyState = document.getElementById('emptyState');
        if (emptyState) emptyState.style.display = 'none';

        const tr = document.createElement('tr');
        tr.id = `item-${itemIndex}`;

        let options = '';
        if (tipo === 'produto') {
            options = produtos.map(p => `<option value="${p.id}" data-preco="${p.preco_venda}" data-nome="${p.descricao}">${p.descricao}</option>`).join('');
        } else {
            options = servicos.map(s => `<option value="${s.id}" data-preco="${s.valor_padrao}" data-nome="${s.descricao}">${s.descricao}</option>`).join('');
        }

        const selectName = tipo === 'produto' ? 'produto_id' : 'servico_id';
        const hiddenName = tipo === 'produto' ? 'servico_id' : 'produto_id';

        tr.innerHTML = `
            <td>
                <span class="badge bg-${tipo === 'produto' ? 'primary' : 'success'} bg-opacity-75">${tipo === 'produto' ? 'Produto' : 'Servico'}</span>
                <input type="hidden" name="itens[${itemIndex}][tipo]" value="${tipo}">
            </td>
            <td>
                <select name="itens[${itemIndex}][${selectName}]" class="form-select form-select-sm item-select" data-index="${itemIndex}" onchange="selecionarItem(${itemIndex})">
                    <option value="">Selecione...</option>
                    ${options}
                </select>
                <input type="hidden" name="itens[${itemIndex}][${hiddenName}]" value="">
                <input type="hidden" name="itens[${itemIndex}][descricao]" value="" class="item-descricao">
            </td>
            <td>
                <input type="number" name="itens[${itemIndex}][quantidade]" class="form-control form-control-sm text-center item-qtd"
                       step="0.001" min="0.001" value="1" onchange="calcularLinhaTotal(${itemIndex})">
            </td>
            <td>
                <input type="number" name="itens[${itemIndex}][preco_unitario]" class="form-control form-control-sm text-end item-preco"
                       step="0.01" min="0" value="0.00" onchange="calcularLinhaTotal(${itemIndex})">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm text-end item-total fw-semibold" readonly value="R$ 0,00">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerItem(${itemIndex})" title="Remover">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        itemIndex++;
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
        if (!row) return;
        const qtd = parseFloat(row.querySelector('.item-qtd').value) || 0;
        const preco = parseFloat(row.querySelector('.item-preco').value) || 0;
        const total = qtd * preco;
        row.querySelector('.item-total').value = 'R$ ' + total.toFixed(2).replace('.', ',');

        // Auto-fill descricao if empty
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
        // Show empty state if no items
        if (document.querySelectorAll('#itensBody tr').length === 0) {
            const emptyState = document.getElementById('emptyState');
            if (emptyState) emptyState.style.display = 'block';
        }
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
        const totalGeral = Math.max(0, totalProdutos + totalServicos - desconto);

        document.getElementById('totalProdutos').textContent = 'R$ ' + totalProdutos.toFixed(2).replace('.', ',');
        document.getElementById('totalServicos').textContent = 'R$ ' + totalServicos.toFixed(2).replace('.', ',');
        document.getElementById('totalGeral').textContent = 'R$ ' + totalGeral.toFixed(2).replace('.', ',');
    }
</script>
@endpush
