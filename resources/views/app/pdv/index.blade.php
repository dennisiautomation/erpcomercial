<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PDV - ERP Comercial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        body { background: #1a1a2e; color: #e0e0e0; font-family: 'Segoe UI', Tahoma, sans-serif; }

        .pdv-topbar {
            background: #16213e;
            height: 50px;
            display: flex;
            align-items: center;
            padding: 0 20px;
            border-bottom: 2px solid #0f3460;
        }
        .pdv-topbar .info { font-size: 0.9rem; color: #a0a0c0; }
        .pdv-topbar .info strong { color: #fff; }

        .pdv-main { display: flex; height: calc(100vh - 90px); }

        .pdv-left {
            flex: 0 0 70%;
            display: flex;
            flex-direction: column;
            padding: 15px;
            border-right: 2px solid #0f3460;
        }

        .pdv-right {
            flex: 0 0 30%;
            display: flex;
            flex-direction: column;
            padding: 15px;
            background: #16213e;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .search-bar input {
            flex: 1;
            background: #0f3460;
            border: 2px solid #533483;
            color: #fff;
            font-size: 1.3rem;
            padding: 12px 15px;
            border-radius: 8px;
        }
        .search-bar input:focus { outline: none; border-color: #e94560; }
        .search-bar input::placeholder { color: #666; }

        .items-table-wrapper {
            flex: 1;
            overflow-y: auto;
            border-radius: 8px;
            background: #16213e;
        }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table thead th {
            background: #0f3460;
            padding: 10px 12px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a0a0c0;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #0f3460;
            font-size: 1.05rem;
        }
        .items-table tbody tr:hover { background: rgba(83, 52, 131, 0.2); }

        .btn-remove { background: none; border: none; color: #e94560; cursor: pointer; font-size: 1.2rem; }
        .btn-remove:hover { color: #ff6b81; }

        .summary-section { margin-bottom: 15px; }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 1rem;
        }
        .summary-total {
            font-size: 2.2rem;
            font-weight: bold;
            color: #4ecca3;
            text-align: right;
            padding: 10px 0;
            border-top: 2px solid #533483;
            border-bottom: 2px solid #533483;
            margin: 10px 0;
        }

        .cliente-section { margin-bottom: 15px; }
        .cliente-section input {
            background: #0f3460;
            border: 1px solid #533483;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            width: 100%;
            font-size: 0.9rem;
        }
        .cliente-section input::placeholder { color: #666; }

        .payment-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 15px;
        }
        .btn-payment {
            padding: 10px;
            border: 2px solid #533483;
            background: #0f3460;
            color: #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-payment:hover { background: #533483; color: #fff; }
        .btn-payment.active { background: #533483; border-color: #e94560; color: #fff; }
        .btn-payment i { display: block; font-size: 1.3rem; margin-bottom: 4px; }

        .payment-split { margin-bottom: 10px; font-size: 0.85rem; }
        .payment-split .split-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 8px;
            background: #0f3460;
            border-radius: 4px;
            margin-bottom: 4px;
        }
        .payment-split .split-remove { color: #e94560; cursor: pointer; border: none; background: none; }

        .troco-display {
            background: #0f3460;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            margin-bottom: 10px;
            display: none;
        }
        .troco-display .label { color: #a0a0c0; font-size: 0.8rem; }
        .troco-display .value { color: #4ecca3; font-size: 1.5rem; font-weight: bold; }

        .btn-finalizar {
            width: 100%;
            padding: 15px;
            background: #4ecca3;
            color: #1a1a2e;
            border: none;
            border-radius: 10px;
            font-size: 1.3rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-finalizar:hover { background: #3ab88f; }
        .btn-finalizar:disabled { background: #555; color: #888; cursor: not-allowed; }

        .pdv-bottombar {
            background: #0f3460;
            height: 40px;
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 20px;
            font-size: 0.75rem;
            color: #666;
            border-top: 1px solid #533483;
        }
        .pdv-bottombar kbd {
            background: #533483;
            color: #fff;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: auto;
            margin-bottom: 10px;
        }
        .btn-action {
            flex: 1;
            min-width: 80px;
            padding: 6px 4px;
            background: #0f3460;
            border: 1px solid #533483;
            color: #a0a0c0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.7rem;
            text-align: center;
        }
        .btn-action:hover { background: #533483; color: #fff; }

        .no-caixa-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .no-caixa-box {
            background: #16213e;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            border: 2px solid #533483;
        }

        /* Modal overrides for dark theme */
        .modal-content { background: #16213e; color: #e0e0e0; border: 1px solid #533483; }
        .modal-header { border-bottom: 1px solid #0f3460; }
        .modal-footer { border-top: 1px solid #0f3460; }
        .modal .form-control, .modal .form-select {
            background: #0f3460;
            border: 1px solid #533483;
            color: #fff;
        }
        .modal .form-label { color: #a0a0c0; }
        .btn-close { filter: invert(1); }

        .valor-input-modal {
            font-size: 2rem;
            text-align: center;
            padding: 15px;
        }

        @media print {
            body { background: #fff; color: #000; }
            .pdv-topbar, .pdv-bottombar, .pdv-right { display: none !important; }
            .pdv-left { flex: 0 0 100%; border: none; }
        }
    </style>
</head>
<body>
    @if(!$caixa)
    {{-- No caixa open --}}
    <div class="no-caixa-overlay">
        <div class="no-caixa-box">
            <i class="bi bi-lock-fill" style="font-size:3rem; color:#e94560;"></i>
            <h3 class="mt-3">Caixa Fechado</h3>
            <p class="text-muted">Abra o caixa para iniciar as vendas.</p>
            <a href="{{ route('app.caixa.abrir') }}" class="btn btn-lg" style="background:#4ecca3; color:#1a1a2e; font-weight:bold;">
                <i class="bi bi-unlock me-2"></i>Abrir Caixa
            </a>
            <div class="mt-3">
                <a href="{{ route('app.dashboard') }}" class="text-muted">
                    <i class="bi bi-arrow-left me-1"></i>Voltar ao Dashboard
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Top Bar --}}
    <div class="pdv-topbar">
        <div class="d-flex justify-content-between w-100 align-items-center">
            <div class="d-flex gap-4">
                <span class="info"><i class="bi bi-shop me-1"></i> <strong>{{ auth()->user()->unidade->nome ?? 'Unidade' }}</strong></span>
                <span class="info"><i class="bi bi-person me-1"></i> <strong>{{ auth()->user()->name ?? 'Operador' }}</strong></span>
                @if($caixa)
                    <span class="info"><i class="bi bi-cash-stack me-1"></i> Caixa <strong>#{{ $caixa->numero_caixa }}</strong></span>
                @endif
            </div>
            <div class="d-flex gap-3 align-items-center">
                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>NAO FISCAL</span>
                <a href="{{ route('app.dashboard') }}" class="text-decoration-none text-muted" style="font-size:0.85rem;">
                    <i class="bi bi-box-arrow-left me-1"></i>Sair PDV
                </a>
            </div>
        </div>
    </div>

    {{-- Main Area --}}
    <div class="pdv-main">
        {{-- Left: Products --}}
        <div class="pdv-left">
            <div class="search-bar">
                <input type="text" id="buscaProduto" placeholder="Bipe o codigo de barras ou digite o nome do produto..." autofocus>
            </div>
            <div id="searchResults" style="display:none; position:absolute; z-index:100; width:60%; max-height:300px; overflow-y:auto; background:#0f3460; border:2px solid #533483; border-radius:8px; margin-top:55px;">
            </div>

            <div class="items-table-wrapper">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:5%">#</th>
                            <th style="width:10%">Cod</th>
                            <th style="width:35%">Descricao</th>
                            <th style="width:10%">Qtd</th>
                            <th style="width:15%">Unit.</th>
                            <th style="width:15%">Total</th>
                            <th style="width:5%"></th>
                        </tr>
                    </thead>
                    <tbody id="itensVenda">
                        <tr id="emptyRow">
                            <td colspan="7" class="text-center py-5" style="color:#555;">
                                <i class="bi bi-upc-scan" style="font-size:3rem;"></i>
                                <p class="mt-2">Adicione produtos para iniciar a venda</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Right: Summary & Payment --}}
        <div class="pdv-right">
            {{-- Summary --}}
            <div class="summary-section">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotalDisplay">R$ 0,00</span>
                </div>
                <div class="summary-row">
                    <span>Desconto:</span>
                    <span id="descontoDisplay" class="text-danger">R$ 0,00</span>
                </div>
                <div class="summary-total">
                    <small style="font-size:0.5em; color:#a0a0c0; display:block;">TOTAL</small>
                    R$ <span id="totalDisplay">0,00</span>
                </div>
            </div>

            {{-- Cliente --}}
            <div class="cliente-section">
                <label style="font-size:0.8rem; color:#a0a0c0;"><i class="bi bi-person me-1"></i>Cliente (opcional)</label>
                <input type="text" id="clienteCpfCnpj" placeholder="CPF/CNPJ do cliente..." class="mt-1">
                <input type="hidden" id="clienteIdPdv" value="">
                <small id="clienteNomePdv" class="text-muted mt-1 d-block"></small>
            </div>

            {{-- Payment --}}
            <div>
                <label style="font-size:0.8rem; color:#a0a0c0; margin-bottom:8px; display:block;">
                    <i class="bi bi-credit-card me-1"></i>Forma de Pagamento
                </label>
                <div class="payment-buttons">
                    <button class="btn-payment" data-forma="dinheiro" onclick="selecionarPagamento('dinheiro')">
                        <i class="bi bi-cash-coin"></i>Dinheiro
                    </button>
                    <button class="btn-payment" data-forma="credito" onclick="selecionarPagamento('credito')">
                        <i class="bi bi-credit-card-2-front"></i>Credito
                    </button>
                    <button class="btn-payment" data-forma="debito" onclick="selecionarPagamento('debito')">
                        <i class="bi bi-credit-card"></i>Debito
                    </button>
                    <button class="btn-payment" data-forma="pix" onclick="selecionarPagamento('pix')">
                        <i class="bi bi-qr-code"></i>PIX
                    </button>
                </div>
            </div>

            {{-- Split payments --}}
            <div class="payment-split" id="paymentSplitArea">
                {{-- Filled by JS --}}
            </div>

            {{-- Troco --}}
            <div class="troco-display" id="trocoArea">
                <div class="label">TROCO</div>
                <div class="value" id="trocoDisplay">R$ 0,00</div>
            </div>

            {{-- Action buttons --}}
            <div class="action-buttons">
                <button class="btn-action" onclick="abrirBuscaProduto()"><kbd>F1</kbd><br>Buscar</button>
                <button class="btn-action" onclick="abrirCliente()"><kbd>F2</kbd><br>Cliente</button>
                <button class="btn-action" onclick="abrirDesconto()"><kbd>F4</kbd><br>Desconto</button>
                <button class="btn-action" onclick="abrirSangria()"><kbd>F7</kbd><br>Sangria</button>
                <button class="btn-action" onclick="abrirSuprimento()"><kbd>F8</kbd><br>Suprimento</button>
                <button class="btn-action" onclick="cancelarItem()"><kbd>F9</kbd><br>Canc.Item</button>
                <button class="btn-action" onclick="fecharCaixa()"><kbd>F10</kbd><br>Fechar Cx</button>
            </div>

            {{-- Finalizar --}}
            <button class="btn-finalizar" id="btnFinalizar" onclick="finalizarVenda()" disabled>
                <i class="bi bi-check-circle me-2"></i>FINALIZAR VENDA (F12)
            </button>
        </div>
    </div>

    {{-- Bottom Bar --}}
    <div class="pdv-bottombar">
        <span><kbd>F1</kbd> Buscar</span>
        <span><kbd>F2</kbd> Cliente</span>
        <span><kbd>F4</kbd> Desconto</span>
        <span><kbd>F7</kbd> Sangria</span>
        <span><kbd>F8</kbd> Suprimento</span>
        <span><kbd>F9</kbd> Canc. Item</span>
        <span><kbd>F10</kbd> Fechar Caixa</span>
        <span><kbd>F12</kbd> Finalizar</span>
        <span class="ms-auto">ERP Comercial - PDV</span>
    </div>

    {{-- Modal: Sangria --}}
    <div class="modal fade" id="modalSangria" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-down-circle me-2"></i>Sangria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Valor</label>
                        <input type="number" id="sangriaValor" class="form-control valor-input-modal" step="0.01" min="0.01" placeholder="0,00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descricao</label>
                        <input type="text" id="sangriaDescricao" class="form-control" placeholder="Motivo da sangria...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmarSangria()">Confirmar Sangria</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Suprimento --}}
    <div class="modal fade" id="modalSuprimento" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2"></i>Suprimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Valor</label>
                        <input type="number" id="suprimentoValor" class="form-control valor-input-modal" step="0.01" min="0.01" placeholder="0,00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descricao</label>
                        <input type="text" id="suprimentoDescricao" class="form-control" placeholder="Motivo do suprimento...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="confirmarSuprimento()">Confirmar Suprimento</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Desconto --}}
    <div class="modal fade" id="modalDesconto" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-tag me-2"></i>Desconto na Venda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Valor do Desconto (R$)</label>
                        <input type="number" id="descontoValorInput" class="form-control valor-input-modal" step="0.01" min="0" placeholder="0,00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="aplicarDesconto()">Aplicar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Pagamento Dinheiro --}}
    <div class="modal fade" id="modalDinheiro" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash me-2"></i>Valor Recebido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center">Total: <strong id="modalDinheiroTotal" class="fs-4"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Valor em Dinheiro</label>
                        <input type="number" id="dinheiroValor" class="form-control valor-input-modal" step="0.01" min="0.01" placeholder="0,00">
                    </div>
                    <div class="text-center">
                        <span class="text-muted">Troco: </span>
                        <strong id="modalDinheiroTroco" class="fs-5" style="color:#4ecca3;">R$ 0,00</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="confirmarDinheiro()">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Cupom Print Area (hidden) --}}
    <div id="cupomArea" style="display:none;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let itensVenda = [];
    let pagamentos = [];
    let descontoGeral = 0;
    let itemSelecionado = -1;

    // Barcode scanner detection
    let barcodeBuffer = '';
    let barcodeTimeout;
    let lastKeyTime = 0;

    document.addEventListener('keydown', function(e) {
        // F-key shortcuts
        if (e.key === 'F1') { e.preventDefault(); abrirBuscaProduto(); }
        if (e.key === 'F2') { e.preventDefault(); abrirCliente(); }
        if (e.key === 'F4') { e.preventDefault(); abrirDesconto(); }
        if (e.key === 'F7') { e.preventDefault(); abrirSangria(); }
        if (e.key === 'F8') { e.preventDefault(); abrirSuprimento(); }
        if (e.key === 'F9') { e.preventDefault(); cancelarItem(); }
        if (e.key === 'F10') { e.preventDefault(); fecharCaixa(); }
        if (e.key === 'F12') { e.preventDefault(); finalizarVenda(); }
    });

    // Search product input
    const buscaInput = document.getElementById('buscaProduto');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;

    buscaInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const codigo = this.value.trim();
            if (codigo) buscarProduto(codigo, true);
        }
    });

    buscaInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const termo = this.value.trim();
        if (termo.length < 2) { searchResults.style.display = 'none'; return; }

        searchTimeout = setTimeout(() => {
            fetch(`{{ url('app/pdv/buscar-produto') }}/${encodeURIComponent(termo)}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(produtos => {
                searchResults.innerHTML = '';
                if (produtos.length === 0) {
                    searchResults.innerHTML = '<div style="padding:15px; color:#666; text-align:center;">Nenhum produto encontrado</div>';
                    searchResults.style.display = 'block';
                    return;
                }
                produtos.forEach(p => {
                    const div = document.createElement('div');
                    div.style.cssText = 'padding:10px 15px; cursor:pointer; border-bottom:1px solid #1a1a2e;';
                    div.innerHTML = `<strong>${p.codigo_interno || '-'}</strong> | ${p.descricao} <span style="float:right; color:#4ecca3;">R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.',',')}</span>`;
                    div.addEventListener('mouseover', () => div.style.background = '#533483');
                    div.addEventListener('mouseout', () => div.style.background = '');
                    div.addEventListener('click', () => {
                        adicionarItem(p);
                        searchResults.style.display = 'none';
                        buscaInput.value = '';
                        buscaInput.focus();
                    });
                    searchResults.appendChild(div);
                });
                searchResults.style.display = 'block';
            });
        }, 200);
    });

    function buscarProduto(codigo, autoAdd = false) {
        fetch(`{{ url('app/pdv/buscar-produto') }}/${encodeURIComponent(codigo)}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(r => r.json())
        .then(produtos => {
            if (produtos.length === 1 && autoAdd) {
                adicionarItem(produtos[0]);
                buscaInput.value = '';
                searchResults.style.display = 'none';
            } else if (produtos.length > 1) {
                // Show results
                searchResults.innerHTML = '';
                produtos.forEach(p => {
                    const div = document.createElement('div');
                    div.style.cssText = 'padding:10px 15px; cursor:pointer; border-bottom:1px solid #1a1a2e;';
                    div.innerHTML = `<strong>${p.codigo_interno || '-'}</strong> | ${p.descricao} <span style="float:right; color:#4ecca3;">R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.',',')}</span>`;
                    div.addEventListener('click', () => {
                        adicionarItem(p);
                        searchResults.style.display = 'none';
                        buscaInput.value = '';
                        buscaInput.focus();
                    });
                    searchResults.appendChild(div);
                });
                searchResults.style.display = 'block';
            } else if (produtos.length === 0) {
                alert('Produto nao encontrado.');
                buscaInput.value = '';
            }
        });
    }

    function adicionarItem(produto) {
        // Check if item already in list
        const existente = itensVenda.findIndex(i => i.produto_id === produto.id);
        if (existente >= 0) {
            itensVenda[existente].quantidade++;
            itensVenda[existente].total = itensVenda[existente].quantidade * itensVenda[existente].preco_unitario;
        } else {
            itensVenda.push({
                produto_id: produto.id,
                codigo: produto.codigo_interno || '',
                descricao: produto.descricao,
                quantidade: 1,
                preco_unitario: parseFloat(produto.preco_venda),
                desconto_valor: 0,
                total: parseFloat(produto.preco_venda),
            });
        }
        renderItens();
        calcularTotais();
    }

    function renderItens() {
        const tbody = document.getElementById('itensVenda');
        tbody.innerHTML = '';

        if (itensVenda.length === 0) {
            tbody.innerHTML = `<tr id="emptyRow"><td colspan="7" class="text-center py-5" style="color:#555;">
                <i class="bi bi-upc-scan" style="font-size:3rem;"></i>
                <p class="mt-2">Adicione produtos para iniciar a venda</p></td></tr>`;
            return;
        }

        itensVenda.forEach((item, idx) => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            if (idx === itemSelecionado) tr.style.background = 'rgba(233,69,96,0.2)';
            tr.addEventListener('click', () => { itemSelecionado = idx; renderItens(); });
            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td>${item.codigo}</td>
                <td>${item.descricao}</td>
                <td>
                    <input type="number" value="${item.quantidade}" min="0.001" step="0.001"
                        style="width:60px; background:#0f3460; border:1px solid #533483; color:#fff; text-align:center; border-radius:4px; padding:2px;"
                        onchange="alterarQtd(${idx}, this.value)" onclick="event.stopPropagation()">
                </td>
                <td>R$ ${item.preco_unitario.toFixed(2).replace('.', ',')}</td>
                <td style="color:#4ecca3; font-weight:600;">R$ ${item.total.toFixed(2).replace('.', ',')}</td>
                <td><button class="btn-remove" onclick="event.stopPropagation(); removerItem(${idx})"><i class="bi bi-x-lg"></i></button></td>
            `;
            tbody.appendChild(tr);
        });
    }

    function alterarQtd(idx, val) {
        const qtd = parseFloat(val);
        if (qtd > 0) {
            itensVenda[idx].quantidade = qtd;
            itensVenda[idx].total = qtd * itensVenda[idx].preco_unitario - itensVenda[idx].desconto_valor;
            calcularTotais();
            renderItens();
        }
    }

    function removerItem(idx) {
        itensVenda.splice(idx, 1);
        itemSelecionado = -1;
        renderItens();
        calcularTotais();
    }

    function cancelarItem() {
        if (itemSelecionado >= 0 && itemSelecionado < itensVenda.length) {
            removerItem(itemSelecionado);
        } else if (itensVenda.length > 0) {
            removerItem(itensVenda.length - 1);
        }
    }

    function calcularTotais() {
        let subtotal = itensVenda.reduce((sum, i) => sum + i.total, 0);
        let total = subtotal - descontoGeral;
        if (total < 0) total = 0;

        document.getElementById('subtotalDisplay').textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
        document.getElementById('descontoDisplay').textContent = descontoGeral > 0 ? '- R$ ' + descontoGeral.toFixed(2).replace('.', ',') : 'R$ 0,00';
        document.getElementById('totalDisplay').textContent = total.toFixed(2).replace('.', ',');

        const btn = document.getElementById('btnFinalizar');
        btn.disabled = itensVenda.length === 0 || pagamentos.length === 0;

        calcularTroco();
    }

    function getTotal() {
        let subtotal = itensVenda.reduce((sum, i) => sum + i.total, 0);
        return Math.max(0, subtotal - descontoGeral);
    }

    // Payment
    function selecionarPagamento(forma) {
        if (forma === 'dinheiro') {
            const total = getTotal();
            const restante = total - pagamentos.reduce((s, p) => s + p.valor, 0);
            document.getElementById('modalDinheiroTotal').textContent = 'R$ ' + restante.toFixed(2).replace('.', ',');
            document.getElementById('dinheiroValor').value = restante.toFixed(2);
            document.getElementById('modalDinheiroTroco').textContent = 'R$ 0,00';
            new bootstrap.Modal(document.getElementById('modalDinheiro')).show();
            setTimeout(() => document.getElementById('dinheiroValor').focus(), 500);
        } else {
            const total = getTotal();
            const restante = total - pagamentos.reduce((s, p) => s + p.valor, 0);
            if (restante <= 0) return;
            pagamentos.push({ forma: forma, valor: restante });
            renderPagamentos();
            calcularTotais();
        }

        document.querySelectorAll('.btn-payment').forEach(b => b.classList.remove('active'));
    }

    document.getElementById('dinheiroValor').addEventListener('input', function() {
        const recebido = parseFloat(this.value) || 0;
        const total = getTotal();
        const restante = total - pagamentos.reduce((s, p) => s + p.valor, 0);
        const troco = Math.max(0, recebido - restante);
        document.getElementById('modalDinheiroTroco').textContent = 'R$ ' + troco.toFixed(2).replace('.', ',');
    });

    function confirmarDinheiro() {
        const valor = parseFloat(document.getElementById('dinheiroValor').value) || 0;
        if (valor <= 0) return;
        const total = getTotal();
        const restante = total - pagamentos.reduce((s, p) => s + p.valor, 0);
        pagamentos.push({ forma: 'dinheiro', valor: Math.min(valor, restante) });
        renderPagamentos();
        calcularTotais();
        bootstrap.Modal.getInstance(document.getElementById('modalDinheiro')).hide();
    }

    function renderPagamentos() {
        const area = document.getElementById('paymentSplitArea');
        area.innerHTML = '';
        pagamentos.forEach((p, idx) => {
            const div = document.createElement('div');
            div.className = 'split-item';
            div.innerHTML = `
                <span>${p.forma.charAt(0).toUpperCase() + p.forma.slice(1)}</span>
                <span>
                    R$ ${p.valor.toFixed(2).replace('.', ',')}
                    <button class="split-remove" onclick="removerPagamento(${idx})"><i class="bi bi-x"></i></button>
                </span>
            `;
            area.appendChild(div);
        });
    }

    function removerPagamento(idx) {
        pagamentos.splice(idx, 1);
        renderPagamentos();
        calcularTotais();
    }

    function calcularTroco() {
        const total = getTotal();
        const totalPago = pagamentos.reduce((s, p) => s + p.valor, 0);
        const troco = Math.max(0, totalPago - total);
        const trocoArea = document.getElementById('trocoArea');
        if (troco > 0) {
            document.getElementById('trocoDisplay').textContent = 'R$ ' + troco.toFixed(2).replace('.', ',');
            trocoArea.style.display = 'block';
        } else {
            trocoArea.style.display = 'none';
        }
    }

    // Actions
    function abrirBuscaProduto() { buscaInput.focus(); buscaInput.select(); }
    function abrirCliente() { document.getElementById('clienteCpfCnpj').focus(); }

    function abrirDesconto() {
        document.getElementById('descontoValorInput').value = descontoGeral.toFixed(2);
        new bootstrap.Modal(document.getElementById('modalDesconto')).show();
    }

    function aplicarDesconto() {
        descontoGeral = parseFloat(document.getElementById('descontoValorInput').value) || 0;
        calcularTotais();
        bootstrap.Modal.getInstance(document.getElementById('modalDesconto')).hide();
    }

    function abrirSangria() {
        document.getElementById('sangriaValor').value = '';
        document.getElementById('sangriaDescricao').value = '';
        new bootstrap.Modal(document.getElementById('modalSangria')).show();
    }

    function confirmarSangria() {
        const valor = parseFloat(document.getElementById('sangriaValor').value);
        const descricao = document.getElementById('sangriaDescricao').value.trim();
        if (!valor || valor <= 0 || !descricao) { alert('Preencha valor e descricao.'); return; }

        fetch('{{ route("app.caixa.sangria") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ valor, descricao })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                bootstrap.Modal.getInstance(document.getElementById('modalSangria')).hide();
            } else {
                alert(data.error || 'Erro ao registrar sangria.');
            }
        });
    }

    function abrirSuprimento() {
        document.getElementById('suprimentoValor').value = '';
        document.getElementById('suprimentoDescricao').value = '';
        new bootstrap.Modal(document.getElementById('modalSuprimento')).show();
    }

    function confirmarSuprimento() {
        const valor = parseFloat(document.getElementById('suprimentoValor').value);
        const descricao = document.getElementById('suprimentoDescricao').value.trim();
        if (!valor || valor <= 0 || !descricao) { alert('Preencha valor e descricao.'); return; }

        fetch('{{ route("app.caixa.suprimento") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ valor, descricao })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                bootstrap.Modal.getInstance(document.getElementById('modalSuprimento')).hide();
            } else {
                alert(data.error || 'Erro ao registrar suprimento.');
            }
        });
    }

    function fecharCaixa() {
        if (confirm('Deseja fechar o caixa?')) {
            window.location.href = '{{ route("app.caixa.fechar") }}';
        }
    }

    function finalizarVenda() {
        if (itensVenda.length === 0) { alert('Adicione itens a venda.'); return; }
        if (pagamentos.length === 0) { alert('Selecione a forma de pagamento.'); return; }

        const total = getTotal();
        const totalPago = pagamentos.reduce((s, p) => s + p.valor, 0);
        if (totalPago < total) { alert('Valor pago insuficiente.'); return; }

        const btn = document.getElementById('btnFinalizar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>PROCESSANDO...';

        fetch('{{ route("app.pdv.registrar") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                itens: itensVenda,
                pagamentos: pagamentos,
                cliente_id: document.getElementById('clienteIdPdv').value || null,
                desconto_valor: descontoGeral,
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Show receipt
                if (data.cupom) {
                    const cupomWindow = window.open('', '_blank', 'width=350,height=600');
                    cupomWindow.document.write(data.cupom);
                    cupomWindow.document.close();
                    cupomWindow.print();
                }

                // Reset
                itensVenda = [];
                pagamentos = [];
                descontoGeral = 0;
                itemSelecionado = -1;
                document.getElementById('clienteIdPdv').value = '';
                document.getElementById('clienteCpfCnpj').value = '';
                document.getElementById('clienteNomePdv').textContent = '';
                renderItens();
                renderPagamentos();
                calcularTotais();
                buscaInput.focus();

                alert(`Venda #${data.venda.numero} registrada com sucesso!`);
            } else {
                alert(data.error || 'Erro ao registrar venda.');
            }
        })
        .catch(err => {
            alert('Erro de conexao. Tente novamente.');
            console.error(err);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>FINALIZAR VENDA (F12)';
        });
    }

    // Close search results on click outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#buscaProduto') && !e.target.closest('#searchResults')) {
            searchResults.style.display = 'none';
        }
    });

    // Auto-focus search on load
    @if($caixa)
    buscaInput.focus();
    @endif
    </script>
</body>
</html>
