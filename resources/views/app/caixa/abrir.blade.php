<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrir Caixa - PDV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --border: #475569;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-green: #22c55e;
            --accent-blue: #3b82f6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .caixa-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }

        .caixa-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        .caixa-icon i {
            font-size: 3.5rem;
            color: var(--accent-green);
            background: rgba(34,197,94,0.12);
            width: 80px;
            height: 80px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
        }

        .caixa-card h2 {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .caixa-card .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 28px;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .form-group input, .form-group select {
            width: 100%;
            background: var(--bg-primary);
            border: 2px solid var(--border);
            color: var(--text-primary);
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            text-align: center;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .form-group .hint {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .form-group .input-prefix {
            position: relative;
        }
        .form-group .input-prefix span {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 1.1rem;
        }
        .form-group .input-prefix input {
            padding-left: 50px;
        }

        .info-box {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 24px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            padding: 3px 0;
        }
        .info-row .label { color: var(--text-muted); }
        .info-row .value { color: var(--text-primary); font-weight: 600; }

        .btn-abrir {
            width: 100%;
            padding: 16px;
            background: var(--accent-green);
            color: #000;
            border: none;
            border-radius: 14px;
            font-size: 1.15rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-abrir:hover { background: #16a34a; transform: translateY(-1px); box-shadow: 0 4px 15px rgba(34,197,94,0.3); }

        .btn-voltar {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.88rem;
            transition: color 0.15s;
        }
        .btn-voltar:hover { color: var(--text-secondary); }

        .error-msg {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.3);
            color: #ef4444;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 16px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="caixa-card">
    <div class="caixa-icon">
        <i class="bi bi-unlock"></i>
    </div>

    <h2>Abrir Caixa</h2>
    <p class="subtitle">Informe os dados para iniciar as vendas</p>

    @if(session('error'))
        <div class="error-msg">
            <i class="bi bi-exclamation-circle me-1"></i> {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('app.caixa.abrir') }}">
        @csrf

        <div class="form-group">
            <label>Numero do Caixa</label>
            <input type="number" name="numero_caixa" value="{{ old('numero_caixa', 1) }}"
                min="1" required autofocus>
            @error('numero_caixa')
                <div class="error-msg" style="margin-top:6px; margin-bottom:0;">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label>Valor de Abertura (Troco Inicial)</label>
            <div class="input-prefix">
                <span>R$</span>
                <input type="number" name="valor_abertura" value="{{ old('valor_abertura', '0.00') }}"
                    step="0.01" min="0" required>
            </div>
            <div class="hint">Valor em dinheiro disponivel no caixa</div>
            @error('valor_abertura')
                <div class="error-msg" style="margin-top:6px; margin-bottom:0;">{{ $message }}</div>
            @enderror
        </div>

        <div class="info-box">
            <div class="info-row">
                <span class="label">Operador</span>
                <span class="value">{{ auth()->user()->name }}</span>
            </div>
            <div class="info-row">
                <span class="label">Data / Hora</span>
                <span class="value">{{ now()->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        <button type="submit" class="btn-abrir">
            <i class="bi bi-unlock"></i> Abrir Caixa
        </button>

        <a href="{{ route('app.dashboard') }}" class="btn-voltar">
            <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
        </a>
    </form>
</div>

</body>
</html>
