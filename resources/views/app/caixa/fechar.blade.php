<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fechar Caixa - PDV</title>
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
            --accent-red: #ef4444;
            --accent-yellow: #eab308;
            --accent-cyan: #06b6d4;
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
            padding: 20px;
        }

        .fechar-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px;
            width: 100%;
            max-width: 560px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }

        .fechar-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .fechar-header i {
            font-size: 2.5rem;
            color: var(--accent-red);
            background: rgba(239,68,68,0.12);
            width: 64px;
            height: 64px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            margin-bottom: 12px;
        }
        .fechar-header h2 { font-size: 1.4rem; font-weight: 700; }
        .fechar-header .caixa-info { color: var(--text-muted); font-size: 0.85rem; }

        /* Resumo grid */
        .resumo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 20px;
        }
        .resumo-item {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 14px;
            text-align: center;
        }
        .resumo-item .label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .resumo-item .value {
            font-size: 1.15rem;
            font-weight: 700;
        }
        .resumo-item.abertura .value { color: var(--text-primary); }
        .resumo-item.vendas .value { color: var(--accent-green); }
        .resumo-item.suprimentos .value { color: var(--accent-cyan); }
        .resumo-item.sangrias .value { color: var(--accent-red); }

        /* Valor esperado */
        .valor-esperado {
            background: var(--bg-primary);
            border: 2px solid var(--accent-blue);
            border-radius: 14px;
            padding: 16px;
            text-align: center;
            margin-bottom: 24px;
        }
        .valor-esperado .label {
            font-size: 0.82rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .valor-esperado .amount {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--accent-blue);
            font-variant-numeric: tabular-nums;
        }

        /* Form */
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .form-group input, .form-group textarea {
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
        .form-group textarea {
            text-align: left;
            font-size: 0.9rem;
            font-weight: 400;
            resize: vertical;
            min-height: 60px;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
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

        /* Diferenca */
        .diferenca-box {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .diferenca-box .label { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; }
        .diferenca-box .value { font-size: 1.3rem; font-weight: 700; }
        .diferenca-box .detail { font-size: 0.82rem; margin-top: 2px; }
        .diferenca-box.ok { border-color: var(--accent-green); }
        .diferenca-box.ok .value { color: var(--accent-green); }
        .diferenca-box.ok .detail { color: var(--accent-green); }
        .diferenca-box.sobra { border-color: var(--accent-yellow); }
        .diferenca-box.sobra .value { color: var(--accent-yellow); }
        .diferenca-box.sobra .detail { color: var(--accent-yellow); }
        .diferenca-box.falta { border-color: var(--accent-red); }
        .diferenca-box.falta .value { color: var(--accent-red); }
        .diferenca-box.falta .detail { color: var(--accent-red); }

        /* Buttons */
        .btn-fechar {
            width: 100%;
            padding: 16px;
            background: var(--accent-red);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
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
        .btn-fechar:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 15px rgba(239,68,68,0.3); }

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

        .time-info {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 20px;
            font-size: 0.82rem;
            color: var(--text-muted);
        }
        .time-info .value { color: var(--text-primary); font-weight: 600; }
    </style>
</head>
<body>

<div class="fechar-card">
    <div class="fechar-header">
        <i class="bi bi-lock"></i>
        <h2>Fechamento de Caixa</h2>
        <div class="caixa-info">Caixa #{{ $caixa->numero_caixa }} | Operador: {{ $caixa->operador->name ?? auth()->user()->name }}</div>
    </div>

    <div class="time-info">
        <span><i class="bi bi-clock me-1"></i> Aberto: <span class="value">{{ $caixa->aberto_em?->format('d/m/Y H:i') }}</span></span>
        <span><i class="bi bi-clock-history me-1"></i> Agora: <span class="value">{{ now()->format('d/m/Y H:i') }}</span></span>
    </div>

    @if(session('error'))
        <div class="error-msg">
            <i class="bi bi-exclamation-circle me-1"></i> {{ session('error') }}
        </div>
    @endif

    {{-- Resumo de movimentacoes --}}
    <div class="resumo-grid">
        <div class="resumo-item abertura">
            <div class="label"><i class="bi bi-unlock me-1"></i> Abertura</div>
            <div class="value">R$ {{ number_format($resumo['abertura'], 2, ',', '.') }}</div>
        </div>
        <div class="resumo-item vendas">
            <div class="label"><i class="bi bi-bag-check me-1"></i> Vendas</div>
            <div class="value">+ R$ {{ number_format($resumo['vendas'], 2, ',', '.') }}</div>
        </div>
        <div class="resumo-item suprimentos">
            <div class="label"><i class="bi bi-arrow-up-circle me-1"></i> Suprimentos</div>
            <div class="value">+ R$ {{ number_format($resumo['suprimentos'], 2, ',', '.') }}</div>
        </div>
        <div class="resumo-item sangrias">
            <div class="label"><i class="bi bi-arrow-down-circle me-1"></i> Sangrias</div>
            <div class="value">- R$ {{ number_format($resumo['sangrias'], 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- Valor esperado --}}
    <div class="valor-esperado">
        <div class="label">Valor Esperado no Caixa</div>
        <div class="amount">R$ {{ number_format($valorEsperado, 2, ',', '.') }}</div>
    </div>

    {{-- Formulario --}}
    <form method="POST" action="{{ route('app.caixa.fechar') }}" data-confirm="Confirmar fechamento do caixa?">
        @csrf

        <div class="form-group">
            <label>Valor Contado (em caixa)</label>
            <div class="input-prefix">
                <span>R$</span>
                <input type="number" name="valor_contado" id="valorContado"
                    step="0.01" min="0" required autofocus
                    value="{{ old('valor_contado') }}">
            </div>
            @error('valor_contado')
                <div class="error-msg" style="margin-top:6px; margin-bottom:0;">{{ $message }}</div>
            @enderror
        </div>

        <div class="diferenca-box" id="diferencaBox">
            <div class="label">Diferenca</div>
            <div class="value" id="diferencaValue">R$ 0,00</div>
            <div class="detail" id="diferencaDetail">Informe o valor contado</div>
        </div>

        <div class="form-group">
            <label>Observacoes (opcional)</label>
            <textarea name="observacoes" placeholder="Observacoes do fechamento...">{{ old('observacoes') }}</textarea>
        </div>

        <button type="submit" class="btn-fechar">
            <i class="bi bi-lock"></i> Fechar Caixa
        </button>

        <a href="{{ route('app.pdv.index') }}" class="btn-voltar">
            <i class="bi bi-arrow-left"></i> Voltar ao PDV
        </a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const valorEsperado = {{ $valorEsperado }};
    const contadoInput = document.getElementById('valorContado');
    const box = document.getElementById('diferencaBox');
    const valueEl = document.getElementById('diferencaValue');
    const detailEl = document.getElementById('diferencaDetail');

    function formatMoney(val) {
        return 'R$ ' + Math.abs(val).toFixed(2).replace('.', ',');
    }

    contadoInput.addEventListener('input', function() {
        const contado = parseFloat(this.value) || 0;
        const diff = contado - valorEsperado;

        box.classList.remove('ok', 'sobra', 'falta');

        if (Math.abs(diff) < 0.02) {
            valueEl.textContent = 'R$ 0,00';
            detailEl.textContent = 'Caixa confere!';
            box.classList.add('ok');
        } else if (diff > 0) {
            valueEl.textContent = '+ ' + formatMoney(diff);
            detailEl.textContent = 'Sobra de ' + formatMoney(diff);
            box.classList.add('sobra');
        } else {
            valueEl.textContent = '- ' + formatMoney(diff);
            detailEl.textContent = 'Falta de ' + formatMoney(diff);
            box.classList.add('falta');
        }
    });
});
</script>
</body>
</html>
