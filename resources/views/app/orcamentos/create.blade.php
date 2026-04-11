@extends('layouts.app')

@section('title', 'Novo Orcamento')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-file-earmark-plus me-2"></i>Novo Orcamento</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('app.orcamentos.index') }}">Orcamentos</a></li>
                <li class="breadcrumb-item active">Novo</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('app.orcamentos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Corrija os erros abaixo:</strong>
        <ul class="mb-0 mt-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('app.orcamentos.store') }}" id="formOrcamento">
    @csrf

    <div class="row g-4">
        {{-- Cliente --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-person me-1"></i> Cliente <span class="text-danger">*</span>
                </div>
                <div class="card-body">
                    <div class="position-relative">
                        <div class="input-group" id="clienteBuscaGroup">
                            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                            <input type="text" id="clienteBusca" class="form-control" placeholder="Digite o nome ou CPF/CNPJ do cliente..." autocomplete="off">
                        </div>
                        <div id="clienteResultados" class="list-group mt-1 position-absolute w-100 shadow-lg" style="z-index:1050; display:none; max-height:300px; overflow-y:auto;"></div>
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
                    @error('cliente_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Detalhes --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-gear me-1"></i> Detalhes
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Vendedor</label>
                        <select name="vendedor_id" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($vendedores as $v)
                                <option value="{{ $v->id }}" {{ old('vendedor_id') == $v->id ? 'selected' : '' }}>
                                    {{ $v->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Validade <span class="text-danger">*</span></label>
                        <input type="date" name="validade_ate" class="form-control @error('validade_ate') is-invalid @enderror"
                               value="{{ old('validade_ate', now()->addDays(30)->format('Y-m-d')) }}" required>
                        @error('validade_ate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Itens --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-list-ul me-1"></i> Itens do Orcamento</span>
                    <button type="button" class="btn btn-success btn-sm" id="btnAddItem">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar Item
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tabelaItens">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3" style="width:5%">#</th>
                                    <th style="width:35%">Produto / Servico</th>
                                    <th style="width:12%">Quantidade</th>
                                    <th style="width:15%">Preco Unit. (R$)</th>
                                    <th style="width:10%">Desc. (%)</th>
                                    <th style="width:15%" class="text-end">Total (R$)</th>
                                    <th style="width:8%" class="text-center pe-3">Acao</th>
                                </tr>
                            </thead>
                            <tbody id="itensBody">
                                {{-- Dynamic rows via JS --}}
                            </tbody>
                        </table>
                    </div>
                    <div id="itensVazio" class="text-center py-4 text-muted" style="display:none;">
                        <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                        Clique em "Adicionar Item" para incluir produtos ou servicos.
                    </div>
                </div>
                @error('itens')
                    <div class="card-footer bg-danger bg-opacity-10 text-danger small">
                        <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                    </div>
                @enderror
            </div>
        </div>

        {{-- Observacoes --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-chat-text me-1"></i> Observacoes
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Observacoes Internas</label>
                        <textarea name="observacoes_internas" class="form-control" rows="3"
                                  placeholder="Notas internas (nao visiveis para o cliente)...">{{ old('observacoes_internas') }}</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Observacoes para o Cliente</label>
                        <textarea name="observacoes_externas" class="form-control" rows="3"
                                  placeholder="Condicoes, prazo de entrega, garantia...">{{ old('observacoes_externas') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Totais --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-calculator me-1"></i> Resumo Financeiro
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <span class="text-muted">Subtotal dos Itens:</span>
                        <strong class="fs-5" id="subtotalDisplay">R$ 0,00</strong>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Desconto (%)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="desconto_percentual" id="descontoPerc"
                                       class="form-control" step="0.01" min="0" max="100" value="{{ old('desconto_percentual', 0) }}">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Desconto (R$)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="desconto_valor" id="descontoValor"
                                       class="form-control" step="0.01" min="0" value="{{ old('desconto_valor', 0) }}">
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-3 bg-success bg-opacity-10 rounded-3">
                        <span class="fs-5 fw-semibold">TOTAL:</span>
                        <strong class="fs-4 text-success" id="totalDisplay">R$ 0,00</strong>
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2 justify-content-end">
                <a href="{{ route('app.orcamentos.index') }}" class="btn btn-outline-secondary btn-lg">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Salvar Orcamento
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
    const produtosBuscarUrl = '{{ url("app/pdv/buscar-produto") }}';
    const clientesBuscarUrl = '{{ url("app/clientes/buscar") }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    // ===== ITEM ROWS =====
    function addItemRow(data = null) {
        const idx = itemIndex;
        const row = document.createElement('tr');
        row.setAttribute('data-index', idx);
        row.innerHTML = `
            <td class="ps-3 text-muted item-num">${idx + 1}</td>
            <td>
                <div class="position-relative">
                    <input type="text" class="form-control form-control-sm produto-busca"
                           placeholder="Buscar produto ou servico..." data-index="${idx}" autocomplete="off"
                           value="${data ? (data.descricao || '') : ''}">
                    <div class="produto-resultados list-group position-absolute w-100 shadow-lg"
                         style="z-index:1040; display:none; max-height:250px; overflow-y:auto;"></div>
                    <input type="hidden" name="itens[${idx}][produto_id]" class="produto-id" value="${data ? (data.produto_id || '') : ''}">
                    <input type="hidden" name="itens[${idx}][servico_id]" class="servico-id" value="${data ? (data.servico_id || '') : ''}">
                    <input type="hidden" name="itens[${idx}][descricao]" class="item-descricao" value="${data ? (data.descricao || '') : ''}">
                </div>
            </td>
            <td>
                <input type="number" name="itens[${idx}][quantidade]" class="form-control form-control-sm item-qtd text-center"
                       step="0.001" min="0.001" value="${data ? data.quantidade : 1}" required>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text px-1">R$</span>
                    <input type="number" name="itens[${idx}][preco_unitario]" class="form-control form-control-sm item-preco"
                           step="0.01" min="0" value="${data ? parseFloat(data.preco_unitario).toFixed(2) : '0.00'}" required>
                </div>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" name="itens[${idx}][desconto_percentual]" class="form-control form-control-sm item-desc-perc"
                           step="0.01" min="0" max="100" value="${data ? (data.desconto_percentual || 0) : 0}">
                    <span class="input-group-text px-1">%</span>
                </div>
            </td>
            <td class="text-end">
                <span class="item-total fw-bold text-nowrap">R$ 0,00</span>
            </td>
            <td class="text-center pe-3">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item" title="Remover item">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        document.getElementById('itensBody').appendChild(row);
        itemIndex++;
        bindRowEvents(row);
        updateEmptyState();
        calcularTotais();
        if (!data) {
            row.querySelector('.produto-busca').focus();
        }
    }

    function bindRowEvents(row) {
        const buscaInput = row.querySelector('.produto-busca');
        const resultadosDiv = row.querySelector('.produto-resultados');
        const produtoIdInput = row.querySelector('.produto-id');
        const servicoIdInput = row.querySelector('.servico-id');
        const descricaoInput = row.querySelector('.item-descricao');
        let searchTimeout;

        buscaInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const termo = this.value.trim();
            if (termo.length < 2) { resultadosDiv.style.display = 'none'; return; }

            searchTimeout = setTimeout(() => {
                fetch(`${produtosBuscarUrl}/${encodeURIComponent(termo)}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                })
                .then(r => r.json())
                .then(produtos => {
                    resultadosDiv.innerHTML = '';
                    if (produtos.length === 0) {
                        resultadosDiv.innerHTML = '<div class="list-group-item text-muted small py-2">Nenhum produto encontrado</div>';
                        resultadosDiv.style.display = 'block';
                        return;
                    }
                    produtos.forEach(p => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action py-2';
                        item.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-semibold">${p.descricao}</span>
                                    <small class="text-muted ms-1">${p.codigo_interno ? '(' + p.codigo_interno + ')' : ''}</small>
                                </div>
                                <span class="badge bg-success">R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.', ',')}</span>
                            </div>
                        `;
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            produtoIdInput.value = p.id;
                            servicoIdInput.value = '';
                            descricaoInput.value = p.descricao;
                            buscaInput.value = p.descricao;
                            row.querySelector('.item-preco').value = parseFloat(p.preco_venda).toFixed(2);
                            resultadosDiv.style.display = 'none';
                            calcularTotais();
                        });
                        resultadosDiv.appendChild(item);
                    });
                    resultadosDiv.style.display = 'block';
                });
            }, 300);
        });

        buscaInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') resultadosDiv.style.display = 'none';
        });

        row.querySelectorAll('.item-qtd, .item-preco, .item-desc-perc').forEach(input => {
            input.addEventListener('input', calcularTotais);
        });

        row.querySelector('.btn-remove-item').addEventListener('click', function() {
            row.remove();
            renumberRows();
            updateEmptyState();
            calcularTotais();
        });
    }

    function renumberRows() {
        document.querySelectorAll('#itensBody tr').forEach((row, i) => {
            row.querySelector('.item-num').textContent = i + 1;
        });
    }

    function updateEmptyState() {
        const rows = document.querySelectorAll('#itensBody tr').length;
        document.getElementById('itensVazio').style.display = rows === 0 ? 'block' : 'none';
        document.getElementById('tabelaItens').style.display = rows === 0 ? 'none' : 'table';
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

        const total = Math.max(0, subtotal - descValor);
        document.getElementById('totalDisplay').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    }

    document.getElementById('descontoPerc').addEventListener('input', calcularTotais);
    document.getElementById('descontoValor').addEventListener('input', function() {
        document.getElementById('descontoPerc').value = 0;
        calcularTotais();
    });

    document.getElementById('btnAddItem').addEventListener('click', () => addItemRow());

    // ===== CLIENTE SEARCH =====
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

    // Start with one item row
    addItemRow();

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.produto-busca') && !e.target.closest('.produto-resultados')) {
            document.querySelectorAll('.produto-resultados').forEach(d => d.style.display = 'none');
        }
        if (!e.target.closest('#clienteBusca') && !e.target.closest('#clienteResultados')) {
            clienteResultados.style.display = 'none';
        }
    });

    // Keyboard shortcut: Ctrl+Enter to submit
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            document.getElementById('formOrcamento').submit();
        }
    });
});
</script>
@endpush
