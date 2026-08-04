<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Suspenso - ERP Comercial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f172a 100%);
            position: relative;
        }
        .wrapper { position: relative; z-index: 1; width: 100%; max-width: 520px; padding: 1rem; }
        .card-suspenso {
            background: rgba(255,255,255,0.07); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 1.25rem; padding: 2.5rem 2rem 2rem;
            box-shadow: 0 24px 48px rgba(0,0,0,0.4); text-align: center;
        }
        .icone {
            width: 72px; height: 72px; border-radius: 1.1rem; margin: 0 auto 1.25rem;
            background: linear-gradient(135deg, #dc2626, #ef4444);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 24px rgba(220,38,38,0.35);
        }
        .icone i { font-size: 2rem; color: #fff; }
        h1 { font-size: 1.4rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.5rem; }
        p.sub { color: #94a3b8; font-size: 0.95rem; margin: 0 0 1.5rem; line-height: 1.7; }
        .faturas {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 0.75rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem; text-align: left;
        }
        .faturas .item { display: flex; justify-content: space-between; padding: 0.4rem 0; color: #cbd5e1; font-size: 0.9rem; }
        .faturas .item + .item { border-top: 1px solid rgba(255,255,255,0.06); }
        .contato { color: #94a3b8; font-size: 0.85rem; margin-bottom: 1.5rem; }
        .contato a { color: #60a5fa; text-decoration: none; }
        .btn-sair {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: #e2e8f0;
            padding: 0.6rem 2rem; border-radius: 0.6rem; font-weight: 600; font-size: 0.9rem; cursor: pointer;
        }
        .btn-sair:hover { background: rgba(255,255,255,0.14); color: #fff; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card-suspenso">
            <div class="icone"><i class="bi bi-lock-fill"></i></div>
            <h1>Acesso temporariamente suspenso</h1>
            <p class="sub">
                O acesso da empresa <strong style="color:#e2e8f0;">{{ $empresa->nome_fantasia ?: $empresa->razao_social }}</strong>
                ao ERP Comercial está suspenso por pendência financeira junto à IA365.
            </p>

            @if(auth()->user()->perfil === \App\Enums\Perfil::Dono && $faturas->isNotEmpty())
                <div class="faturas">
                    <div style="color:#94a3b8;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">
                        Pendências em aberto
                    </div>
                    @foreach($faturas as $fatura)
                        <div class="item">
                            <span>{{ $fatura->descricao ?: 'Mensalidade ' . $fatura->competencia }}</span>
                            <span>
                                <strong>R$ {{ number_format($fatura->valor, 2, ',', '.') }}</strong>
                                <small style="color:#f87171;"> · venceu {{ $fatura->vencimento->format('d/m/Y') }}</small>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="contato">
                Para regularizar e reativar o acesso imediatamente, fale com a IA365:<br>
                <a href="mailto:dcanteli@ia365.com.br">dcanteli@ia365.com.br</a>
            </p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-sair"><i class="bi bi-box-arrow-right me-1"></i> Sair</button>
            </form>
        </div>
    </div>
</body>
</html>
