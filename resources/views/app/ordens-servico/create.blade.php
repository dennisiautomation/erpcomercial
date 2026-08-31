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
                                    <button type="button" class="btn btn-outline-primary" onclick="abrirNovoCliente(document.getElementById('clienteBusca').value.trim())">
                                        <i class="bi bi-person-plus me-1"></i> Novo
                                    </button>
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

<div class="modal fade" id="novoClienteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Cadastrar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tipo</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="nc_tipo_pessoa" id="nc_tipo_pf" value="pf" checked>
                        <label class="btn btn-outline-primary" for="nc_tipo_pf">Pessoa Física</label>
                        <input type="radio" class="btn-check" name="nc_tipo_pessoa" id="nc_tipo_pj" value="pj">
                        <label class="btn btn-outline-primary" for="nc_tipo_pj">Pessoa Jurídica</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" id="nc_label_doc">CPF <span class="text-danger">*</span></label>
                    <input type="text" id="nc_cpf_cnpj" class="form-control" maxlength="18" placeholder="000.000.000-00">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" id="nc_label_nome">Nome <span class="text-danger">*</span></label>
                    <input type="text" id="nc_nome" class="form-control">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Telefone</label>
                        <input type="text" id="nc_telefone" class="form-control" placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email</label>
                        <input type="email" id="nc_email" class="form-control">
                    </div>
                </div>
                <div id="nc_erro" class="alert alert-danger mt-3 d-none small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="nc_salvar">
                    <i class="bi bi-check-lg me-1"></i>Salvar e usar
                </button>
            </div>
        </div>
    </div>
</div>
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
                    // Cliente novo sem sair da abertura da OS
                    const vazio = document.createElement('div');
                    vazio.className = 'list-group-item small py-2';
                    vazio.innerHTML = '<span class="text-muted">Nenhum cliente encontrado.</span>';
                    const btnNovo = document.createElement('button');
                    btnNovo.type = 'button';
                    btnNovo.className = 'btn btn-sm btn-primary ms-2';
                    btnNovo.innerHTML = '<i class="bi bi-person-plus me-1"></i> Cadastrar';
                    btnNovo.addEventListener('click', () => abrirNovoCliente(termo));
                    vazio.appendChild(btnNovo);
                    clienteResultados.appendChild(vazio);
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

    // ─── Novo cliente (modal) ───────────────────────────────
    const modalEl = document.getElementById('novoClienteModal');
    const ncModal = new bootstrap.Modal(modalEl);
    const ncCpfCnpj = document.getElementById('nc_cpf_cnpj');
    const ncNome = document.getElementById('nc_nome');
    const ncLabelDoc = document.getElementById('nc_label_doc');
    const ncLabelNome = document.getElementById('nc_label_nome');
    const ncErro = document.getElementById('nc_erro');

    function onlyDigits(v) { return v.replace(/\D/g, ''); }
    function maskCpf(v) { return onlyDigits(v).slice(0,11).replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})\.(\d{3})(\d)/,'$1.$2.$3').replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/,'$1.$2.$3-$4'); }
    function maskCnpj(v) {
        // CNPJ alfanumérico (NT 2025.001): letras nas 12 primeiras posições, DV numérico
        const a = v.replace(/[^0-9A-Za-z]/g, '').toUpperCase().slice(0, 14);
        const base = a.slice(0, 12), dv = a.slice(12).replace(/[^0-9]/g, '');
        let out = base.slice(0, 2);
        if (base.length > 2) out += '.' + base.slice(2, 5);
        if (base.length > 5) out += '.' + base.slice(5, 8);
        if (base.length > 8) out += '/' + base.slice(8, 12);
        if (dv.length) out += '-' + dv;
        return out;
    }
    function maskTel(v) { v = onlyDigits(v).slice(0,11); if (v.length > 10) return v.replace(/^(\d{2})(\d{5})(\d{4}).*/,'($1) $2-$3'); if (v.length > 6) return v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/,'($1) $2-$3'); if (v.length > 2) return v.replace(/^(\d{2})(\d{0,5})/,'($1) $2'); return v; }

    function aplicarTipo() {
        const isPJ = document.getElementById('nc_tipo_pj').checked;
        ncLabelDoc.innerHTML = (isPJ ? 'CNPJ' : 'CPF') + ' <span class="text-danger">*</span>';
        ncLabelNome.innerHTML = (isPJ ? 'Razão Social' : 'Nome') + ' <span class="text-danger">*</span>';
        ncCpfCnpj.placeholder = isPJ ? '00.000.000/0000-00' : '000.000.000-00';
        ncCpfCnpj.value = isPJ ? maskCnpj(ncCpfCnpj.value) : maskCpf(ncCpfCnpj.value);
    }
    document.querySelectorAll('input[name="nc_tipo_pessoa"]').forEach(r => r.addEventListener('change', aplicarTipo));
    ncCpfCnpj.addEventListener('input', function() {
        const isPJ = document.getElementById('nc_tipo_pj').checked;
        this.value = isPJ ? maskCnpj(this.value) : maskCpf(this.value);
    });
    ncCpfCnpj.addEventListener('blur', function() {
        const isPJ = document.getElementById('nc_tipo_pj').checked;
        if (!isPJ) return;
        const cnpj = this.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase();
        if (cnpj.length !== 14) return;
        if (/[A-Z]/.test(cnpj)) return; // consulta automática só existe p/ CNPJ numérico
        const original = this.value;
        this.disabled = true;
        fetch('https://brasilapi.com.br/api/cnpj/v1/' + cnpj)
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(data => {
                if (data.razao_social && !ncNome.value) ncNome.value = data.razao_social;
                const tel = document.getElementById('nc_telefone');
                if (data.ddd_telefone_1 && !tel.value) tel.value = maskTel(data.ddd_telefone_1);
                const email = document.getElementById('nc_email');
                if (data.email && !email.value) email.value = data.email;
            })
            .catch(() => {})
            .finally(() => { this.disabled = false; });
    });
    document.getElementById('nc_telefone').addEventListener('input', function() { this.value = maskTel(this.value); });

    function abrirNovoCliente(termo) {
        ncErro.classList.add('d-none');
        ncErro.textContent = '';
        ncCpfCnpj.value = '';
        ncNome.value = '';
        document.getElementById('nc_telefone').value = '';
        document.getElementById('nc_email').value = '';
        const digits = (termo || '').replace(/[^0-9A-Za-z]/g, ''); // preserva CNPJ alfanumérico
        if (digits.length === 11 || digits.length === 14) {
            document.getElementById(digits.length === 14 ? 'nc_tipo_pj' : 'nc_tipo_pf').checked = true;
            aplicarTipo();
            ncCpfCnpj.value = digits.length === 14 ? maskCnpj(digits) : maskCpf(digits);
        } else if (termo) {
            ncNome.value = termo;
        }
        clienteResultados.style.display = 'none';
        ncModal.show();
        setTimeout(() => (ncNome.value ? ncCpfCnpj : ncNome).focus(), 300);
    }
    window.abrirNovoCliente = abrirNovoCliente;

    document.getElementById('nc_salvar').addEventListener('click', function() {
        ncErro.classList.add('d-none');
        const payload = {
            tipo_pessoa: document.querySelector('input[name="nc_tipo_pessoa"]:checked').value,
            cpf_cnpj: ncCpfCnpj.value,
            nome_razao_social: ncNome.value.trim(),
            telefone: document.getElementById('nc_telefone').value || null,
            email: document.getElementById('nc_email').value || null,
        };
        if (!payload.cpf_cnpj || !payload.nome_razao_social) {
            ncErro.textContent = 'Informe documento e nome.';
            ncErro.classList.remove('d-none');
            return;
        }
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Salvando...';
        fetch('{{ route("app.clientes.quick") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        })
        .then(async r => {
            if (!r.ok) {
                const data = await r.json().catch(() => ({}));
                const msgs = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Erro ao salvar.');
                throw new Error(msgs);
            }
            return r.json();
        })
        .then(c => {
            document.getElementById('clienteId').value = c.id;
            document.getElementById('clienteNome').textContent = c.nome_razao_social;
            document.getElementById('clienteDoc').textContent = c.cpf_cnpj || '';
            document.getElementById('clienteSelecionado').style.display = 'block';
            document.getElementById('clienteBuscaGroup').style.display = 'none';
            clienteBusca.value = '';
            clienteResultados.style.display = 'none';
            ncModal.hide();
        })
        .catch(err => {
            ncErro.textContent = err.message;
            ncErro.classList.remove('d-none');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Salvar e usar';
        });
    });
</script>
@endpush
