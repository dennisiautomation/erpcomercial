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
        .btn-pay.pay-boleto i { color: var(--accent-orange, #fd7e14); }
        .btn-pay.pay-crediario i { color: var(--accent-red, #dc3545); }
        .btn-pay.pay-transferencia i { color: var(--accent-blue, #0d6efd); }
        .btn-pay.pay-vale i { color: var(--accent-teal, #20c997); }

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
        .btn-doc {
            background: var(--bg-tertiary, #2a2a35);
            color: var(--text-muted, #9a9ab0);
            border: 1px solid var(--border-color, #3a3a48);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.15s;
        }

        .btn-doc.active {
            background: var(--accent-blue, #3b82f6);
            border-color: var(--accent-blue, #3b82f6);
            color: #fff;
        }

        .btn-doc-escolha {
            flex: 1;
            padding: 16px 8px;
            background: var(--bg-tertiary, #2a2a35);
            color: var(--text-primary, #f1f5f9);
            border: 2px solid var(--border-color, #3a3a48);
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-doc-escolha:hover {
            border-color: var(--accent-blue, #3b82f6);
        }

        .btn-doc-escolha.btn-doc-default {
            border-color: var(--accent-green, #22c55e);
            box-shadow: 0 0 0 3px rgba(34,197,94,0.18);
        }

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
        @if(\App\Http\Middleware\CheckPermission::modoPdv(auth()->user()))
            {{-- Modo "só PDV": o dashboard não existe para este usuário, e mandá-lo
                 para lá só produziria um redirect de volta ao PDV. A saída é sair. --}}
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn-back"
                        style="background:none;border:0;padding:0;width:100%;cursor:pointer;">
                    <i class="bi bi-box-arrow-right"></i> Sair do sistema
                </button>
            </form>
        @else
            <a href="{{ route('app.dashboard') }}" class="btn-back">
                <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
            </a>
        @endif
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

        {{-- CPF/CNPJ na nota (avulso — vai no cupom fiscal sem cadastrar cliente) --}}
        <div class="cliente-section">
            <input type="text" id="cpfNota" maxlength="18" autocomplete="off"
                   placeholder="CPF/CNPJ na nota (opcional)"
                   style="width:100%; background:var(--bg-primary); border:1px solid var(--border); color:var(--text-primary); border-radius:8px; font-size:0.85rem; padding:8px 12px;">
        </div>

        {{-- Vendedor — sempre visível; a venda, comissão e (se ligado na Config
             da Loja) o caixa são atribuídos a quem estiver selecionado --}}
        <div class="cliente-section">
            <select id="vendedorSelect" class="form-select" title="Vendedor da venda (F3)"
                    style="background:var(--bg-primary); border:1px solid var(--border); color:var(--text-primary); border-radius:8px; font-size:0.85rem; padding:8px 12px;">
                <option value="">Vendedor: {{ auth()->user()->name }} (padrao)</option>
                @foreach(($operadores ?? collect()) as $op)
                    <option value="{{ $op->id }}">{{ $op->name }} ({{ $op->perfil }})</option>
                @endforeach
            </select>
        </div>

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
            <div class="summary-row" id="creditoRow" style="display:none; color:var(--accent-teal, #20c997);">
                <span>Crédito da troca <small id="creditoCodigo" style="opacity:.75;"></small></span>
                <span class="summary-val" id="summaryCredito" style="color:var(--accent-teal, #20c997);">- R$ 0,00</span>
            </div>
            <div class="summary-row" id="restanteRow" style="display:none;">
                <span>A pagar</span>
                <span class="summary-val" id="summaryRestante">R$ 0,00</span>
            </div>
            <div class="summary-total">
                <span class="label">TOTAL</span>
                <span class="amount" id="summaryTotal">R$ 0,00</span>
                <div id="tabelaPrecoBadge" style="display:none; font-size:0.72rem; font-weight:600; color:var(--accent-yellow, #ffc107); text-align:right;"></div>
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
            <button class="btn-pay pay-boleto" data-forma="boleto" title="Boleto">
                <i class="bi bi-upc-scan"></i> Boleto
            </button>
            <button class="btn-pay pay-crediario" data-forma="crediario" title="Crediario / Fiado">
                <i class="bi bi-journal-text"></i> Crediario
            </button>
            <button class="btn-pay pay-transferencia" data-forma="transferencia" title="Transferencia / TED">
                <i class="bi bi-bank"></i> Transf.
            </button>
            <button class="btn-pay pay-vale" data-forma="vale" title="Vale / Voucher">
                <i class="bi bi-ticket-perforated"></i> Vale
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
            <button class="btn-action" onclick="PDV.openTroca()" title="Troca / devolução de uma venda">
                <i class="bi bi-arrow-repeat"></i> Troca
                <kbd>F6</kbd>
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
        {{-- Documento na finalização: automático (parametrizado) ou escolha manual --}}
        <div id="docChoice" style="display:flex; gap:4px; margin-bottom:6px;">
            <button type="button" class="btn-doc active" data-doc="" title="Segue a parametrização das Configurações da Loja"
                    style="flex:1; font-size:0.72rem; padding:4px 2px;">Auto</button>
            <button type="button" class="btn-doc" data-doc="recibo" title="Força recibo (não fiscal)"
                    style="flex:1; font-size:0.72rem; padding:4px 2px;">Recibo</button>
            <button type="button" class="btn-doc" data-doc="cupom_fiscal" title="Força cupom fiscal (NFC-e)"
                    style="flex:1; font-size:0.72rem; padding:4px 2px;">Cupom Fiscal</button>
        </div>
        <button class="btn-finalizar" id="btnFinalizar" disabled onclick="PDV.finalizarVenda()">
            <i class="bi bi-check-circle"></i> FINALIZAR VENDA <kbd>F12</kbd>
        </button>
    </div>
</div>

{{-- ===== BOTTOM BAR ===== --}}
<div class="pdv-bottombar">
    <span><kbd>F1</kbd> Buscar</span>
    <span><kbd>F2</kbd> Cliente</span>
    <span><kbd>F3</kbd> Vendedor</span>
    <span><kbd>F4</kbd> Desconto</span>
    <span><kbd>F6</kbd> Troca</span>
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
                <div class="mb-3" id="parcelasWrap" style="display:none;">
                    <label class="form-label">Parcelas</label>
                    <select class="form-select" id="parcelasSelect"
                            style="background:var(--bg-primary); color:var(--text-primary); border-color:var(--border-color, #3a3a48);"></select>
                    <div id="jurosResumo" style="display:none; margin-top:.5rem; padding:.6rem .75rem;
                         border-radius:.5rem; background:rgba(255,193,7,.12);
                         border:1px solid rgba(255,193,7,.35); font-size:.9rem;"></div>
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

{{-- Modal: Troca / Devolução (F6) — 03/09/2026 --}}
<div class="modal fade" id="modalTroca" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2" style="color:var(--accent-teal, #20c997)"></i><span id="trocaTitulo">Troca / Devolução</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh; overflow-y:auto;">
                {{-- Passo 1: achar a venda --}}
                <div id="trocaPasso1">
                    <label class="form-label">Qual venda? <small style="color:var(--text-muted);">número da venda, código do cupom (V123) ou nome do cliente — vale venda de qualquer dia e de qualquer loja</small></label>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control" id="trocaBusca" placeholder="Ex.: 158, V158 ou Maria" autocomplete="off">
                        <button type="button" class="modal-btn-primary" onclick="PDV.buscarVendasTroca()"><i class="bi bi-search"></i></button>
                    </div>
                    <div class="client-search-results" id="trocaResultados" style="margin-top:10px;"></div>
                </div>

                {{-- Passo 2: o que volta --}}
                <div id="trocaPasso2" style="display:none;">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <div>
                            <div style="font-weight:700; font-size:1.05rem;">Venda #<span id="trocaVendaNumero"></span> <small id="trocaVendaInfo" style="color:var(--text-muted); font-weight:400;"></small></div>
                            <div id="trocaVendaCliente" style="font-size:.85rem; color:var(--text-secondary);"></div>
                        </div>
                        <button type="button" class="modal-btn-secondary" onclick="PDV.trocaVoltar()"><i class="bi bi-arrow-left"></i> Outra venda</button>
                    </div>
                    <div id="trocaPolitica" style="display:none; padding:8px 12px; border-radius:8px; font-size:.83rem; margin-bottom:10px;"></div>

                    <table class="table table-sm mb-2" style="color:var(--text-primary); font-size:.88rem;">
                        <thead><tr style="color:var(--text-muted); font-size:.75rem; text-transform:uppercase;">
                            <th>Item</th><th class="text-end">Vendido</th><th class="text-end" style="width:110px;">Devolver</th><th class="text-end">Valor</th><th class="text-center" title="A peça volta para a prateleira?">Estoque</th>
                        </tr></thead>
                        <tbody id="trocaItens"></tbody>
                    </table>

                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <label class="form-label">Motivo</label>
                            <select class="form-select" id="trocaMotivo"></select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Detalhe <small style="color:var(--text-muted);">(opcional)</small></label>
                            <input type="text" class="form-control" id="trocaMotivoTexto" maxlength="500" placeholder="Ex.: veio pequeno, cliente quer o M">
                        </div>
                    </div>

                    <div id="trocaEstoqueWrap" style="display:none;" class="mb-2">
                        <label class="form-label">Em qual estoque a peça entra</label>
                        <select class="form-select" id="trocaEstoque"></select>
                    </div>

                    <div id="trocaSobraWrap" class="mb-2" style="display:none;">
                        <label class="form-label">O cliente não vai levar nada agora. A sobra…</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <label class="form-check" style="padding:8px 12px; border:1px solid var(--border-color, #3a3a48); border-radius:8px; cursor:pointer;">
                                <input class="form-check-input" type="radio" name="trocaSobra" value="vale" checked> vira <strong>crédito na loja (vale)</strong>
                            </label>
                            <label class="form-check" id="trocaSobraDinheiroOpt" style="padding:8px 12px; border:1px solid var(--border-color, #3a3a48); border-radius:8px; cursor:pointer;">
                                <input class="form-check-input" type="radio" name="trocaSobra" value="dinheiro"> é <strong>devolvida em dinheiro</strong> pela gaveta
                            </label>
                        </div>
                    </div>

                    <div id="trocaGerenteWrap" style="display:none; padding:10px 12px; border:1px solid var(--accent-yellow); border-radius:8px; margin-bottom:10px;">
                        <div style="font-size:.85rem; color:var(--accent-yellow); margin-bottom:6px;"><i class="bi bi-shield-lock me-1"></i><span id="trocaGerenteMotivo">Fora da política da loja</span> — autorização de um gerente:</div>
                        <div class="row g-2">
                            <div class="col-md-6"><input type="email" class="form-control" id="trocaGerenteEmail" placeholder="E-mail do gerente" autocomplete="off"></div>
                            <div class="col-md-6"><input type="password" class="form-control" id="trocaGerenteSenha" placeholder="Senha do gerente" autocomplete="new-password"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="padding-top:8px; border-top:1px solid var(--border-color, #3a3a48);">
                        <div>
                            <div style="font-size:.78rem; color:var(--text-muted);">VALOR A DEVOLVER</div>
                            <div style="font-size:1.6rem; font-weight:800; color:var(--accent-teal, #20c997);" id="trocaTotal">R$ 0,00</div>
                            <div id="trocaParcelasAviso" style="display:none; font-size:.78rem; color:var(--accent-yellow);"></div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="modal-btn-secondary" id="btnTrocaDevolver" onclick="PDV.trocaModo('devolucao')"><i class="bi bi-box-arrow-in-left"></i> Só devolver</button>
                            <button type="button" class="modal-btn-green" id="btnTrocaTrocar" onclick="PDV.registrarTroca('troca')"><i class="bi bi-upc-scan"></i> Trocar agora (bipar o que leva)</button>
                            <button type="button" class="modal-btn-green" id="btnTrocaConfirmarDevolucao" style="display:none;" onclick="PDV.registrarTroca('devolucao')"><i class="bi bi-check-lg"></i> Confirmar devolução</button>
                        </div>
                    </div>
                </div>

                {{-- Passo 3: resultado da devolução --}}
                <div id="trocaPasso3" style="display:none; text-align:center; padding:12px 0;">
                    <i class="bi bi-check-circle" style="font-size:3rem; color:var(--accent-green); display:block; margin-bottom:8px;"></i>
                    <h5>Devolução registrada</h5>
                    <div id="trocaResultado" style="font-size:.95rem; margin:10px 0 16px;"></div>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="modal-btn-primary" onclick="PDV.imprimirComprovanteTroca()"><i class="bi bi-printer"></i> Imprimir comprovante</button>
                        <button type="button" class="modal-btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Vale (código do crédito de troca) --}}
<div class="modal fade" id="modalVale" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-ticket-perforated me-2" style="color:var(--accent-teal, #20c997)"></i>Vale</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Código do vale <small style="color:var(--text-muted);">(impresso no comprovante da troca)</small></label>
                <input type="text" class="form-control modal-valor-input" id="valeCodigo" placeholder="VT-XXXX-XXXX" autocomplete="off" style="text-transform:uppercase; font-size:1.1rem;">
                <div id="valeInfo" style="display:none; margin-top:10px; font-size:.85rem;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="modal-btn-green" onclick="PDV.confirmarVale()"><i class="bi bi-check-lg"></i> Usar vale</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: sobra do crédito da troca (só quando a loja permite dinheiro) --}}
<div class="modal fade" id="modalSobraTroca" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 28px;">
                <div style="font-size:1.05rem; font-weight:700; margin-bottom:6px;"><i class="bi bi-cash-coin me-1"></i> Sobram <span id="sobraTrocaValor"></span> do crédito</div>
                <div style="font-size:.85rem; color:var(--text-muted); margin-bottom:16px;">O cliente devolveu mais do que está levando. O que fazer com a diferença?</div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-doc-escolha" onclick="PDV.decidirSobraTroca(true)">
                        <i class="bi bi-cash-stack d-block fs-3 mb-1"></i> Devolver em dinheiro
                        <div style="font-size:0.68rem; opacity:0.75;">sai da gaveta agora</div>
                    </button>
                    <button type="button" class="btn-doc-escolha" onclick="PDV.decidirSobraTroca(false)">
                        <i class="bi bi-ticket-perforated d-block fs-3 mb-1"></i> Deixar no vale
                        <div style="font-size:0.68rem; opacity:0.75;">cliente usa depois</div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Sucesso venda --}}
{{-- Pergunta de documento na finalização (modo Auto sem regra automática) --}}
<div class="modal fade" id="modalDocumento" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 430px;">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 28px;">
                <div style="font-size:1.1rem; font-weight:700; margin-bottom:16px;">
                    <i class="bi bi-printer me-1"></i> Qual documento imprimir?
                </div>
                <div class="d-flex gap-2">
                    <button type="button" id="btnDocCupom" class="btn-doc-escolha" onclick="PDV.confirmarDocumento('cupom_fiscal')">
                        <i class="bi bi-receipt-cutoff d-block fs-3 mb-1"></i> Cupom Fiscal
                        <div style="font-size:0.68rem; opacity:0.75;">NFC-e na SEFAZ</div>
                    </button>
                    <button type="button" id="btnDocRecibo" class="btn-doc-escolha" onclick="PDV.confirmarDocumento('recibo')">
                        <i class="bi bi-file-text d-block fs-3 mb-1"></i> Recibo
                        <div style="font-size:0.68rem; opacity:0.75;">Não fiscal</div>
                    </button>
                </div>
                <div style="font-size:0.7rem; color:var(--text-muted, #9a9ab0); margin-top:12px;">
                    Enter confirma o destacado · para não perguntar, escolha Recibo/Cupom antes de finalizar
                </div>
            </div>
        </div>
    </div>
</div>

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
                <div id="sucessoNfceErro" style="display:none; margin-bottom:16px; padding:10px 14px; border:1px solid var(--accent-yellow); border-radius:8px; color:var(--accent-yellow); font-size:0.82rem; text-align:left; line-height:1.4;"></div>
                <div id="sucessoVale" style="display:none; margin-bottom:16px; padding:10px 14px; border:1px solid var(--accent-teal, #20c997); border-radius:8px; color:var(--text-primary); font-size:0.85rem; text-align:left; line-height:1.5;"></div>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <button class="modal-btn-primary" onclick="PDV.imprimirCupom()">
                        <i class="bi bi-printer"></i> Imprimir Cupom
                    </button>
                    <button class="modal-btn-secondary" id="btnImprimirTroca" style="display:none;" onclick="PDV.imprimirComprovanteTroca()">
                        <i class="bi bi-arrow-repeat"></i> Comprovante da troca
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
    clienteTipoPreco: 'varejo',
    descontoValor: 0,
    descontoPercentual: 0,
    pagamentos: [],
    // Tabelas de preço por forma de pagamento (Configurações da Loja)
    precosCache: {},
    tabelaAtiva: 'dinheiro_pix',
    // Documento na finalização: null = automático (parametrização da loja)
    documentoEscolhido: null,
    configLoja: {!! json_encode([
        'regra_split'             => $configLoja->regra_preco_split ?? 'cartao_maior',
        'max_parcelas'            => (int) ($configLoja->max_parcelas ?? 6),
        'juros_por_parcela'       => (object) ($configLoja->juros_por_parcela ?? []),
        // Quem decide e o service, no PHP — o front so consome, para a regra
        // nao viver em dois lugares e divergir.
        'mostrar_valor_parcelas'  => app(\App\Services\JurosParcelamentoService::class)
                                        ->mostrarValorParcelas($configLoja),
        'exists'                  => $configLoja->exists,
        'cupom_automatico_cartao' => (bool) ($configLoja->cupom_automatico_cartao ?? false),
        'cpf_emite_fiscal'        => (bool) ($configLoja->cpf_emite_fiscal ?? false),
        'padrao_impressao'        => $configLoja->padrao_impressao ?? 'recibo',
        'troca_sobra'             => $configLoja->troca_sobra ?? 'vale',
        'fiscal_ativo'            => (bool) ($configFiscal
            && $configFiscal->emissao_fiscal_ativa
            && ($configFiscal->emite_nfce ?? ($configFiscal->tipo_cupom_pdv === 'fiscal'))),
    ]) !!},
    estoquesLoja: {!! json_encode($estoquesLoja ?? []) !!},
    pagamentoAtual: null,
    // Troca (F6): crédito da devolução aplicado na venda nova
    creditoTroca: null,
    valeSobraDinheiro: null,
    lastTrocaHtml: '',
    valeAtual: null,
    _trocaSituacao: null,
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

        // Máscara do "CPF na nota" — aceita CNPJ alfanumérico (NT 2025.001)
        document.getElementById('cpfNota')?.addEventListener('input', function () {
            const a = this.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase();
            if (/[A-Z]/.test(a) || a.length > 11) {
                const c = a.slice(0, 14);
                const base = c.slice(0, 12), dv = c.slice(12).replace(/[^0-9]/g, '');
                let v = base.slice(0, 2);
                if (base.length > 2) v += '.' + base.slice(2, 5);
                if (base.length > 5) v += '.' + base.slice(5, 8);
                if (base.length > 8) v += '/' + base.slice(8, 12);
                if (dv.length) v += '-' + dv;
                this.value = v;
            } else {
                let v = a.slice(0, 11);
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                this.value = v;
            }
        });

        // Modal de pagamento fechado sem confirmar: volta à tabela das formas confirmadas
        document.getElementById('modalPagamento')?.addEventListener('hidden.bs.modal', () => {
            this.repriceItens();
        });

        // Escolha manual do documento (Auto / Recibo / Cupom Fiscal)
        document.querySelectorAll('#docChoice .btn-doc').forEach(btn => {
            btn.addEventListener('click', () => {
                this.documentoEscolhido = btn.dataset.doc || null;
                document.querySelectorAll('#docChoice .btn-doc').forEach(b =>
                    b.classList.toggle('active', b === btn));
            });
        });

        // Modal "Qual documento imprimir?": Enter confirma o destacado
        document.getElementById('modalDocumento')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.confirmarDocumento(this._docPadrao || 'cupom_fiscal');
            }
        });
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
                case 'F3':
                    e.preventDefault();
                    document.getElementById('vendedorSelect')?.focus();
                    break;
                case 'F4':
                    e.preventDefault();
                    this.openDesconto();
                    break;
                case 'F6':
                    e.preventDefault();
                    this.openTroca();
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
            this.cachePrecos(produtos);
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
            this.cachePrecos(produtos);
            this.showSearchResults(produtos);
        } catch (err) {
            console.error('Search error:', err);
        }
    },

    cachePrecos(produtos) {
        (produtos || []).forEach(p => {
            if (p && p.precos) this.precosCache[p.id] = p.precos;
        });
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
        let estoqueData = null;
        try {
            const r = await fetch(`/app/pdv/estoque/${produto.id}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            estoqueData = await r.json();
        } catch (err) { /* segue mesmo se falhar */ }

        let unidadeOrigemId = null;

        // Estoque local zerado mas tem em outra unidade da empresa?
        if (estoqueData && estoqueData.estoque_atual <= 0
            && estoqueData.pode_vender_remoto
            && estoqueData.outras_unidades.length > 0) {
            const escolha = await this.escolherUnidadeRemota(produto, estoqueData.outras_unidades);
            if (escolha === null) return; // usuário cancelou
            unidadeOrigemId = escolha;
        } else if (estoqueData && estoqueData.estoque_atual <= 0) {
            // Estoque local zerado e política não permite venda remota (ou nenhuma outra unidade tem)
            const outras = estoqueData.outras_unidades || [];
            if (outras.length > 0) {
                const lista = outras.map(u => `${u.nome}: ${u.saldo}`).join(' · ');
                this.showAlert(`Sem estoque local. Disponível em outras: ${lista}`, 'warning');
            } else {
                this.showAlert('Sem estoque para: ' + produto.descricao, 'warning');
            }
        }

        const existing = this.itens.find(i => i.produto_id === produto.id && i.unidade_origem_id === unidadeOrigemId);
        if (existing) {
            existing.quantidade += 1;
            existing.total = round((existing.preco_unitario * existing.quantidade) - existing.desconto_valor, 2);
        } else {
            const precos = produto.precos || this.precosCache[produto.id] || null;
            this.itens.push({
                produto_id: produto.id,
                descricao: produto.descricao,
                codigo_interno: produto.codigo_interno || '',
                codigo_barras: produto.codigo_barras || '',
                preco_unitario: parseFloat(produto.preco_venda),
                precos: precos,
                quantidade: 1,
                desconto_valor: 0,
                total: parseFloat(produto.preco_venda),
                unidade_origem_id: unidadeOrigemId,
                unidade_origem_nome: unidadeOrigemId
                    ? (estoqueData.outras_unidades.find(u => u.unidade_id === unidadeOrigemId)?.nome || '')
                    : null,
            });
        }
        this.selectedItemIndex = this.itens.length - 1;
        this.repriceItens();
    },

    // ===== TABELAS DE PREÇO POR FORMA DE PAGAMENTO =====
    modalidadeDaForma(forma) {
        return ({
            dinheiro: 'dinheiro_pix', pix: 'dinheiro_pix',
            cartao_debito: 'debito', cartao_credito: 'credito',
        })[forma] || 'dinheiro_pix';
    },

    // Modalidade aplicável às formas escolhidas (+ uma forma em análise no modal)
    modalidadeAtual(formaExtra = null) {
        // Cliente de atacado leva o preco de atacado em qualquer forma de pagamento
        if (this.clienteTipoPreco === 'atacado') return 'atacado';

        const formas = this.pagamentos.map(p => p.forma);
        if (formaExtra) formas.push(formaExtra);
        if (formas.length === 0) return 'dinheiro_pix';

        const mods = formas.map(f => this.modalidadeDaForma(f));
        if (formas.length === 1) return mods[0];

        const regra = this.configLoja.regra_split;
        if (regra === 'sempre_menor') return 'dinheiro_pix';
        if (regra === 'sempre_maior') return 'credito';
        // cartao_maior: a maior tabela entre as formas presentes
        const peso = { dinheiro_pix: 0, debito: 1, credito: 2 };
        return mods.sort((a, b) => peso[b] - peso[a])[0];
    },

    // Reaplica a tabela de preço da modalidade vigente em todos os itens
    repriceItens(formaExtra = null) {
        const mod = this.modalidadeAtual(formaExtra);
        this.itens.forEach(item => {
            if (item.precos && item.precos[mod] !== undefined) {
                item.preco_unitario = parseFloat(item.precos[mod]);
            }
            item.total = round((item.preco_unitario * item.quantidade) - item.desconto_valor, 2);
        });
        this.tabelaAtiva = mod;
        this.renderItems();
        this.updateSummary();
        this.updateTabelaBadge();
    },

    updateTabelaBadge() {
        const badge = document.getElementById('tabelaPrecoBadge');
        if (!badge) return;
        const labels = { dinheiro_pix: null, debito: 'Tabela: Débito', credito: 'Tabela: Crédito', atacado: 'Tabela: Atacado' };
        const texto = labels[this.tabelaAtiva];
        badge.style.display = texto ? 'block' : 'none';
        badge.textContent = texto || '';
    },

    // Modal de escolha de unidade remota (estoque vem de outra loja).
    // Estrutura criada via DOM (não innerHTML) para garantir escape de
    // produto.descricao e u.nome — dados vêm do banco da empresa.
    escolherUnidadeRemota(produto, outrasUnidades) {
        return new Promise(resolve => {
            const wrap = document.createElement('div');
            wrap.className = 'modal fade show d-block';
            wrap.style.background = 'rgba(0,0,0,.5)';
            wrap.tabIndex = -1;

            const dialog = document.createElement('div');
            dialog.className = 'modal-dialog modal-dialog-centered';
            wrap.appendChild(dialog);

            const content = document.createElement('div');
            content.className = 'modal-content';
            dialog.appendChild(content);

            // Header
            const header = document.createElement('div');
            header.className = 'modal-header bg-warning bg-opacity-25';
            const title = document.createElement('h6');
            title.className = 'modal-title';
            title.innerHTML = '<i class="bi bi-arrow-left-right me-2"></i>';
            title.appendChild(document.createTextNode('Vender de outra loja?'));
            header.appendChild(title);
            content.appendChild(header);

            // Body
            const body = document.createElement('div');
            body.className = 'modal-body';
            const p1 = document.createElement('p');
            p1.className = 'mb-2';
            const strong = document.createElement('strong');
            strong.textContent = produto.descricao;
            p1.appendChild(strong);
            p1.appendChild(document.createTextNode(' está zerado nesta loja.'));
            body.appendChild(p1);

            const p2 = document.createElement('p');
            p2.className = 'text-muted small mb-3';
            p2.textContent = 'Selecione a unidade de origem — uma transferência automática será criada.';
            body.appendChild(p2);

            const grid = document.createElement('div');
            grid.className = 'd-grid gap-2';
            outrasUnidades.forEach(u => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-primary text-start';
                btn.dataset.unidade = String(u.unidade_id);

                const flex = document.createElement('div');
                flex.className = 'd-flex justify-content-between';
                const left = document.createElement('span');
                left.innerHTML = '<i class="bi bi-shop me-2"></i>';
                left.appendChild(document.createTextNode(u.nome));
                const right = document.createElement('strong');
                right.textContent = `${u.saldo} disp.`;
                flex.appendChild(left);
                flex.appendChild(right);
                btn.appendChild(flex);
                grid.appendChild(btn);
            });
            body.appendChild(grid);
            content.appendChild(body);

            // Footer
            const footer = document.createElement('div');
            footer.className = 'modal-footer';
            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-outline-secondary';
            cancelBtn.textContent = 'Cancelar';
            footer.appendChild(cancelBtn);
            content.appendChild(footer);

            document.body.appendChild(wrap);

            const cleanup = () => wrap.remove();
            cancelBtn.onclick = () => { cleanup(); resolve(null); };
            grid.querySelectorAll('[data-unidade]').forEach(btn => {
                btn.onclick = () => {
                    const id = parseInt(btn.dataset.unidade, 10);
                    cleanup();
                    resolve(id);
                };
            });
        });
    },

    // Escapa string p/ uso em template literal HTML — proteção XSS básica.
    escHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
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
        const esc = (s) => this.escHtml(s);
        tbody.innerHTML = this.itens.map((item, idx) => `
            <tr class="${idx === this.selectedItemIndex ? 'selected' : ''}" onclick="PDV.selectItem(${idx})">
                <td class="col-seq">${idx + 1}</td>
                <td class="col-code">${esc(item.codigo_interno || item.codigo_barras || '-')}</td>
                <td class="col-desc">
                    ${esc(item.descricao)}
                    ${item.unidade_origem_id ? `<span class="badge bg-warning text-dark ms-1" title="Estoque da unidade ${esc(item.unidade_origem_nome)}"><i class="bi bi-arrow-left-right"></i> ${esc(item.unidade_origem_nome)}</span>` : ''}
                </td>
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

        // Crédito da troca (F6): abate do total; o que passar fica no vale
        const creditoRow = document.getElementById('creditoRow');
        const restanteRow = document.getElementById('restanteRow');
        if (this.creditoTroca) {
            const credito = this.creditoAplicado();
            creditoRow.style.display = 'flex';
            document.getElementById('creditoCodigo').textContent = this.creditoTroca.codigo;
            document.getElementById('summaryCredito').textContent = '- ' + this.formatMoney(credito);
            restanteRow.style.display = 'flex';
            document.getElementById('summaryRestante').textContent = this.formatMoney(Math.max(0, round(total - credito, 2)));
        } else {
            creditoRow.style.display = 'none';
            restanteRow.style.display = 'none';
        }

        const btn = document.getElementById('btnFinalizar');
        btn.disabled = this.itens.length === 0;
    },

    creditoAplicado() {
        if (!this.creditoTroca) return 0;
        return round(Math.min(this.creditoTroca.saldo, this.getTotal()), 2);
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
                if (forma === 'vale') { this.openVale(); return; }
                this.openPagamento(forma);
            });
        });
    },

    openPagamento(forma) {
        if (this.itens.length === 0) {
            this.showAlert('Adicione itens antes de selecionar pagamento', 'warning');
            return;
        }

        // Preço depende da forma: reaplica a tabela antes de mostrar o total
        this.repriceItens(forma);

        const total = this.getTotal();
        const jaAdicionado = this.pagamentos.reduce((s, p) => s + p.valor, 0) + this.creditoAplicado();
        const restante = round(total - jaAdicionado, 2);
        const temParcial = this.pagamentos.length > 0 || this.creditoAplicado() > 0;

        if (restante <= 0 && temParcial) {
            this.showAlert('Pagamento ja completo. Finalize a venda.', 'warning');
            return;
        }

        this.pagamentoAtual = forma;

        const formaLabels = {
            'dinheiro': 'Dinheiro',
            'cartao_credito': 'Cartao de Credito',
            'cartao_debito': 'Cartao de Debito',
            'pix': 'PIX',
            'boleto': 'Boleto',
            'crediario': 'Crediario',
            'transferencia': 'Transferencia',
            'vale': 'Vale'
        };

        document.getElementById('modalPagamentoTitle').textContent = 'Pagamento - ' + (formaLabels[forma] || forma);
        document.getElementById('modalPagamentoForma').textContent = formaLabels[forma] || forma;
        document.getElementById('modalPagamentoTotal').textContent = this.formatMoney(temParcial ? restante : total);

        const valorInput = document.getElementById('valorRecebido');

        // For dinheiro, show valor recebido field for troco calc
        // For others, auto-fill the remaining
        if (forma === 'dinheiro') {
            document.getElementById('valorRecebidoWrap').style.display = 'block';
            valorInput.value = '';
            document.getElementById('modalTrocoWrap').style.display = 'none';
        } else if (forma === 'vale' && this.valeAtual) {
            // Vale: no máximo o saldo, no máximo o que falta pagar
            document.getElementById('valorRecebidoWrap').style.display = 'block';
            valorInput.value = Math.min(this.valeAtual.saldo, temParcial ? restante : total).toFixed(2);
            document.getElementById('modalTrocoWrap').style.display = 'none';
        } else {
            document.getElementById('valorRecebidoWrap').style.display = 'block';
            valorInput.value = (temParcial ? restante : total).toFixed(2);
            document.getElementById('modalTrocoWrap').style.display = 'none';
        }

        // Show/hide split checkbox
        document.getElementById('splitCheck').style.display = temParcial ? 'none' : 'block';
        document.getElementById('isSplitPayment').checked = temParcial;

        // Parcelas: só para cartão de crédito
        const parcelasWrap = document.getElementById('parcelasWrap');
        if (forma === 'cartao_credito') {
            parcelasWrap.style.display = 'block';
            document.getElementById('parcelasSelect').onchange = () => this.renderResumoJuros();
            this.atualizarParcelas(1);
        } else {
            parcelasWrap.style.display = 'none';
            document.getElementById('jurosResumo').style.display = 'none';
        }

        // Troco calculation for dinheiro
        valorInput.oninput = () => {
            if (forma === 'dinheiro') {
                const recebido = parseFloat(valorInput.value) || 0;
                const valorEsperado = temParcial ? restante : total;
                const troco = recebido - valorEsperado;
                if (troco > 0) {
                    document.getElementById('modalTrocoWrap').style.display = 'block';
                    document.getElementById('modalTroco').textContent = this.formatMoney(troco);
                } else {
                    document.getElementById('modalTrocoWrap').style.display = 'none';
                }
            }
            // Mudou o valor a parcelar? As parcelas e o juros acompanham.
            if (forma === 'cartao_credito') {
                this.atualizarParcelas();
            }
        };

        const modal = new bootstrap.Modal(document.getElementById('modalPagamento'));
        modal.show();

        setTimeout(() => valorInput.focus(), 300);
    },

    // ===== JUROS DE PARCELAMENTO (cartão de crédito) =====
    // Espelha JurosParcelamentoService: a tabela da loja diz quanto a venda
    // encarece em cada quantidade de parcelas. Aqui é só a prévia que o caixa
    // vê — quem grava o valor final é o servidor, que refaz esta conta.
    percentualJuros(parcelas) {
        const tabela = this.configLoja.juros_por_parcela || {};
        return Math.max(0, parseFloat(tabela[parcelas] || 0));
    },

    simularParcelas(valor, parcelas) {
        const percentual = this.percentualJuros(parcelas);
        valor = round(valor, 2);

        const total = percentual > 0 ? round(valor * (1 + percentual / 100), 2) : valor;

        return {
            parcelas,
            valorParcela: round(total / parcelas, 2),
            total,
            juros: round(total - valor, 2),
            percentual,
            temJuros: percentual > 0,
        };
    },

    // Valor que está sendo parcelado: o que o caixa digitou, ou o restante da venda.
    baseParcelamento() {
        const digitado = parseFloat(document.getElementById('valorRecebido')?.value) || 0;
        if (digitado > 0) return digitado;

        const total = this.getTotal();
        const jaAdicionado = this.pagamentos.reduce((s, p) => s + p.valor, 0);
        return this.pagamentos.length > 0 ? round(total - jaAdicionado, 2) : total;
    },

    atualizarParcelas(forcarSelecao = null) {
        const sel = document.getElementById('parcelasSelect');
        if (!sel) return;

        const escolhido = forcarSelecao || parseInt(sel.value || '1', 10);
        const max = this.configLoja.max_parcelas || 6;
        const base = this.baseParcelamento();

        sel.innerHTML = '';
        for (let n = 1; n <= max; n++) {
            const sim = this.simularParcelas(base, n);
            const opt = document.createElement('option');
            opt.value = n;
            // Loja que nao ligou o valor da parcela (e nao cobra juros) ve o
            // select de sempre: '2x', '3x'. Mudar a tela de quem nao pediu nada
            // e o unico jeito desta entrega vazar para os outros clientes.
            opt.textContent = !this.configLoja.mostrar_valor_parcelas
                ? (n === 1 ? 'À vista (1x)' : n + 'x')
                : n === 1
                    ? 'À vista (1x) — ' + this.formatMoney(sim.total)
                    : n + 'x de ' + this.formatMoney(sim.valorParcela)
                      + (sim.temJuros ? ' · total ' + this.formatMoney(sim.total) : ' sem juros');
            sel.appendChild(opt);
        }

        sel.value = Math.min(escolhido, max) || 1;
        this.renderResumoJuros();
    },

    renderResumoJuros() {
        const box = document.getElementById('jurosResumo');
        if (!box) return;

        const parcelas = parseInt(document.getElementById('parcelasSelect')?.value || '1', 10);
        const sim = this.simularParcelas(this.baseParcelamento(), parcelas);

        if (!sim.temJuros) {
            box.style.display = 'none';
            return;
        }

        box.style.display = 'block';
        box.innerHTML = `
            <div><strong>${parcelas}x de ${this.formatMoney(sim.valorParcela)}</strong></div>
            <div>Juros (${sim.percentual.toFixed(2).replace('.', ',')}%): + ${this.formatMoney(sim.juros)}</div>
            <div>Total com juros: <strong>${this.formatMoney(sim.total)}</strong></div>`;
    },

    confirmarPagamento() {
        const forma = this.pagamentoAtual;
        const valorInput = document.getElementById('valorRecebido');
        const valor = parseFloat(valorInput.value) || 0;
        const isSplit = document.getElementById('isSplitPayment').checked || this.pagamentos.length > 0 || this.creditoAplicado() > 0;
        const total = this.getTotal();
        const jaAdicionado = this.pagamentos.reduce((s, p) => s + p.valor, 0) + this.creditoAplicado();
        const restante = round(total - jaAdicionado, 2);

        if (valor <= 0) {
            this.showAlert('Informe o valor do pagamento', 'warning');
            return;
        }

        if (forma === 'vale') {
            if (!this.valeAtual) { this.showAlert('Informe o código do vale', 'warning'); return; }
            if (valor > this.valeAtual.saldo + 0.01) {
                this.showAlert('O vale tem saldo de ' + this.formatMoney(this.valeAtual.saldo), 'warning');
                return;
            }
        }

        // For dinheiro, accept any value >= total for troco
        // For other methods, value should not exceed remaining
        if (forma !== 'dinheiro' && valor > restante + 0.01) {
            this.showAlert('Valor excede o restante da venda', 'warning');
            return;
        }

        const parcelas = forma === 'cartao_credito'
            ? parseInt(document.getElementById('parcelasSelect')?.value || '1', 10)
            : 1;

        const extra = forma === 'vale' && this.valeAtual ? { vale_codigo: this.valeAtual.codigo } : {};

        if (isSplit) {
            // Split payment - add to list
            this.pagamentos.push({ forma, valor: Math.min(valor, restante), parcelas, ...extra });
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
            this.pagamentos = [{ forma, valor, parcelas, ...extra }];
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
            'cartao_debito': 'Debito', 'pix': 'PIX',
            'boleto': 'Boleto', 'crediario': 'Crediario',
            'transferencia': 'Transf.', 'vale': 'Vale'
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
        this.repriceItens();
        this.renderSplitPayments();
        document.getElementById('trocoDisplay').style.display = 'none';
    },

    // ===== DOCUMENTO NA FINALIZAÇÃO =====
    // Escolha manual > regras automáticas (cartão/CPF) > perguntar ao operador
    decidirDocumento() {
        const cfg = this.configLoja;
        if (!cfg.fiscal_ativo) return 'recibo';

        const formas = this.pagamentos.map(p => p.forma);
        const temCartao = formas.includes('cartao_credito') || formas.includes('cartao_debito');

        if (cfg.exists && cfg.cupom_automatico_cartao && temCartao) return 'auto';
        if (cfg.exists && cfg.cpf_emite_fiscal && this.clienteId) return 'auto';

        return 'perguntar';
    },

    mostrarPerguntaDocumento() {
        this._docPadrao = this.configLoja.exists ? this.configLoja.padrao_impressao : 'cupom_fiscal';
        document.getElementById('btnDocCupom').classList.toggle('btn-doc-default', this._docPadrao === 'cupom_fiscal');
        document.getElementById('btnDocRecibo').classList.toggle('btn-doc-default', this._docPadrao === 'recibo');
        new bootstrap.Modal(document.getElementById('modalDocumento')).show();
    },

    confirmarDocumento(doc) {
        bootstrap.Modal.getInstance(document.getElementById('modalDocumento'))?.hide();
        this.finalizarVenda(doc);
    },

    // ===== FINALIZAR VENDA =====
    async finalizarVenda(docConfirmado = null) {
        if (this.itens.length === 0) {
            this.showAlert('Adicione itens a venda', 'warning');
            return;
        }

        const total = this.getTotal();
        const credito = this.creditoAplicado();

        // If no payment selected, prompt (crédito da troca cobrindo tudo dispensa)
        if (this.pagamentos.length === 0 && credito < total - 0.01) {
            this.showAlert(credito > 0
                ? `O crédito cobre ${this.formatMoney(credito)}. Escolha a forma para os ${this.formatMoney(total - credito)} restantes.`
                : 'Selecione uma forma de pagamento', 'warning');
            return;
        }

        const totalPago = this.pagamentos.reduce((s, p) => s + p.valor, 0) + credito;
        // For dinheiro, allow overpayment (troco). For others, must match.
        const hasDinheiro = this.pagamentos.some(p => p.forma === 'dinheiro');
        if (!hasDinheiro && totalPago < total - 0.01) {
            this.showAlert(`Pagamento insuficiente. Faltam ${this.formatMoney(total - totalPago)}`, 'warning');
            return;
        }

        // Sobra do crédito da troca: se a loja permite dinheiro, o caixa decide
        if (this.creditoTroca && this.creditoTroca.saldo > total + 0.009
            && this.configLoja.troca_sobra === 'dinheiro' && this.valeSobraDinheiro === null) {
            document.getElementById('sobraTrocaValor').textContent = this.formatMoney(round(this.creditoTroca.saldo - total, 2));
            this._docAposSobra = docConfirmado;
            new bootstrap.Modal(document.getElementById('modalSobraTroca')).show();
            return;
        }

        // Documento desta venda: escolha manual, regra automática ou pergunta
        const documento = docConfirmado || this.documentoEscolhido || this.decidirDocumento();
        if (documento === 'perguntar') {
            this.mostrarPerguntaDocumento();
            return;
        }

        // Show loading
        document.getElementById('loadingOverlay').classList.add('show');

        try {
            const payload = {
                tabela_precos: 1,
                juros_parcelamento: 1,
                documento: documento === 'auto' ? null : documento,
                itens: this.itens.map(i => ({
                    produto_id: i.produto_id,
                    quantidade: i.quantidade,
                    preco_unitario: i.preco_unitario,
                    desconto_valor: i.desconto_valor,
                    unidade_origem_id: i.unidade_origem_id || null,
                })),
                pagamentos: credito > 0
                    ? [{ forma: 'vale', valor: credito, parcelas: 1, vale_codigo: this.creditoTroca.codigo }, ...this.pagamentos]
                    : this.pagamentos,
                vale_sobra_dinheiro: this.valeSobraDinheiro ? 1 : 0,
                troca_devolucao_id: this.creditoTroca ? this.creditoTroca.devolucaoId : null,
                cliente_id: this.clienteId,
                cpf_cnpj_nota: document.getElementById('cpfNota')?.value || null,
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

            // Vale / crédito da troca usado nesta venda
            const valeBox = document.getElementById('sucessoVale');
            if (data.vale) {
                let txt = `<i class="bi bi-ticket-perforated"></i> Vale <strong>${data.vale.codigo}</strong>: usado ${this.formatMoney(data.vale.valor_usado)}.`;
                if (data.vale.sobra_devolvida > 0) {
                    txt += `<br><strong>${this.formatMoney(data.vale.sobra_devolvida)} devolvidos em dinheiro</strong> ao cliente (saíram do caixa).`;
                } else if (data.vale.saldo_restante > 0) {
                    txt += `<br>Ainda restam <strong>${this.formatMoney(data.vale.saldo_restante)}</strong> no vale — o cliente usa numa próxima compra.`;
                } else {
                    txt += ' Vale totalmente utilizado.';
                }
                valeBox.innerHTML = txt;
                valeBox.style.display = 'block';
            } else {
                valeBox.style.display = 'none';
            }
            document.getElementById('btnImprimirTroca').style.display = this.lastTrocaHtml ? 'inline-flex' : 'none';

            // limpa o CPF na nota após uso (não vaza para a próxima venda)
            const cpfNotaPos = document.getElementById('cpfNota');
            if (cpfNotaPos) cpfNotaPos.value = '';

            // Cupom fiscal falhou? A venda sai como recibo — avisar o motivo
            const nfceErroBox = document.getElementById('sucessoNfceErro');
            if (data.nfce_erro) {
                nfceErroBox.innerHTML = '<strong><i class="bi bi-exclamation-triangle"></i> Cupom fiscal (NFC-e) não emitido — saiu recibo.</strong><br>'
                    + String(data.nfce_erro).replace(/\n/g, '<br>');
                nfceErroBox.style.display = 'block';
            } else {
                nfceErroBox.style.display = 'none';
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
        this.clienteTipoPreco = 'varejo';
        this.descontoValor = 0;
        this.descontoPercentual = 0;
        this.pagamentos = [];
        this.pagamentoAtual = null;
        this.creditoTroca = null;
        this.valeSobraDinheiro = null;
        this.valeAtual = null;
        this.lastTrocaHtml = '';
        this.selectedItemIndex = -1;
        this.lastCupomHtml = '';
        this.tabelaAtiva = 'dinheiro_pix';
        this.updateTabelaBadge();
        this.documentoEscolhido = null;
        document.querySelectorAll('#docChoice .btn-doc').forEach(b =>
            b.classList.toggle('active', !b.dataset.doc));
        const cpfNotaEl = document.getElementById('cpfNota');
        if (cpfNotaEl) cpfNotaEl.value = '';

        // Vendedor volta ao operador logado — evita comissão indo pro vendedor errado
        const vendedorEl = document.getElementById('vendedorSelect');
        if (vendedorEl) vendedorEl.value = '';

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
                <div class="client-search-item" onclick="PDV.selecionarCliente(${c.id}, '${(c.nome_razao_social||'').replace(/'/g, "\\'")}', '${c.cpf_cnpj||''}', '${c.tipo_preco||'varejo'}')">
                    <div style="font-weight:500;">${c.nome_razao_social}</div>
                    <div style="font-size:0.82rem; color:var(--text-muted);">${c.cpf_cnpj || 'Sem documento'}</div>
                </div>
            `).join('');
        } catch (err) {
            console.error('Client search error:', err);
        }
    },

    selecionarCliente(id, nome, doc, tipoPreco = 'varejo') {
        this.clienteId = id;
        this.clienteNome = nome;
        this.clienteTipoPreco = tipoPreco;

        document.getElementById('noCliente').style.display = 'none';
        document.getElementById('clienteName').textContent = nome;
        document.getElementById('clienteName').style.display = 'inline';
        if (doc) {
            document.getElementById('clienteDoc').textContent = doc;
            document.getElementById('clienteDoc').style.display = 'inline';
        }
        document.getElementById('clienteClear').style.display = 'inline-block';

        bootstrap.Modal.getInstance(document.getElementById('modalCliente'))?.hide();

        // Trocar de cliente muda a tabela de preco dos itens ja lancados
        this.repriceItens();

        if (tipoPreco === 'atacado') {
            const semAtacado = this.itens.filter(i => !i.precos || i.precos.atacado === undefined);
            this.showAlert('Cliente: ' + nome + ' — preco de ATACADO aplicado', 'success');
            if (semAtacado.length) {
                this.showAlert(semAtacado.length + ' item(ns) sem preco de atacado cadastrado — seguem no preco normal', 'warning');
            }
        } else {
            this.showAlert('Cliente: ' + nome, 'success');
        }
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

    // ===== TROCA / DEVOLUÇÃO (F6) — 03/09/2026 =====
    openTroca() {
        if (this.creditoTroca) {
            this.showAlert('Já há uma troca em andamento (crédito de ' + this.formatMoney(this.creditoTroca.saldo) + '). Finalize a venda ou inicie uma nova.', 'warning');
            return;
        }
        this._trocaSituacao = null;
        document.getElementById('trocaPasso1').style.display = 'block';
        document.getElementById('trocaPasso2').style.display = 'none';
        document.getElementById('trocaPasso3').style.display = 'none';
        document.getElementById('trocaTitulo').textContent = 'Troca / Devolução';
        const busca = document.getElementById('trocaBusca');
        busca.value = '';
        document.getElementById('trocaResultados').innerHTML = '';
        busca.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); this.buscarVendasTroca(); } };
        new bootstrap.Modal(document.getElementById('modalTroca')).show();
        setTimeout(() => busca.focus(), 300);
        // lista as últimas vendas já de cara
        this.buscarVendasTroca();
    },

    async buscarVendasTroca() {
        const q = document.getElementById('trocaBusca').value.trim();
        const box = document.getElementById('trocaResultados');
        box.innerHTML = '<div style="padding:12px; text-align:center; color:var(--text-muted);">Buscando...</div>';
        try {
            const resp = await fetch('{{ route("app.pdv.troca.vendas") }}?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const vendas = await resp.json();
            if (!resp.ok) { box.innerHTML = '<div style="padding:12px; color:var(--accent-red);">' + (vendas.error || 'Erro ao buscar') + '</div>'; return; }
            if (!vendas.length) { box.innerHTML = '<div style="padding:12px; text-align:center; color:var(--text-muted);">Nenhuma venda concluída encontrada</div>'; return; }
            box.innerHTML = vendas.map(v => `
                <div class="client-search-item" onclick="PDV.carregarVendaTroca(${v.id})">
                    <div class="d-flex justify-content-between">
                        <div style="font-weight:600;">Venda #${v.numero} <small style="color:var(--text-muted); font-weight:400;">${v.data}</small></div>
                        <div style="font-weight:700;">${this.formatMoney(v.total)}</div>
                    </div>
                    <div style="font-size:0.82rem; color:var(--text-muted);">${v.cliente || 'Consumidor'} · ${v.itens} item(ns)${v.mesma_loja ? '' : ' · <span style="color:var(--accent-yellow);">' + (v.loja || 'outra loja') + '</span>'}</div>
                </div>`).join('');
        } catch (err) {
            box.innerHTML = '<div style="padding:12px; color:var(--accent-red);">Erro de conexão</div>';
        }
    },

    async carregarVendaTroca(id) {
        try {
            const resp = await fetch('/app/pdv/troca/venda/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const s = await resp.json();
            if (!resp.ok) { this.showAlert(s.error || 'Erro ao carregar a venda', 'error'); return; }
            this._trocaSituacao = s;
            this.renderTrocaVenda();
        } catch (err) {
            this.showAlert('Erro de conexão: ' + err.message, 'error');
        }
    },

    trocaVoltar() {
        document.getElementById('trocaPasso1').style.display = 'block';
        document.getElementById('trocaPasso2').style.display = 'none';
        document.getElementById('trocaBusca').focus();
    },

    renderTrocaVenda() {
        const s = this._trocaSituacao;
        document.getElementById('trocaPasso1').style.display = 'none';
        document.getElementById('trocaPasso2').style.display = 'block';
        document.getElementById('trocaVendaNumero').textContent = s.venda.numero;
        document.getElementById('trocaVendaInfo').textContent = `${s.venda.data} · ${this.formatMoney(s.venda.total)}${s.venda.loja ? ' · ' + s.venda.loja : ''}`;
        document.getElementById('trocaVendaCliente').textContent = s.venda.cliente ? 'Cliente: ' + s.venda.cliente : 'Consumidor (sem cadastro)';

        // Itens
        document.getElementById('trocaItens').innerHTML = s.itens.map(i => `
            <tr data-item="${i.venda_item_id}" data-unit="${i.valor_unitario}" data-max="${i.disponivel}" ${i.disponivel <= 0 ? 'style="opacity:.45;"' : ''}>
                <td>${i.descricao}${i.devolvida > 0 ? ` <small style="color:var(--accent-yellow);">(${i.devolvida} já devolvido)</small>` : ''}</td>
                <td class="text-end">${i.quantidade}</td>
                <td class="text-end"><input type="number" class="form-control form-control-sm text-end troca-qtd" min="0" max="${i.disponivel}" step="1" value="${i.disponivel > 0 ? i.disponivel : 0}" ${i.disponivel <= 0 ? 'disabled' : ''} oninput="PDV.recalcularTroca()" style="width:90px; display:inline-block;"></td>
                <td class="text-end">${this.formatMoney(i.valor_unitario)}</td>
                <td class="text-center"><input type="checkbox" class="form-check-input troca-estoque" ${i.e_servico ? 'disabled' : 'checked'} title="${i.e_servico ? 'Serviço não volta ao estoque' : 'Desmarque se a peça está avariada e não volta à prateleira'}"></td>
            </tr>`).join('');

        // Motivos
        document.getElementById('trocaMotivo').innerHTML = Object.entries(s.motivos).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
        document.getElementById('trocaMotivoTexto').value = '';

        // Estoques da loja (só quando há mais de um)
        const estoques = this.estoquesLoja || [];
        const estWrap = document.getElementById('trocaEstoqueWrap');
        if (estoques.length > 1) {
            document.getElementById('trocaEstoque').innerHTML = estoques.map(e => `<option value="${e.id}" ${e.permite_venda ? 'selected' : ''}>${e.nome}</option>`).join('');
            estWrap.style.display = 'block';
        } else {
            estWrap.style.display = 'none';
        }

        // Política
        const pol = document.getElementById('trocaPolitica');
        if (!s.pode_trocar) {
            pol.style.display = 'block';
            pol.style.background = 'rgba(239,68,68,.15)'; pol.style.color = 'var(--accent-red)';
            pol.innerHTML = '<i class="bi bi-x-circle me-1"></i> Esta venda não tem itens disponíveis para troca.';
        } else if (s.politica.fora_prazo) {
            pol.style.display = 'block';
            pol.style.background = 'rgba(255,193,7,.12)'; pol.style.color = 'var(--accent-yellow)';
            pol.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> Venda de <strong>${s.politica.dias_desde_venda} dias</strong> — fora do prazo de troca da loja (${s.politica.prazo_dias} dias).` + (s.politica.exige_gerente_fora_prazo ? ' Precisa da autorização de um gerente.' : (s.politica.usuario_e_gerente ? ' Você é gerente: pode autorizar.' : ''));
        } else {
            pol.style.display = 'block';
            pol.style.background = 'rgba(34,197,94,.12)'; pol.style.color = 'var(--accent-green)';
            pol.innerHTML = `<i class="bi bi-check-circle me-1"></i> Dentro do prazo de troca (${s.politica.dias_desde_venda} de ${s.politica.prazo_dias > 0 ? s.politica.prazo_dias : '∞'} dias).`;
        }

        // Sobra (só na devolução)
        document.getElementById('trocaSobraWrap').style.display = 'none';
        document.getElementById('trocaSobraDinheiroOpt').style.display = s.politica.permite_dinheiro ? 'inline-block' : 'none';
        document.querySelector('input[name="trocaSobra"][value="vale"]').checked = true;
        document.querySelectorAll('input[name="trocaSobra"]').forEach(r => r.onchange = () => this.atualizarGerenteTroca());

        // Modo inicial: troca
        this._trocaTipo = 'troca';
        document.getElementById('btnTrocaTrocar').style.display = s.pode_trocar ? 'inline-flex' : 'none';
        document.getElementById('btnTrocaDevolver').style.display = s.pode_trocar ? 'inline-flex' : 'none';
        document.getElementById('btnTrocaConfirmarDevolucao').style.display = 'none';
        document.getElementById('trocaGerenteEmail').value = '';
        document.getElementById('trocaGerenteSenha').value = '';

        this.recalcularTroca();
        this.atualizarGerenteTroca();
    },

    trocaModo(tipo) {
        this._trocaTipo = tipo;
        const dev = tipo === 'devolucao';
        document.getElementById('trocaSobraWrap').style.display = dev ? 'block' : 'none';
        document.getElementById('btnTrocaTrocar').style.display = dev ? 'none' : 'inline-flex';
        document.getElementById('btnTrocaDevolver').style.display = dev ? 'none' : 'inline-flex';
        document.getElementById('btnTrocaConfirmarDevolucao').style.display = dev ? 'inline-flex' : 'none';
        document.getElementById('trocaTitulo').textContent = dev ? 'Devolução (sem levar nada agora)' : 'Troca / Devolução';
        this.atualizarGerenteTroca();
    },

    atualizarGerenteTroca() {
        const s = this._trocaSituacao; if (!s) return;
        const motivos = [];
        if (s.politica.exige_gerente_fora_prazo) motivos.push('fora do prazo');
        const sobra = document.querySelector('input[name="trocaSobra"]:checked')?.value;
        if (this._trocaTipo === 'devolucao' && sobra === 'dinheiro' && s.politica.exige_gerente_dinheiro) motivos.push('devolução em dinheiro');
        const wrap = document.getElementById('trocaGerenteWrap');
        wrap.style.display = motivos.length ? 'block' : 'none';
        document.getElementById('trocaGerenteMotivo').textContent = motivos.length ? motivos.join(' + ') : '';
    },

    recalcularTroca() {
        let total = 0;
        document.querySelectorAll('#trocaItens tr').forEach(tr => {
            const inp = tr.querySelector('.troca-qtd'); if (!inp) return;
            const max = parseFloat(tr.dataset.max) || 0;
            let q = parseFloat(inp.value) || 0;
            if (q > max) { q = max; inp.value = max; }
            if (q < 0) { q = 0; inp.value = 0; }
            total += q * (parseFloat(tr.dataset.unit) || 0);
        });
        total = round(total, 2);
        document.getElementById('trocaTotal').textContent = this.formatMoney(total);
        const s = this._trocaSituacao;
        const aviso = document.getElementById('trocaParcelasAviso');
        if (s && s.parcelas_abertas > 0) {
            aviso.style.display = 'block';
            aviso.innerHTML = `<i class="bi bi-info-circle"></i> Esta venda tem ${this.formatMoney(s.parcelas_abertas)} em parcelas abertas (crediário/boleto): o valor devolvido abate essas parcelas antes de virar crédito.`;
        } else {
            aviso.style.display = 'none';
        }
        return total;
    },

    async registrarTroca(tipo) {
        const s = this._trocaSituacao; if (!s) return;
        const itens = [];
        document.querySelectorAll('#trocaItens tr').forEach(tr => {
            const inp = tr.querySelector('.troca-qtd'); if (!inp) return;
            const q = parseFloat(inp.value) || 0;
            if (q <= 0) return;
            itens.push({
                venda_item_id: parseInt(tr.dataset.item, 10),
                quantidade: q,
                retorna_estoque: tr.querySelector('.troca-estoque')?.checked ? 1 : 0,
                estoque_id: document.getElementById('trocaEstoqueWrap').style.display !== 'none' ? (parseInt(document.getElementById('trocaEstoque').value, 10) || null) : null,
            });
        });
        if (!itens.length) { this.showAlert('Marque a quantidade de pelo menos um item para devolver', 'warning'); return; }

        const payload = {
            venda_id: s.venda.id,
            tipo,
            itens,
            motivo: document.getElementById('trocaMotivo').value,
            motivo_texto: document.getElementById('trocaMotivoTexto').value.trim() || null,
            sobra_destino: tipo === 'devolucao' ? (document.querySelector('input[name="trocaSobra"]:checked')?.value || 'vale') : 'vale',
            gerente_email: document.getElementById('trocaGerenteEmail').value.trim() || null,
            gerente_senha: document.getElementById('trocaGerenteSenha').value || null,
        };

        document.getElementById('loadingOverlay').classList.add('show');
        try {
            const resp = await fetch('{{ route("app.pdv.troca.registrar") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();
            document.getElementById('loadingOverlay').classList.remove('show');
            if (!resp.ok || data.error) {
                const msg = data.error || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Erro ao registrar a troca');
                this.showAlert(msg, 'error');
                return;
            }

            this.lastTrocaHtml = data.comprovante || '';
            document.getElementById('trocaGerenteSenha').value = '';

            if (tipo === 'troca') {
                if (data.vale && data.vale.saldo > 0) {
                    this.creditoTroca = { codigo: data.vale.codigo, saldo: data.vale.saldo, devolucaoId: data.devolucao.id, validade: data.vale.validade };
                    bootstrap.Modal.getInstance(document.getElementById('modalTroca'))?.hide();
                    this.updateSummary();
                    this.showAlert(`Troca da venda #${data.devolucao.venda_numero}: crédito de ${this.formatMoney(data.vale.saldo)}. Bipe o que o cliente leva.`, 'success');
                    document.getElementById('searchInput').focus();
                } else {
                    // Tudo abatido em parcelas abertas: não há crédito para a venda nova
                    document.getElementById('trocaPasso2').style.display = 'none';
                    document.getElementById('trocaPasso3').style.display = 'block';
                    document.getElementById('trocaResultado').innerHTML = `Devolvido ${this.formatMoney(data.devolucao.valor_estornado)}, abatido das parcelas em aberto da venda. Não sobrou crédito — a venda nova é paga normalmente.`;
                }
            } else {
                document.getElementById('trocaPasso2').style.display = 'none';
                document.getElementById('trocaPasso3').style.display = 'block';
                let r = `Devolvido <strong>${this.formatMoney(data.devolucao.valor_estornado)}</strong> da venda #${data.devolucao.venda_numero}.`;
                if (data.devolucao.valor_abatido_parcelas > 0) r += `<br>${this.formatMoney(data.devolucao.valor_abatido_parcelas)} abatidos das parcelas em aberto.`;
                if (data.vale) r += `<br><div style="margin-top:8px; font-size:1.2rem;">Vale <strong>${data.vale.codigo}</strong> — ${this.formatMoney(data.vale.saldo)}${data.vale.validade ? ' · válido até ' + data.vale.validade : ''}</div>`;
                else if (data.devolucao.forma_sobra === 'dinheiro') r += `<br><div style="margin-top:8px; font-size:1.2rem;"><strong>${this.formatMoney(data.devolucao.valor_sobra)} devolvidos em dinheiro</strong> — saída registrada no caixa.</div>`;
                document.getElementById('trocaResultado').innerHTML = r;
                this.imprimirComprovanteTroca();
            }
        } catch (err) {
            document.getElementById('loadingOverlay').classList.remove('show');
            this.showAlert('Erro de conexão: ' + err.message, 'error');
        }
    },

    imprimirComprovanteTroca() {
        if (!this.lastTrocaHtml) return;
        const frame = document.getElementById('printFrame');
        frame.srcdoc = this.lastTrocaHtml;
        frame.onload = () => frame.contentWindow.print();
    },

    decidirSobraTroca(dinheiro) {
        this.valeSobraDinheiro = !!dinheiro;
        bootstrap.Modal.getInstance(document.getElementById('modalSobraTroca'))?.hide();
        this.finalizarVenda(this._docAposSobra || null);
    },

    // ===== VALE (crédito de troca como pagamento) =====
    openVale() {
        if (this.itens.length === 0) {
            this.showAlert('Adicione itens antes de selecionar pagamento', 'warning');
            return;
        }
        this.valeAtual = null;
        const input = document.getElementById('valeCodigo');
        input.value = '';
        document.getElementById('valeInfo').style.display = 'none';
        input.onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); this.confirmarVale(); } };
        new bootstrap.Modal(document.getElementById('modalVale')).show();
        setTimeout(() => input.focus(), 300);
    },

    async confirmarVale() {
        const codigo = document.getElementById('valeCodigo').value.trim();
        if (!codigo) { this.showAlert('Digite ou bipe o código do vale', 'warning'); return; }
        const info = document.getElementById('valeInfo');
        try {
            const resp = await fetch('/app/pdv/vale/' + encodeURIComponent(codigo), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const data = await resp.json();
            if (!resp.ok) {
                info.style.display = 'block';
                info.style.color = 'var(--accent-red)';
                info.textContent = data.error || 'Vale inválido';
                return;
            }
            this.valeAtual = data;
            bootstrap.Modal.getInstance(document.getElementById('modalVale'))?.hide();
            this.showAlert(`Vale ${data.codigo}: saldo ${this.formatMoney(data.saldo)}${data.cliente ? ' · ' + data.cliente : ''}`, 'success');
            this.openPagamento('vale');
        } catch (err) {
            this.showAlert('Erro de conexão: ' + err.message, 'error');
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
    PDV.clienteTipoPreco = 'varejo';
    PDV.repriceItens();
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
