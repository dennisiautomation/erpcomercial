<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - ERP Comercial</title>
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
        .success-alert { background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.25); }
        .success-alert .alert-text { color:#86efac; }

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

        .toggle-password {
            position:absolute; right:0.75rem; top:50%; transform:translateY(-50%); background:none; border:none;
            color:#64748b; cursor:pointer; padding:0.25rem; font-size:1rem; z-index:2; transition:color 0.2s;
        }
        .toggle-password:hover { color:#94a3b8; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="brand">
                <div class="brand-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h1>Nova Senha</h1>
                <p>Defina sua nova senha de acesso</p>
            </div>

            @if($errors->any())
                <div class="login-alert">
                    <p class="alert-text">
                        <i class="bi bi-exclamation-circle"></i>
                        {{ $errors->first() }}
                    </p>
                </div>
            @endif

            @if(session('email'))
                <div class="login-alert success-alert">
                    <p class="alert-text">
                        <i class="bi bi-check-circle"></i>
                        Link gerado para {{ session('email') }}. Preencha abaixo para redefinir.
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <div class="input-wrapper">
                        <i class="bi bi-envelope field-icon"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ session('email', old('email')) }}"
                            placeholder="seu@email.com"
                            autocomplete="email"
                            required
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

                <div class="form-group">
                    <label for="password">Nova Senha</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock field-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Minimo 8 caracteres"
                            autocomplete="new-password"
                            required
                            class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                        >
                        <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar senha" onclick="togglePass('password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="field-error">
                            <i class="bi bi-exclamation-circle" style="font-size:0.75rem"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirmar Senha</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock-fill field-icon"></i>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            placeholder="Repita a nova senha"
                            autocomplete="new-password"
                            required
                        >
                        <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar senha" onclick="togglePass('password_confirmation', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <span>Redefinir Senha</span>
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

    <script>
        function togglePass(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
</body>
</html>
