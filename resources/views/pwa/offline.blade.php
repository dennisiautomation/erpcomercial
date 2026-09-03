<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sem conexão — ERP Comercial</title>
    <link rel="icon" href="/pwa/icone-192.png" type="image/png">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #1e293b;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            padding: 24px;
        }
        .caixa {
            max-width: 420px;
            text-align: center;
            background: #fff;
            border-radius: 18px;
            padding: 40px 32px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, .08);
        }
        .marca {
            width: 64px; height: 64px;
            border-radius: 16px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p  { margin: 0 0 24px; color: #64748b; font-size: .95rem; line-height: 1.5; }
        button {
            border: 0; border-radius: 10px; cursor: pointer;
            background: #6366f1; color: #fff;
            padding: 11px 22px; font-size: .95rem; font-weight: 600;
        }
        button:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class="caixa">
        <div class="marca"></div>
        <h1>Você está sem internet</h1>
        <p>
            O ERP precisa de conexão para carregar suas vendas, produtos e caixa.
            Assim que a internet voltar, é só tentar de novo — nada do que você
            já salvou foi perdido.
        </p>
        <button onclick="location.reload()">Tentar de novo</button>
    </div>
    <script>
        window.addEventListener('online', function () { location.reload(); });
    </script>
</body>
</html>
