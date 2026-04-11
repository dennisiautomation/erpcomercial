<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci minha senha - ERP Comercial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --surface: rgba(255, 255, 255, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f172a 100%);
            overflow: hidden; position: relative;
        }
        body::before, body::after {
            content: ''; position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.15; z-index: 0;
            animation: float 20s ease-in-out infinite;
        }
        body::before { width: 600px; height: 600px; background: var(--primary); top: -200px; right: -100px; }
        body::after { width: 500px; height: 500px; background: #7c3aed; bottom: -150px; left: -100px; animation-delay: -10s; }
        @keyframes float {
            0%, 100% { transform: translate(0,0) scale(1); }
            33% { transform: translate(30px,-30px) scale(1.05); }
            66% { transform: translate(-20px,20px) scale(0.95); }
        }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
        @keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

        .login-wrapper { position:relative; z-index:1; width:100%; max-width:440px; padding:1rem; animation:fadeInUp 0.6s ease-out; }
        .login-card {
            background: rgba(255,255,255,0.07); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 1.25rem; padding: 2.5rem 2rem 2rem;
            box-shadow: 0 24px 48px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.08);
        }
        .brand { text-align:center; margin-bottom:2rem; }
        .brand-icon {
            width:64px; height:64px; background:linear-gradient(135deg,var(--primary),var(--primary-light));
            border-radius:1rem; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1rem;
            box-shadow:0 8px 24px rgba(37,99,235,0.35);
        }
        .brand-icon i { font-size:1.75rem; color:#fff; }
        .brand h1 { font-size:1.5rem; font-weight:700; color:#f1f5f9; margin:0 0 0.25rem; letter-spacing:-0.025em; }
        .brand p { color:#94a3b8; font-size:0.875rem; margin:0; }

        .login-alert { background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.25); border-radius:0.75rem; padding:0.75rem 1rem; margin-bottom:1.25rem; animation:slideDown 0.3s ease-out; }
        .login-alert .alert-text { color:#fca5a5; font-size:0.85rem; margin:0; display:flex; align-items:center; gap:0.5rem; }

        .form-group { margin-bottom:1.25rem; }
        .form-group label { display:block; font-size:0.8rem; font-weight:600; color:#cbd5e1; margin-bottom:0.5rem; text-transform:uppercase; letter-spacing:0.05em; }
        .input-wrapper { position:relative; }
        .input-wrapper i.field-icon { position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#64748b; font-size:1rem; transition:color 0.2s; z-index:2; }
        .input-wrapper input {
            width:100%; padding:0.8rem 1rem 0.8rem 2.75rem; background:rgba(255,255,255,0.06);
            border:1px solid rgba(255,255,255,0.1); border-radius:0.75rem; color:#f1f5f9; font-size:0.95rem;
            transition:all 0.2s ease; outline:none;
        }
        .input-wrapper input::placeholder { color:#475569; }
        .input-wrapper input:focus { border-color:var(--primary-light); background:rgba(255,255,255,0.09); box-shadow:0 0 0 3px rgba(37,99,235,0.15); }
        .input-wrapper input:focus ~ i.field-icon { color:var(--primary-light); }
        .input-wrapper input.is-invalid { border-color:rgba(239,68,68,0.5); }

        .field-error { font-size:0.8rem; color:#f87171; margin-top:0.4rem; display:flex; align-items:center; gap:0.3rem; animation:slideDown 0.25s ease-out; }

        .btn-login {
            width:100%; padding:0.85rem; font-size:0.95rem; font-weight:600; color:#fff;
            background:linear-gradient(135deg,var(--primary),var(--primary-dark)); border:none; border-radius:0.75rem;
            cursor:pointer; display:flex; align-items:center; justify-content:center; gap:0.5rem;
            transition:all 0.25s ease; box-shadow:0 4px 16px rgba(37,99,235,0.3);
        }
        .btn-login:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(37,99,235,0.4); background:linear-gradient(135deg,var(--primary-light),var(--primary)); }

        .login-footer { text-align:center; margin-top:1.5rem; color:#475569; font-size:0.8rem; }
        .back-link { color:#94a3b8; font-size:0.85rem; text-decoration:none; transition:color 0.2s; }
        .back-link:hover { color:#cbd5e1; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="brand">
                <div class="brand-icon">
                    <i class="bi bi-key"></i>
                </div>
                <h1>Recuperar Senha</h1>
                <p>Informe seu e-mail para redefinir sua senha</p>
            </div>

            @if($errors->any())
                <div class="login-alert">
                    <p class="alert-text">
                        <i class="bi bi-exclamation-circle"></i>
                        {{ $errors->first() }}
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <div class="input-wrapper">
                        <i class="bi bi-envelope field-icon"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="seu@email.com"
                            autocomplete="email"
                            required
                            autofocus
                            class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
                        >
                    </div>
                    @error('email')
                        <div class="field-error">
                            <i class="bi bi-exclamation-circle" style="font-size:0.75rem"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <button type="submit" class="btn-login">
                    <span>Enviar Link de Recuperacao</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="{{ route('login') }}" class="back-link">
                    <i class="bi bi-arrow-left me-1"></i>Voltar ao login
                </a>
            </div>
        </div>

        <div class="login-footer">
            &copy; {{ date('Y') }} ERP Comercial &mdash; Todos os direitos reservados
        </div>
    </div>
</body>
</html>
