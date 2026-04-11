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
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --bg-input: #1e293b;
            --border: #475569;
            --border-focus: #3b82f6;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-blue: #3b82f6;
            --accent-green: #22c55e;
            --accent-red: #ef4444;
            --accent-yellow: #eab308;
            --accent-purple: #a855f7;
            --accent-cyan: #06b6d4;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* ===== TOP BAR ===== */
        .pdv-topbar {
            background: var(--bg-secondary);
            height: 52px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 16px;
            border-bottom: 1px solid var(--border);
            user-select: none;
        }
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--accent-blue);
            white-space: nowrap;
        }
        .topbar-brand i { font-size: 1.3rem; }
        .topbar-info {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .topbar-info .label { color: var(--text-muted); margin-right: 4px; }
        .topbar-info .value { color: var(--text-primary); font-weight: 600; }
        .topbar-spacer { flex: 1; }
        .topbar-fiscal {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .topbar-fiscal.fiscal-on { background: rgba(34,197,94,0.15); color: var(--accent-green); }
        .topbar-fiscal.fiscal-off { background: rgba(239,68,68,0.15); color: var(--accent-red); }
        .topbar-fiscal .dot {
            width: 7px; height: 7px; border-radius: 50%;
            display: inline-block;
        }
        .topbar-fiscal.fiscal-on .dot { background: var(--accent-green); }
        .topbar-fiscal.fiscal-off .dot { background: var(--accent-red); }
        .topbar-clock {
            font-size: 1.1rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: var(--text-primary);
            letter-spacing: 0.5px;
        }

        /* ===== MAIN LAYOUT ===== */
        .pdv-main {
            display: flex;
            height: calc(100vh - 52px - 36px);
        }

        /* ===== LEFT PANEL (65%) ===== */
        .pdv-left {
            flex: 0 0 65%;
            display: flex;
            flex-direction: column;
            padding: 12px 12px 8px 12px;
            overflow: hidden;
        }

        /* Search */
        .search-container {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }
        .search-input-wrap {
            flex: 1;
            position: relative;
        }
        .search-input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.2rem;
        }
        #searchInput {
            width: 100%;
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            color: var(--text-primary);
            font-size: 1.2rem;
            padding: 12px 14px 12px 42px;
            border-radius: 10px;
            transition: border-color 0.2s;
        }
        #searchInput:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        #searchInput::placeholder { color: var(--text-muted); }

        .search-results-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 0 0 10px 10px;
            max-height: 320px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .search-results-dropdown.show { display: block; }
        .search-result-item {
            padding: 10px 14px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(71,85,105,0.3);
            transition: background 0.1s;
        }
        .search-result-item:hover { background: var(--bg-tertiary); }
        .search-result-item .prod-name { font-weight: 500; }
        .search-result-item .prod-code { font-size: 0.78rem; color: var(--text-muted); }
        .search-result-item .prod-price { font-weight: 700; color: var(--accent-green); font-size: 1.05rem; }

        /* Items list */
        .items-container {
            flex: 1;
            overflow-y: auto;
            border-radius: 10px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
        }
        .items-container::-webkit-scrollbar { width: 6px; }
        .items-container::-webkit-scrollbar-track { background: transparent; }
        .items-container::-webkit-scrollbar-thumb { background: var(--bg-tertiary); border-radius: 3px; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table thead th {
            background: var(--bg-tertiary);
            padding: 10px 12px;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            position: sticky;
            top: 0;
            z-index: 2;
            font-weight: 600;
        }
        .items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(71,85,105,0.2);
            font-size: 0.95rem;
            vertical-align: middle;
        }
        .items-table tbody tr {
            transition: background 0.15s;
        }
        .items-table tbody tr:hover { background: rgba(59,130,246,0.06); }
        .items-table tbody tr.selected { background: rgba(59,130,246,0.12); }
        .items-table .col-seq { width: 40px; text-align: center; color: var(--text-muted); }
        .items-table .col-code { width: 90px; color: var(--text-muted); font-size: 0.82rem; }
        .items-table .col-desc { }
        .items-table .col-qty { width: 120px; text-align: center; }
        .items-table .col-price { width: 100px; text-align: right; }
        .items-table .col-total { width: 110px; text-align: right; font-weight: 700; color: var(--accent-green); }
        .items-table .col-actions { width: 50px; text-align: center; }

        .qty-control {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            background: var(--bg-primary);
            border-radius: 8px;
            padding: 2px;
        }
        .qty-control button {
            width: 30px;
            height: 30px;
            border: none;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }
        .qty-control button:hover { background: var(--accent-blue); }
        .qty-control input {
            width: 50px;
            text-align: center;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1rem;
        }
        .qty-control input:focus { outline: none; }

        .btn-remove-item {
            background: none;
            border: none;
            color: var(--accent-red);
            cursor: pointer;
            font-size: 1.1rem;
            padding: 4px;
            border-radius: 6px;
            transition: all 0.15s;
        }
        .btn-remove-item:hover { background: rgba(239,68,68,0.15); }

        .items-empty {
            padding: 60px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        .items-empty i { font-size: 3rem; display: block; margin-bottom: 12px; opacity: 0.3; }
        .items-empty p { font-size: 1rem; }
        .items-empty .shortcut { font-size: 0.82rem; margin-top: 8px; }
        .items-empty kbd {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.78rem;
            border: 1px solid var(--border);
        }

        /* ===== RIGHT PANEL (35%) ===== */
        .pdv-right {
            flex: 0 0 35%;
            display: flex;
            flex-direction: column;
            padding: 12px;
            background: var(--bg-secondary);
            border-left: 1px solid var(--border);
            overflow-y: auto;
        }

        /* Cliente section */
        .cliente-section {
            margin-bottom: 10px;
        }
        .cliente-display {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            min-height: 40px;
        }
        .cliente-display .cliente-name {
            color: var(--text-primary);
            font-weight: 500;
        }
        .cliente-display .cliente-doc {
            color: var(--text-muted);
            font-size: 0.78rem;
        }
        .cliente-display .btn-cliente-clear {
            background: none;
            border: none;
            color: var(--accent-red);
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .cliente-display .btn-cliente-clear:hover { background: rgba(239,68,68,0.15); }
        .no-cliente { color: var(--text-muted); font-style: italic; }

        /* Summary */
        .summary-section {
            background: var(--bg-primary);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
            border: 1px solid var(--border);
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        .summary-row .summary-val { color: var(--text-primary); font-weight: 600; }
        .summary-row.discount .summary-val { color: var(--accent-red); }
        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0 4px;
            margin-top: 8px;
            border-top: 2px solid var(--border);
        }
        .summary-total .label { font-size: 1.1rem; font-weight: 700; color: var(--text-secondary); }
        .summary-total .amount {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--accent-green);
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .items-count {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-align: right;
            margin-top: 2px;
        }

        /* Payment buttons */
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 10px;
        }
        .btn-pay {
            padding: 12px 8px;
            border: 2px solid var(--border);
            background: var(--bg-primary);
            color: var(--text-secondary);
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.15s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .btn-pay i { font-size: 1.4rem; }
        .btn-pay:hover {
            border-color: var(--accent-blue);
            background: rgba(59,130,246,0.08);
            color: var(--text-primary);
        }
        .btn-pay.active {
            border-color: var(--accent-blue);
            background: rgba(59,130,246,0.15);
            color: var(--accent-blue);
        }
        .btn-pay.pay-dinheiro i { color: var(--accent-green); }
        .btn-pay.pay-credito i { color: var(--accent-yellow); }
        .btn-pay.pay-debito i { color: var(--accent-cyan); }
        .btn-pay.pay-pix i { color: var(--accent-purple); }

        /* Split payments list */
        .split-payments {
            margin-bottom: 10px;
        }
        .split-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 10px;
            background: var(--bg-primary);
            border-radius: 6px;
            margin-bottom: 4px;
            font-size: 0.85rem;
        }
        .split-item .split-forma {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        .split-item .split-valor { font-weight: 700; color: var(--accent-green); }
        .split-item .btn-split-remove {
            background: none;
            border: none;
            color: var(--accent-red);
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0 4px;
        }
        .split-remaining {
            font-size: 0.82rem;
            color: var(--accent-yellow);
            text-align: center;
            padding: 4px;
            font-weight: 600;
        }

        /* Troco */
        .troco-display {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.3);
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            margin-bottom: 10px;
            display: none;
        }
        .troco-display .troco-label { font-size: 0.78rem; color: var(--text-muted); }
        .troco-display .troco-value { font-size: 1.6rem; font-weight: 800; color: var(--accent-green); }

        /* Finalizar button */
        .btn-finalizar {
            width: 100%;
            padding: 16px;
            background: var(--accent-green);
            color: #000;
            border: none;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-finalizar:hover { background: #16a34a; transform: translateY(-1px); box-shadow: 0 4px 15px rgba(34,197,94,0.3); }
        .btn-finalizar:disabled { background: var(--bg-tertiary); color: var(--text-muted); cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-finalizar kbd {
            background: rgba(0,0,0,0.2);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        /* Action buttons row */
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .btn-action {
            flex: 1;
            min-width: 70px;
            padding: 8px 4px;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.68rem;
            text-align: center;
            transition: all 0.15s;
            font-weight: 500;
        }
        .btn-action:hover { background: var(--bg-tertiary); color: var(--text-primary); border-color: var(--accent-blue); }
        .btn-action kbd {
            display: block;
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .btn-action.danger:hover { border-color: var(--accent-red); color: var(--accent-red); }

        /* ===== BOTTOM BAR ===== */
        .pdv-bottombar {
            background: var(--bg-secondary);
            height: 36px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 16px;
            font-size: 0.7rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
            user-select: none;
        }
        .pdv-bottombar kbd {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 0.65rem;
            border: 1px solid var(--border);
        }

        /* ===== NO CAIXA OVERLAY ===== */
        .no-caixa-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .no-caixa-box {
            background: var(--bg-secondary);
            padding: 48px;
            border-radius: 20px;
            text-align: center;
            border: 1px solid var(--border);
            max-width: 440px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .no-caixa-box i { font-size: 4rem; color: var(--accent-yellow); display: block; margin-bottom: 16px; }
        .no-caixa-box h3 { font-size: 1.4rem; margin-bottom: 8px; }
        .no-caixa-box p { color: var(--text-secondary); margin-bottom: 24px; }
        .no-caixa-box .btn-open-caixa {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            background: var(--accent-blue);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
        }
        .no-caixa-box .btn-open-caixa:hover { background: #2563eb; transform: translateY(-1px); }
        .no-caixa-box .btn-back {
            display: block;
            margin-top: 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.88rem;
        }
        .no-caixa-box .btn-back:hover { color: var(--text-secondary); }

        /* ===== MODALS (dark theme) ===== */
        .modal-content {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 16px 20px;
        }
        .modal-body { padding: 20px; }
        .modal-footer { border-top: 1px solid var(--border); padding: 12px 20px; }
        .modal .form-control, .modal .form-select {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: 8px;
        }
        .modal .form-control:focus, .modal .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .modal .form-label { color: var(--text-secondary); font-weight: 500; font-size: 0.88rem; }
        .btn-close { filter: invert(1) grayscale(1) brightness(2); }

        .modal-valor-input {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            padding: 16px;
        }

        .modal-btn-primary {
            background: var(--accent-blue);
            border: none;
            color: #fff;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .modal-btn-primary:hover { background: #2563eb; }
        .modal-btn-secondary {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
        }
        .modal-btn-secondary:hover { background: var(--border); color: var(--text-primary); }
        .modal-btn-danger {
            background: var(--accent-red);
            border: none;
            color: #fff;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .modal-btn-danger:hover { background: #dc2626; }

        .modal-btn-green {
            background: var(--accent-green);
            border: none;
            color: #000;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }
        .modal-btn-green:hover { background: #16a34a; }

        /* Client search in modal */
        .client-search-results {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 4px;
        }
        .client-search-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid rgba(71,85,105,0.2);
            transition: background 0.1s;
        }
        .client-search-item:hover { background: var(--bg-tertiary); }

        /* Alerts */
        .pdv-alert {
            position: fixed;
            top: 60px;
            right: 16px;
            z-index: 10000;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }
        .pdv-alert.success { background: rgba(34,197,94,0.15); border: 1px solid var(--accent-green); color: var(--accent-green); }
        .pdv-alert.error { background: rgba(239,68,68,0.15); border: 1px solid var(--accent-red); color: var(--accent-red); }
        .pdv-alert.warning { background: rgba(234,179,8,0.15); border: 1px solid var(--accent-yellow); color: var(--accent-yellow); }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Print receipt iframe */
        #printFrame { display: none; }

        /* Loading spinner */
        .spinner-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999;
        }
        .spinner-overlay.show { display: flex; }
        .spinner-box {
            text-align: center;
            color: var(--text-primary);
        }
        .spinner-box .spinner-border { width: 3rem; height: 3rem; }
        .spinner-box p { margin-top: 12px; font-weight: 600; }
    </style>
</head>
<body>

{{-- ===== TOP BAR ===== --}}
<div class="pdv-topbar">
    <div class="topbar-brand">
        <i class="bi bi-cart3"></i>
        <span>PDV</span>
    </div>
    <div class="topbar-info">
        <span><span class="label">Unidade:</span> <span class="value">{{ $unidade->nome ?? '-' }}</span></span>
        <span><span class="label">Operador:</span> <span class="value">{{ auth()->user()->name }}</span></span>
        @if($caixa)
            <span><span class="label">Caixa:</span> <span class="value">#{{ $caixa->numero_caixa }}</span></span>
        @endif
    </div>
    <div class="topbar-spacer"></div>

    {{-- Fiscal Indicator --}}
    @if($configFiscal && $configFiscal->emissao_fiscal_ativa && $configFiscal->tipo_cupom_pdv === 'fiscal')
        <div class="topbar-fiscal fiscal-on"><span class="dot"></span> NFC-e Ativa</div>
    @else
        <div class="topbar-fiscal fiscal-off"><span class="dot"></span> Nao Fiscal</div>
    @endif

    <div class="topbar-clock" id="clock">--:--:--</div>
</div>

{{-- ===== NO CAIXA OVERLAY ===== --}}
@if(!$caixa)
<div class="no-caixa-overlay" id="noCaixaOverlay">
    <div class="no-caixa-box">
        <i class="bi bi-lock"></i>
        <h3>Caixa Fechado</h3>
        <p>E necessario abrir o caixa para iniciar as vendas.</p>
        <a href="{{ route('app.caixa.abrir') }}" class="btn-open-caixa">
            <i class="bi bi-unlock"></i> Abrir Caixa
        </a>
        <a href="{{ route('app.dashboard') }}" class="btn-back">
            <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
        </a>
    </div>
</div>
@endif

{{-- ===== MAIN CONTENT ===== --}}
<div class="pdv-main">
    {{-- LEFT PANEL --}}
    <div class="pdv-left">
        <div class="search-container">
            <div class="search-input-wrap">
                <i class="bi bi-upc-scan"></i>
                <input type="text" id="searchInput"
                    placeholder="Buscar produto por codigo de barras, codigo interno ou descricao..."
                    autocomplete="off" {{ !$caixa ? 'disabled' : '' }}>
                <div class="search-results-dropdown" id="searchDropdown"></div>
            </div>
        </div>

        <div class="items-container">
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-seq">#</th>
                        <th class="col-code">CODIGO</th>
                        <th class="col-desc">DESCRICAO</th>
                        <th class="col-qty">QTD</th>
                        <th class="col-price">UNITARIO</th>
                        <th class="col-total">TOTAL</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                </tbody>
            </table>
            <div class="items-empty" id="itemsEmpty">
                <i class="bi bi-cart-x"></i>
                <p>Nenhum item adicionado</p>
                <div class="shortcut">Pressione <kbd>F1</kbd> ou leia um codigo de barras para iniciar</div>
            </div>
        </div>
    </div>

    {{-- RIGHT PANEL --}}
    <div class="pdv-right">
        {{-- Cliente --}}
        <div class="cliente-section">
            <div class="cliente-display" id="clienteDisplay">
                <span class="no-cliente" id="noCliente">
                    <i class="bi bi-person-plus me-1"></i> Sem cliente (F2)
                </span>
                <span class="cliente-name" id="clienteName" style="display:none;"></span>
                <span class="cliente-doc" id="clienteDoc" style="display:none;"></span>
                <button class="btn-cliente-clear" id="clienteClear" style="display:none;" title="Remover cliente">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        {{-- Vendedor --}}
        @if(isset($operadores) && $operadores->count() > 0)
        <div class="cliente-section">
            <select id="vendedorSelect" class="form-select" style="background:var(--bg-primary); border:1px solid var(--border); color:var(--text-primary); border-radius:8px; font-size:0.85rem; padding:8px 12px;">
                <option value="">Vendedor: {{ auth()->user()->name }} (padrao)</option>
                @foreach($operadores as $op)
                    <option value="{{ $op->id }}">{{ $op->name }} ({{ $op->perfil }})</option>
                @endforeach
            </select>
        </div>
        @endif

        {{-- Summary --}}
        <div class="summary-section">
            <div class="summary-row">
                <span>Subtotal</span>
                <span class="summary-val" id="summarySubtotal">R$ 0,00</span>
            </div>
            <div class="summary-row discount" id="discountRow" style="display:none;">
                <span>Desconto</span>
                <span class="summary-val" id="summaryDiscount">- R$ 0,00</span>
            </div>
            <div class="summary-total">
                <span class="label">TOTAL</span>
                <span class="amount" id="summaryTotal">R$ 0,00</span>
            </div>
            <div class="items-count" id="itemsCount">0 itens</div>
        </div>

        {{-- Payment buttons --}}
        <div class="payment-grid">
            <button class="btn-pay pay-dinheiro" data-forma="dinheiro" title="Dinheiro">
                <i class="bi bi-cash-stack"></i> Dinheiro
            </button>
            <button class="btn-pay pay-credito" data-forma="cartao_credito" title="Cartao Credito">
                <i class="bi bi-credit-card"></i> Credito
            </button>
            <button class="btn-pay pay-debito" data-forma="cartao_debito" title="Cartao Debito">
                <i class="bi bi-credit-card-2-front"></i> Debito
            </button>
            <button class="btn-pay pay-pix" data-forma="pix" title="PIX">
                <i class="bi bi-qr-code"></i> PIX
            </button>
        </div>

        {{-- Split payments --}}
        <div class="split-payments" id="splitPayments" style="display:none;">
            <div id="splitList"></div>
            <div class="split-remaining" id="splitRemaining"></div>
        </div>

        {{-- Troco --}}
        <div class="troco-display" id="trocoDisplay">
            <div class="troco-label">TROCO</div>
            <div class="troco-value" id="trocoValue">R$ 0,00</div>
        </div>

        {{-- Action buttons --}}
        <div class="action-buttons">
            <button class="btn-action" onclick="PDV.openDesconto()" title="Desconto geral">
                <i class="bi bi-percent"></i> Desconto
                <kbd>F4</kbd>
            </button>
            <button class="btn-action" onclick="PDV.openSangria()" title="Sangria">
                <i class="bi bi-arrow-down-circle"></i> Sangria
                <kbd>F7</kbd>
            </button>
            <button class="btn-action" onclick="PDV.openSuprimento()" title="Suprimento">
                <i class="bi bi-arrow-up-circle"></i> Suprim.
                <kbd>F8</kbd>
            </button>
            <button class="btn-action danger" onclick="PDV.cancelarItem()" title="Cancelar item selecionado">
                <i class="bi bi-x-circle"></i> Cancelar
                <kbd>F9</kbd>
            </button>
        </div>

        {{-- Finalizar --}}
        <button class="btn-finalizar" id="btnFinalizar" disabled onclick="PDV.finalizarVenda()">
            <i class="bi bi-check-circle"></i> FINALIZAR VENDA <kbd>F12</kbd>
        </button>
    </div>
</div>

{{-- ===== BOTTOM BAR ===== --}}
<div class="pdv-bottombar">
    <span><kbd>F1</kbd> Buscar</span>
    <span><kbd>F2</kbd> Cliente</span>
    <span><kbd>F4</kbd> Desconto</span>
    <span><kbd>F7</kbd> Sangria</span>
    <span><kbd>F8</kbd> Suprimento</span>
    <span><kbd>F9</kbd> Cancelar Item</span>
    <span><kbd>F10</kbd> Fechar Caixa</span>
    <span><kbd>F12</kbd> Finalizar</span>
    <div style="flex:1"></div>
    <span><kbd>ESC</kbd> Limpar/Fechar</span>
</div>

{{-- ===== MODALS ===== --}}

{{-- Modal: Pagamento (valor input for dinheiro) --}}
<div class="modal fade" id="modalPagamento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPagamentoTitle">Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Forma de Pagamento</label>
                    <div class="fw-bold fs-5" id="modalPagamentoForma"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Valor Total da Venda</label>
                    <div class="fw-bold fs-5 text-success" id="modalPagamentoTotal"></div>
                </div>
                <div class="mb-3" id="valorRecebidoWrap">
                    <label class="form-label">Valor Recebido</label>
                    <input type="number" class="form-control modal-valor-input" id="valorRecebido"
                        step="0.01" min="0" placeholder="0,00">
                </div>
                <div id="modalTrocoWrap" style="display:none;" class="text-center mt-3">
                    <div style="color:var(--text-muted); font-size:0.85rem;">TROCO</div>
                    <div style="font-size:2rem; font-weight:800; color:var(--accent-green);" id="modalTroco">R$ 0,00</div>
                </div>
                <div class="form-check mt-3" id="splitCheck">
                    <input class="form-check-input" type="checkbox" id="isSplitPayment">
                    <label class="form-check-label" for="isSplitPayment" style="color:var(--text-secondary);">
                        Pagamento dividido (split)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="modal-btn-green" id="btnConfirmarPagamento" onclick="PDV.confirmarPagamento()">
                    <i class="bi bi-check-lg"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Cliente --}}
<div class="modal fade" id="modalCliente" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Selecionar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control" id="clienteSearchInput"
                    placeholder="Buscar por nome ou CPF/CNPJ..." autocomplete="off">
                <div class="client-search-results" id="clienteSearchResults"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Desconto --}}
<div class="modal fade" id="modalDesconto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-percent me-2"></i>Desconto na Venda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tipo de Desconto</label>
                    <select class="form-select" id="descontoTipo">
                        <option value="valor">Valor (R$)</option>
                        <option value="percentual">Percentual (%)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Valor</label>
                    <input type="number" class="form-control modal-valor-input" id="descontoInput"
                        step="0.01" min="0" placeholder="0,00">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="modal-btn-primary" onclick="PDV.aplicarDesconto()">Aplicar</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Sangria --}}
<div class="modal fade" id="modalSangria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-down-circle me-2" style="color:var(--accent-red)"></i>Sangria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Valor</label>
                    <input type="number" class="form-control modal-valor-input" id="sangriaValor"
                        step="0.01" min="0.01" placeholder="0,00">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descricao / Motivo</label>
                    <input type="text" class="form-control" id="sangriaDescricao" placeholder="Ex: Pagamento fornecedor">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="modal-btn-danger" onclick="PDV.enviarSangria()">
                    <i class="bi bi-arrow-down-circle"></i> Registrar Sangria
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Suprimento --}}
<div class="modal fade" id="modalSuprimento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2" style="color:var(--accent-cyan)"></i>Suprimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Valor</label>
                    <input type="number" class="form-control modal-valor-input" id="suprimentoValor"
                        step="0.01" min="0.01" placeholder="0,00">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descricao / Motivo</label>
                    <input type="text" class="form-control" id="suprimentoDescricao" placeholder="Ex: Troco adicional">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="modal-btn-primary" onclick="PDV.enviarSuprimento()">
                    <i class="bi bi-arrow-up-circle"></i> Registrar Suprimento
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Sucesso venda --}}
<div class="modal fade" id="modalSucesso" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="text-align:center;">
            <div class="modal-body" style="padding:40px;">
                <i class="bi bi-check-circle" style="font-size:4rem; color:var(--accent-green); display:block; margin-bottom:16px;"></i>
                <h3 style="margin-bottom:8px;">Venda Finalizada!</h3>
                <div style="font-size:0.95rem; color:var(--text-secondary); margin-bottom:4px;">Venda #<span id="sucessoNumero"></span></div>
                <div style="font-size:2rem; font-weight:800; color:var(--accent-green); margin-bottom:20px;" id="sucessoTotal"></div>
                <div id="sucessoTroco" style="display:none; margin-bottom:16px;">
                    <div style="font-size:0.85rem; color:var(--text-muted);">TROCO</div>
                    <div style="font-size:1.5rem; font-weight:700; color:var(--accent-yellow);" id="sucessoTrocoValor"></div>
                </div>
                <div class="d-flex gap-2 justify-content-center">
                    <button class="modal-btn-primary" onclick="PDV.imprimirCupom()">
                        <i class="bi bi-printer"></i> Imprimir Cupom
                    </button>
                    <button class="modal-btn-green" onclick="PDV.novaVenda()">
                        <i class="bi bi-plus-lg"></i> Nova Venda
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Loading overlay --}}
<div class="spinner-overlay" id="loadingOverlay">
    <div class="spinner-box">
        <div class="spinner-border text-primary" role="status"></div>
        <p>Processando venda...</p>
    </div>
</div>

{{-- Print frame --}}
<iframe id="printFrame"></iframe>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const PDV = {
    itens: [],
    clienteId: null,
    clienteNome: null,
    descontoValor: 0,
    descontoPercentual: 0,
    pagamentos: [],
    pagamentoAtual: null,
    selectedItemIndex: -1,
    barcodeBuffer: '',
    barcodeTimeout: null,
    lastCupomHtml: '',
    searchTimeout: null,

    // ===== INIT =====
    init() {
        this.updateClock();
        setInterval(() => this.updateClock(), 1000);
        this.bindKeyboardShortcuts();
        this.bindSearchInput();
        this.bindPaymentButtons();
        this.bindBarcodeDetection();
        this.renderItems();
    },

    updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('clock').textContent = `${h}:${m}:${s}`;
    },

    // ===== FORMATTING =====
    formatMoney(val) {
        return 'R$ ' + parseFloat(val || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    },

    // ===== KEYBOARD SHORTCUTS =====
    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Don't intercept when typing in inputs (except F-keys)
            const inInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName);

            switch(e.key) {
                case 'F1':
                    e.preventDefault();
                    document.getElementById('searchInput').focus();
                    break;
                case 'F2':
                    e.preventDefault();
                    this.openCliente();
                    break;
                case 'F4':
                    e.preventDefault();
                    this.openDesconto();
                    break;
                case 'F7':
                    e.preventDefault();
                    this.openSangria();
                    break;
                case 'F8':
                    e.preventDefault();
                    this.openSuprimento();
                    break;
                case 'F9':
                    e.preventDefault();
                    this.cancelarItem();
                    break;
                case 'F10':
                    e.preventDefault();
                    window.location.href = '{{ route("app.caixa.fechar") }}';
                    break;
                case 'F12':
                    e.preventDefault();
                    this.finalizarVenda();
                    break;
                case 'Escape':
                    e.preventDefault();
                    this.closeDropdown();
                    // Close any open modal
                    const openModal = document.querySelector('.modal.show');
                    if (openModal) {
                        bootstrap.Modal.getInstance(openModal)?.hide();
                    }
                    break;
                case 'ArrowUp':
                    if (!inInput && this.itens.length > 0) {
                        e.preventDefault();
                        this.selectedItemIndex = Math.max(0, this.selectedItemIndex - 1);
                        this.highlightItem();
                    }
                    break;
                case 'ArrowDown':
                    if (!inInput && this.itens.length > 0) {
                        e.preventDefault();
                        this.selectedItemIndex = Math.min(this.itens.length - 1, this.selectedItemIndex + 1);
                        this.highlightItem();
                    }
                    break;
                case 'Delete':
                    if (!inInput && this.selectedItemIndex >= 0) {
                        e.preventDefault();
                        this.removeItem(this.selectedItemIndex);
                    }
                    break;
            }
        });
    },

    highlightItem() {
        document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
            tr.classList.toggle('selected', i === this.selectedItemIndex);
        });
    },

    // ===== BARCODE DETECTION =====
    bindBarcodeDetection() {
        document.addEventListener('keypress', (e) => {
            const inInput = e.target === document.getElementById('searchInput');
            if (e.target.tagName === 'INPUT' && !inInput) return;

            // Barcode scanners type fast
            if (this.barcodeTimeout) clearTimeout(this.barcodeTimeout);

            if (e.key === 'Enter' && this.barcodeBuffer.length >= 4) {
                const code = this.barcodeBuffer;
                this.barcodeBuffer = '';
                this.searchAndAddByCode(code);
                if (inInput) {
                    document.getElementById('searchInput').value = '';
                }
                return;
            }

            if (e.key.length === 1) {
                this.barcodeBuffer += e.key;
            }

            this.barcodeTimeout = setTimeout(() => {
                this.barcodeBuffer = '';
            }, 100);
        });
    },

    async searchAndAddByCode(code) {
        try {
            const resp = await fetch(`/app/pdv/produto/${encodeURIComponent(code)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const produtos = await resp.json();
            if (produtos.length === 1) {
                this.addProduto(produtos[0]);
            } else if (produtos.length > 1) {
                document.getElementById('searchInput').value = code;
                this.showSearchResults(produtos);
            } else {
                this.showAlert('Produto nao encontrado: ' + code, 'warning');
            }
        } catch (err) {
            this.showAlert('Erro ao buscar produto', 'error');
        }
    },

    // ===== SEARCH =====
    bindSearchInput() {
        const input = document.getElementById('searchInput');
        input.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            if (this.searchTimeout) clearTimeout(this.searchTimeout);
            if (val.length < 2) {
                this.closeDropdown();
                return;
            }
            this.searchTimeout = setTimeout(() => this.searchProdutos(val), 300);
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = input.value.trim();
                if (val.length >= 1) {
                    this.searchAndAddByCode(val);
                    input.value = '';
                    this.closeDropdown();
                }
            }
            if (e.key === 'Escape') {
                input.value = '';
                this.closeDropdown();
            }
        });

        // Close dropdown on click outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-input-wrap')) {
                this.closeDropdown();
            }
        });
    },

    async searchProdutos(term) {
        try {
            const resp = await fetch(`/app/pdv/produto/${encodeURIComponent(term)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const produtos = await resp.json();
            this.showSearchResults(produtos);
        } catch (err) {
            console.error('Search error:', err);
        }
    },

    showSearchResults(produtos) {
        const dropdown = document.getElementById('searchDropdown');
        if (produtos.length === 0) {
            dropdown.innerHTML = '<div style="padding:16px; text-align:center; color:var(--text-muted);">Nenhum produto encontrado</div>';
            dropdown.classList.add('show');
            return;
        }

        dropdown.innerHTML = produtos.map(p => `
            <div class="search-result-item" onclick="PDV.addProdutoById(${p.id}, '${(p.descricao||'').replace(/'/g, "\\'")}', ${p.preco_venda}, '${p.codigo_interno||''}', '${p.codigo_barras||''}')">
                <div>
                    <div class="prod-name">${p.descricao}</div>
                    <div class="prod-code">${p.codigo_interno || ''} ${p.codigo_barras ? '| ' + p.codigo_barras : ''}</div>
                </div>
                <div class="prod-price">${this.formatMoney(p.preco_venda)}</div>
            </div>
        `).join('');
        dropdown.classList.add('show');
    },

    closeDropdown() {
        document.getElementById('searchDropdown').classList.remove('show');
    },

    // ===== ADD ITEMS =====
    addProdutoById(id, descricao, preco, codigoInterno, codigoBarras) {
        this.addProduto({ id, descricao, preco_venda: preco, codigo_interno: codigoInterno, codigo_barras: codigoBarras });
        document.getElementById('searchInput').value = '';
        this.closeDropdown();
        document.getElementById('searchInput').focus();
    },

    async addProduto(produto) {
        // Check stock before adding
        try {
            const estoqueResp = await fetch(`/app/pdv/estoque/${produto.id}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const estoqueData = await estoqueResp.json();
            if (estoqueData.estoque_atual <= 0) {
                this.showAlert('Sem estoque para: ' + produto.descricao, 'warning');
            }
        } catch (err) {
            // Continue even if stock check fails
        }

        // Check if product already exists, increment qty
        const existing = this.itens.find(i => i.produto_id === produto.id);
        if (existing) {
            existing.quantidade += 1;
            existing.total = round((existing.preco_unitario * existing.quantidade) - existing.desconto_valor, 2);
        } else {
            this.itens.push({
                produto_id: produto.id,
                descricao: produto.descricao,
                codigo_interno: produto.codigo_interno || '',
                codigo_barras: produto.codigo_barras || '',
                preco_unitario: parseFloat(produto.preco_venda),
                quantidade: 1,
                desconto_valor: 0,
                total: parseFloat(produto.preco_venda),
            });
        }
        this.selectedItemIndex = this.itens.length - 1;
        this.renderItems();
        this.updateSummary();
    },

    // ===== RENDER ITEMS =====
    renderItems() {
        const tbody = document.getElementById('itemsBody');
        const empty = document.getElementById('itemsEmpty');

        if (this.itens.length === 0) {
            tbody.innerHTML = '';
            empty.style.display = 'block';
            return;
        }

        empty.style.display = 'none';
        tbody.innerHTML = this.itens.map((item, idx) => `
            <tr class="${idx === this.selectedItemIndex ? 'selected' : ''}" onclick="PDV.selectItem(${idx})">
                <td class="col-seq">${idx + 1}</td>
                <td class="col-code">${item.codigo_interno || item.codigo_barras || '-'}</td>
                <td class="col-desc">${item.descricao}</td>
                <td class="col-qty">
                    <div class="qty-control">
                        <button onclick="event.stopPropagation(); PDV.changeQty(${idx}, -1)">-</button>
                        <input type="number" value="${item.quantidade}" step="1" min="0.001"
                            onchange="PDV.setQty(${idx}, this.value)" onclick="event.stopPropagation(); this.select()">
                        <button onclick="event.stopPropagation(); PDV.changeQty(${idx}, 1)">+</button>
                    </div>
                </td>
                <td class="col-price">${this.formatMoney(item.preco_unitario)}</td>
                <td class="col-total">${this.formatMoney(item.total)}</td>
                <td class="col-actions">
                    <button class="btn-remove-item" onclick="event.stopPropagation(); PDV.removeItem(${idx})" title="Remover item">
                        <i class="bi bi-trash3"></i>
                    </button>
                </td>
            </tr>
        `).join('');

        // Auto-scroll to last item
        const container = document.querySelector('.items-container');
        container.scrollTop = container.scrollHeight;
    },

    selectItem(idx) {
        this.selectedItemIndex = idx;
        this.highlightItem();
    },

    changeQty(idx, delta) {
        const item = this.itens[idx];
        item.quantidade = Math.max(0.001, round(item.quantidade + delta, 3));
        item.total = round((item.preco_unitario * item.quantidade) - item.desconto_valor, 2);
        this.renderItems();
        this.updateSummary();
    },

    setQty(idx, val) {
        const item = this.itens[idx];
        const qty = parseFloat(val);
        if (isNaN(qty) || qty <= 0) return;
        item.quantidade = round(qty, 3);
        item.total = round((item.preco_unitario * item.quantidade) - item.desconto_valor, 2);
        this.renderItems();
        this.updateSummary();
    },

    removeItem(idx) {
        this.itens.splice(idx, 1);
        if (this.selectedItemIndex >= this.itens.length) {
            this.selectedItemIndex = this.itens.length - 1;
        }
        this.renderItems();
        this.updateSummary();
    },

    cancelarItem() {
        if (this.selectedItemIndex >= 0 && this.selectedItemIndex < this.itens.length) {
            this.removeItem(this.selectedItemIndex);
        }
    },

    // ===== SUMMARY =====
    updateSummary() {
        const subtotal = this.itens.reduce((sum, i) => sum + i.total, 0);
        let desconto = this.descontoValor;
        if (this.descontoPercentual > 0) {
            desconto = round(subtotal * (this.descontoPercentual / 100), 2);
        }
        const total = Math.max(0, round(subtotal - desconto, 2));

        document.getElementById('summarySubtotal').textContent = this.formatMoney(subtotal);
        document.getElementById('summaryTotal').textContent = this.formatMoney(total);
        document.getElementById('itemsCount').textContent = this.itens.length + (this.itens.length === 1 ? ' item' : ' itens');

        const discountRow = document.getElementById('discountRow');
        if (desconto > 0) {
            discountRow.style.display = 'flex';
            document.getElementById('summaryDiscount').textContent = '- ' + this.formatMoney(desconto);
        } else {
            discountRow.style.display = 'none';
        }

        const btn = document.getElementById('btnFinalizar');
        btn.disabled = this.itens.length === 0;
    },

    getSubtotal() {
        return this.itens.reduce((sum, i) => sum + i.total, 0);
    },

    getDesconto() {
        if (this.descontoPercentual > 0) {
            return round(this.getSubtotal() * (this.descontoPercentual / 100), 2);
        }
        return this.descontoValor;
    },

    getTotal() {
        return Math.max(0, round(this.getSubtotal() - this.getDesconto(), 2));
    },

    // ===== PAYMENT =====
    bindPaymentButtons() {
        document.querySelectorAll('.btn-pay').forEach(btn => {
            btn.addEventListener('click', () => {
                const forma = btn.dataset.forma;
                this.openPagamento(forma);
            });
        });
    },

    openPagamento(forma) {
        if (this.itens.length === 0) {
            this.showAlert('Adicione itens antes de selecionar pagamento', 'warning');
            return;
        }

        const total = this.getTotal();
        const jaAdicionado = this.pagamentos.reduce((s, p) => s + p.valor, 0);
        const restante = round(total - jaAdicionado, 2);

        if (restante <= 0 && this.pagamentos.length > 0) {
            this.showAlert('Pagamento ja completo. Finalize a venda.', 'warning');
            return;
        }

        this.pagamentoAtual = forma;

        const formaLabels = {
            'dinheiro': 'Dinheiro',
            'cartao_credito': 'Cartao de Credito',
            'cartao_debito': 'Cartao de Debito',
            'pix': 'PIX'
        };

        document.getElementById('modalPagamentoTitle').textContent = 'Pagamento - ' + (formaLabels[forma] || forma);
        document.getElementById('modalPagamentoForma').textContent = formaLabels[forma] || forma;
        document.getElementById('modalPagamentoTotal').textContent = this.formatMoney(this.pagamentos.length > 0 ? restante : total);

        const valorInput = document.getElementById('valorRecebido');

        // For dinheiro, show valor recebido field for troco calc
        // For others, auto-fill the remaining
        if (forma === 'dinheiro') {
            document.getElementById('valorRecebidoWrap').style.display = 'block';
            valorInput.value = '';
            document.getElementById('modalTrocoWrap').style.display = 'none';
        } else {
            document.getElementById('valorRecebidoWrap').style.display = 'block';
            valorInput.value = (this.pagamentos.length > 0 ? restante : total).toFixed(2);
            document.getElementById('modalTrocoWrap').style.display = 'none';
        }

        // Show/hide split checkbox
        document.getElementById('splitCheck').style.display = this.pagamentos.length === 0 ? 'block' : 'none';
        document.getElementById('isSplitPayment').checked = this.pagamentos.length > 0;

        // Troco calculation for dinheiro
        valorInput.oninput = () => {
            if (forma === 'dinheiro') {
                const recebido = parseFloat(valorInput.value) || 0;
                const valorEsperado = this.pagamentos.length > 0 ? restante : total;
                const troco = recebido - valorEsperado;
                if (troco > 0) {
                    document.getElementById('modalTrocoWrap').style.display = 'block';
                    document.getElementById('modalTroco').textContent = this.formatMoney(troco);
                } else {
                    document.getElementById('modalTrocoWrap').style.display = 'none';
                }
            }
        };

        const modal = new bootstrap.Modal(document.getElementById('modalPagamento'));
        modal.show();

        setTimeout(() => valorInput.focus(), 300);
    },

    confirmarPagamento() {
        const forma = this.pagamentoAtual;
        const valorInput = document.getElementById('valorRecebido');
        const valor = parseFloat(valorInput.value) || 0;
        const isSplit = document.getElementById('isSplitPayment').checked || this.pagamentos.length > 0;
        const total = this.getTotal();
        const jaAdicionado = this.pagamentos.reduce((s, p) => s + p.valor, 0);
        const restante = round(total - jaAdicionado, 2);

        if (valor <= 0) {
            this.showAlert('Informe o valor do pagamento', 'warning');
            return;
        }

        // For dinheiro, accept any value >= total for troco
        // For other methods, value should not exceed remaining
        if (forma !== 'dinheiro' && valor > restante + 0.01) {
            this.showAlert('Valor excede o restante da venda', 'warning');
            return;
        }

        if (isSplit) {
            // Split payment - add to list
            this.pagamentos.push({ forma, valor: Math.min(valor, restante) });
            this.renderSplitPayments();

            const novoRestante = round(total - this.pagamentos.reduce((s, p) => s + p.valor, 0), 2);
            if (novoRestante <= 0.01) {
                // All paid, proceed to finalize
                bootstrap.Modal.getInstance(document.getElementById('modalPagamento'))?.hide();
                this.finalizarVenda();
            } else {
                bootstrap.Modal.getInstance(document.getElementById('modalPagamento'))?.hide();
                this.showAlert(`Pagamento adicionado. Restam ${this.formatMoney(novoRestante)}`, 'warning');
            }
        } else {
            // Single payment
            this.pagamentos = [{ forma, valor }];
            bootstrap.Modal.getInstance(document.getElementById('modalPagamento'))?.hide();

            // Calculate troco for display
            if (forma === 'dinheiro' && valor > total) {
                const troco = round(valor - total, 2);
                document.getElementById('trocoDisplay').style.display = 'block';
                document.getElementById('trocoValue').textContent = this.formatMoney(troco);
            } else {
                document.getElementById('trocoDisplay').style.display = 'none';
            }

            this.renderSplitPayments();
            this.finalizarVenda();
        }
    },

    renderSplitPayments() {
        const container = document.getElementById('splitPayments');
        const list = document.getElementById('splitList');
        const remaining = document.getElementById('splitRemaining');

        if (this.pagamentos.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        const formaLabels = {
            'dinheiro': 'Dinheiro', 'cartao_credito': 'Credito',
            'cartao_debito': 'Debito', 'pix': 'PIX'
        };

        list.innerHTML = this.pagamentos.map((p, idx) => `
            <div class="split-item">
                <span class="split-forma">
                    <i class="bi bi-check-circle" style="color:var(--accent-green)"></i>
                    ${formaLabels[p.forma] || p.forma}
                </span>
                <span>
                    <span class="split-valor">${this.formatMoney(p.valor)}</span>
                    <button class="btn-split-remove" onclick="PDV.removeSplitPayment(${idx})" title="Remover">
                        <i class="bi bi-x"></i>
                    </button>
                </span>
            </div>
        `).join('');

        const total = this.getTotal();
        const pago = this.pagamentos.reduce((s, p) => s + p.valor, 0);
        const rest = round(total - pago, 2);

        if (rest > 0.01) {
            remaining.textContent = `Faltam: ${this.formatMoney(rest)}`;
            remaining.style.display = 'block';
        } else {
            remaining.style.display = 'none';
        }

        // Highlight active payment button
        document.querySelectorAll('.btn-pay').forEach(btn => {
            btn.classList.toggle('active', this.pagamentos.some(p => p.forma === btn.dataset.forma));
        });
    },

    removeSplitPayment(idx) {
        this.pagamentos.splice(idx, 1);
        this.renderSplitPayments();
        document.getElementById('trocoDisplay').style.display = 'none';
    },

    // ===== FINALIZAR VENDA =====
    async finalizarVenda() {
        if (this.itens.length === 0) {
            this.showAlert('Adicione itens a venda', 'warning');
            return;
        }

        const total = this.getTotal();

        // If no payment selected, prompt
        if (this.pagamentos.length === 0) {
            this.showAlert('Selecione uma forma de pagamento', 'warning');
            return;
        }

        const totalPago = this.pagamentos.reduce((s, p) => s + p.valor, 0);
        // For dinheiro, allow overpayment (troco). For others, must match.
        const hasDinheiro = this.pagamentos.some(p => p.forma === 'dinheiro');
        if (!hasDinheiro && totalPago < total - 0.01) {
            this.showAlert(`Pagamento insuficiente. Faltam ${this.formatMoney(total - totalPago)}`, 'warning');
            return;
        }

        // Show loading
        document.getElementById('loadingOverlay').classList.add('show');

        try {
            const payload = {
                itens: this.itens.map(i => ({
                    produto_id: i.produto_id,
                    quantidade: i.quantidade,
                    preco_unitario: i.preco_unitario,
                    desconto_valor: i.desconto_valor,
                })),
                pagamentos: this.pagamentos,
                cliente_id: this.clienteId,
                vendedor_id: document.getElementById('vendedorSelect')?.value || null,
                desconto_valor: this.descontoValor,
                desconto_percentual: this.descontoPercentual,
            };

            const resp = await fetch('{{ route("app.pdv.venda") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await resp.json();

            document.getElementById('loadingOverlay').classList.remove('show');

            if (!resp.ok || data.error) {
                this.showAlert(data.error || 'Erro ao registrar venda', 'error');
                return;
            }

            // Success
            this.lastCupomHtml = data.cupom || '';

            document.getElementById('sucessoNumero').textContent = data.venda?.numero || '';
            document.getElementById('sucessoTotal').textContent = this.formatMoney(data.venda?.total || 0);

            if (data.venda?.troco > 0) {
                document.getElementById('sucessoTroco').style.display = 'block';
                document.getElementById('sucessoTrocoValor').textContent = this.formatMoney(data.venda.troco);
            } else {
                document.getElementById('sucessoTroco').style.display = 'none';
            }

            const modal = new bootstrap.Modal(document.getElementById('modalSucesso'));
            modal.show();

        } catch (err) {
            document.getElementById('loadingOverlay').classList.remove('show');
            this.showAlert('Erro de conexao: ' + err.message, 'error');
        }
    },

    imprimirCupom() {
        if (!this.lastCupomHtml) return;
        const frame = document.getElementById('printFrame');
        frame.srcdoc = this.lastCupomHtml;
        frame.onload = () => {
            frame.contentWindow.print();
        };
    },

    novaVenda() {
        bootstrap.Modal.getInstance(document.getElementById('modalSucesso'))?.hide();
        this.itens = [];
        this.clienteId = null;
        this.clienteNome = null;
        this.descontoValor = 0;
        this.descontoPercentual = 0;
        this.pagamentos = [];
        this.pagamentoAtual = null;
        this.selectedItemIndex = -1;
        this.lastCupomHtml = '';

        // Reset UI
        this.renderItems();
        this.updateSummary();
        this.renderSplitPayments();
        document.getElementById('trocoDisplay').style.display = 'none';
        document.querySelectorAll('.btn-pay').forEach(b => b.classList.remove('active'));

        // Reset cliente display
        document.getElementById('noCliente').style.display = 'inline';
        document.getElementById('clienteName').style.display = 'none';
        document.getElementById('clienteDoc').style.display = 'none';
        document.getElementById('clienteClear').style.display = 'none';

        document.getElementById('searchInput').focus();
    },

    // ===== CLIENTE =====
    openCliente() {
        const modal = new bootstrap.Modal(document.getElementById('modalCliente'));
        modal.show();
        setTimeout(() => document.getElementById('clienteSearchInput').focus(), 300);

        const input = document.getElementById('clienteSearchInput');
        input.value = '';
        document.getElementById('clienteSearchResults').innerHTML = '';

        // Debounced search
        input.oninput = () => {
            const term = input.value.trim();
            if (term.length < 2) {
                document.getElementById('clienteSearchResults').innerHTML = '';
                return;
            }
            clearTimeout(this._clienteSearchTimeout);
            this._clienteSearchTimeout = setTimeout(() => this.searchClientes(term), 300);
        };
    },

    async searchClientes(term) {
        try {
            const resp = await fetch(`/app/pdv/cliente/${encodeURIComponent(term)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const clientes = await resp.json();
            const results = document.getElementById('clienteSearchResults');

            if (clientes.length === 0) {
                results.innerHTML = '<div style="padding:12px; text-align:center; color:var(--text-muted);">Nenhum cliente encontrado</div>';
                return;
            }

            results.innerHTML = clientes.map(c => `
                <div class="client-search-item" onclick="PDV.selecionarCliente(${c.id}, '${(c.nome_razao_social||'').replace(/'/g, "\\'")}', '${c.cpf_cnpj||''}')">
                    <div style="font-weight:500;">${c.nome_razao_social}</div>
                    <div style="font-size:0.82rem; color:var(--text-muted);">${c.cpf_cnpj || 'Sem documento'}</div>
                </div>
            `).join('');
        } catch (err) {
            console.error('Client search error:', err);
        }
    },

    selecionarCliente(id, nome, doc) {
        this.clienteId = id;
        this.clienteNome = nome;

        document.getElementById('noCliente').style.display = 'none';
        document.getElementById('clienteName').textContent = nome;
        document.getElementById('clienteName').style.display = 'inline';
        if (doc) {
            document.getElementById('clienteDoc').textContent = doc;
            document.getElementById('clienteDoc').style.display = 'inline';
        }
        document.getElementById('clienteClear').style.display = 'inline-block';

        bootstrap.Modal.getInstance(document.getElementById('modalCliente'))?.hide();
        this.showAlert('Cliente: ' + nome, 'success');
    },

    // ===== DESCONTO =====
    openDesconto() {
        if (this.itens.length === 0) {
            this.showAlert('Adicione itens antes de aplicar desconto', 'warning');
            return;
        }
        document.getElementById('descontoInput').value = '';
        document.getElementById('descontoTipo').value = 'valor';
        const modal = new bootstrap.Modal(document.getElementById('modalDesconto'));
        modal.show();
        setTimeout(() => document.getElementById('descontoInput').focus(), 300);
    },

    aplicarDesconto() {
        const tipo = document.getElementById('descontoTipo').value;
        const val = parseFloat(document.getElementById('descontoInput').value) || 0;

        if (val <= 0) {
            this.descontoValor = 0;
            this.descontoPercentual = 0;
        } else if (tipo === 'percentual') {
            if (val > 100) {
                this.showAlert('Percentual nao pode exceder 100%', 'warning');
                return;
            }
            this.descontoPercentual = val;
            this.descontoValor = 0;
        } else {
            if (val > this.getSubtotal()) {
                this.showAlert('Desconto nao pode exceder o subtotal', 'warning');
                return;
            }
            this.descontoValor = val;
            this.descontoPercentual = 0;
        }

        this.updateSummary();
        bootstrap.Modal.getInstance(document.getElementById('modalDesconto'))?.hide();
        if (val > 0) {
            this.showAlert(`Desconto de ${tipo === 'percentual' ? val + '%' : this.formatMoney(val)} aplicado`, 'success');
        }
    },

    // ===== SANGRIA =====
    openSangria() {
        document.getElementById('sangriaValor').value = '';
        document.getElementById('sangriaDescricao').value = '';
        const modal = new bootstrap.Modal(document.getElementById('modalSangria'));
        modal.show();
        setTimeout(() => document.getElementById('sangriaValor').focus(), 300);
    },

    async enviarSangria() {
        const valor = parseFloat(document.getElementById('sangriaValor').value) || 0;
        const descricao = document.getElementById('sangriaDescricao').value.trim();

        if (valor <= 0) { this.showAlert('Informe o valor da sangria', 'warning'); return; }
        if (!descricao) { this.showAlert('Informe a descricao', 'warning'); return; }

        try {
            const resp = await fetch('{{ route("app.caixa.sangria") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ valor, descricao }),
            });
            const data = await resp.json();
            bootstrap.Modal.getInstance(document.getElementById('modalSangria'))?.hide();
            if (data.success) {
                this.showAlert('Sangria registrada com sucesso!', 'success');
            } else {
                this.showAlert(data.error || 'Erro ao registrar sangria', 'error');
            }
        } catch (err) {
            this.showAlert('Erro de conexao', 'error');
        }
    },

    // ===== SUPRIMENTO =====
    openSuprimento() {
        document.getElementById('suprimentoValor').value = '';
        document.getElementById('suprimentoDescricao').value = '';
        const modal = new bootstrap.Modal(document.getElementById('modalSuprimento'));
        modal.show();
        setTimeout(() => document.getElementById('suprimentoValor').focus(), 300);
    },

    async enviarSuprimento() {
        const valor = parseFloat(document.getElementById('suprimentoValor').value) || 0;
        const descricao = document.getElementById('suprimentoDescricao').value.trim();

        if (valor <= 0) { this.showAlert('Informe o valor do suprimento', 'warning'); return; }
        if (!descricao) { this.showAlert('Informe a descricao', 'warning'); return; }

        try {
            const resp = await fetch('{{ route("app.caixa.suprimento") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ valor, descricao }),
            });
            const data = await resp.json();
            bootstrap.Modal.getInstance(document.getElementById('modalSuprimento'))?.hide();
            if (data.success) {
                this.showAlert('Suprimento registrado com sucesso!', 'success');
            } else {
                this.showAlert(data.error || 'Erro ao registrar suprimento', 'error');
            }
        } catch (err) {
            this.showAlert('Erro de conexao', 'error');
        }
    },

    // ===== ALERTS =====
    showAlert(msg, type = 'success') {
        const icons = { success: 'bi-check-circle', error: 'bi-x-circle', warning: 'bi-exclamation-triangle' };
        const div = document.createElement('div');
        div.className = `pdv-alert ${type}`;
        div.innerHTML = `<i class="bi ${icons[type] || icons.success}"></i> ${msg}`;
        document.body.appendChild(div);
        setTimeout(() => {
            div.style.opacity = '0';
            div.style.transition = 'opacity 0.3s';
            setTimeout(() => div.remove(), 300);
        }, 3000);
    },
};

// Utility
function round(val, decimals = 2) {
    return Math.round(val * Math.pow(10, decimals)) / Math.pow(10, decimals);
}

// Cliente clear button
document.getElementById('clienteClear')?.addEventListener('click', () => {
    PDV.clienteId = null;
    PDV.clienteNome = null;
    document.getElementById('noCliente').style.display = 'inline';
    document.getElementById('clienteName').style.display = 'none';
    document.getElementById('clienteDoc').style.display = 'none';
    document.getElementById('clienteClear').style.display = 'none';
});

// Init
document.addEventListener('DOMContentLoaded', () => PDV.init());
</script>
</body>
</html>
