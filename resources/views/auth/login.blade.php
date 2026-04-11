<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP Comercial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --surface: rgba(255, 255, 255, 0.12);
            --surface-hover: rgba(255, 255, 255, 0.18);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f172a 100%);
            overflow: hidden;
            position: relative;
        }

        /* Animated background orbs */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            z-index: 0;
            animation: float 20s ease-in-out infinite;
        }
        body::before {
            width: 600px;
            height: 600px;
            background: var(--primary);
            top: -200px;
            right: -100px;
        }
        body::after {
            width: 500px;
            height: 500px;
            background: #7c3aed;
            bottom: -150px;
            left: -100px;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            padding: 1rem;
            animation: fadeInUp 0.6s ease-out;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.25rem;
            padding: 2.5rem 2rem 2rem;
            box-shadow:
                0 24px 48px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        /* Branding */
        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35);
        }
        .brand-icon i {
            font-size: 1.75rem;
            color: #fff;
        }
        .brand h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0 0 0.25rem;
            letter-spacing: -0.025em;
        }
        .brand p {
            color: #94a3b8;
            font-size: 0.875rem;
            margin: 0;
        }

        /* Alert */
        .login-alert {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            animation: slideDown 0.3s ease-out;
        }
        .login-alert .alert-text {
            color: #fca5a5;
            font-size: 0.85rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .login-alert .alert-text i {
            flex-shrink: 0;
        }

        .success-alert {
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.25);
        }
        .success-alert .alert-text {
            color: #86efac;
        }

        /* Form fields */
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper i.field-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1rem;
            transition: color 0.2s;
            z-index: 2;
        }
        .input-wrapper input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.75rem;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            color: #f1f5f9;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            outline: none;
        }
        .input-wrapper input::placeholder {
            color: #475569;
        }
        .input-wrapper input:focus {
            border-color: var(--primary-light);
            background: rgba(255, 255, 255, 0.09);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .input-wrapper input:focus ~ i.field-icon {
            color: var(--primary-light);
        }
        .input-wrapper input.is-invalid {
            border-color: rgba(239, 68, 68, 0.5);
        }
        .input-wrapper input.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
        }

        /* Toggle password */
        .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 0.25rem;
            font-size: 1rem;
            z-index: 2;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: #94a3b8;
        }

        /* Inline field error */
        .field-error {
            font-size: 0.8rem;
            color: #f87171;
            margin-top: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            animation: slideDown 0.25s ease-out;
        }

        /* Checkbox */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .custom-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        .custom-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
            border-radius: 4px;
        }
        .custom-check span {
            font-size: 0.875rem;
            color: #94a3b8;
            user-select: none;
        }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
            position: relative;
            overflow: hidden;
        }
        .btn-login:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
        }
        .btn-login:active:not(:disabled) {
            transform: translateY(0);
        }
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2.5px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #475569;
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 2rem 1.5rem 1.5rem;
            }
            .brand-icon {
                width: 56px;
                height: 56px;
            }
            .brand h1 {
                font-size: 1.35rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            {{-- Branding --}}
            <div class="brand">
                <div class="brand-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h1>ERP Comercial</h1>
                <p>Acesse sua conta para continuar</p>
            </div>

            {{-- Flash error from session (e.g. forced logout) --}}
            @if(session('error'))
                <div class="login-alert">
                    <p class="alert-text">
                        <i class="bi bi-exclamation-circle"></i>
                        {{ session('error') }}
                    </p>
                </div>
            @endif

            @if(session('success'))
                <div class="login-alert success-alert">
                    <p class="alert-text">
                        <i class="bi bi-check-circle"></i>
                        {{ session('success') }}
                    </p>
                </div>
            @endif

            {{-- Validation error banner (only for general/credential errors) --}}
            @if($errors->has('email') && !old('_show_inline'))
                <div class="login-alert">
                    <p class="alert-text">
                        <i class="bi bi-exclamation-circle"></i>
                        {{ $errors->first('email') }}
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" id="loginForm" novalidate>
                @csrf

                {{-- Email --}}
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
                    <div class="field-error" id="emailError" style="display: none;"></div>
                </div>

                {{-- Password --}}
                <div class="form-group">
                    <label for="password">Senha</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock field-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Digite sua senha"
                            autocomplete="current-password"
                            required
                            class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                        >
                        <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="field-error">
                            <i class="bi bi-exclamation-circle" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div>
                    @enderror
                    <div class="field-error" id="passwordError" style="display: none;"></div>
                </div>

                {{-- Remember me --}}
                <div class="remember-row">
                    <label class="custom-check">
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        <span>Lembrar-me</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="btn-text">Entrar</span>
                    <i class="bi bi-arrow-right"></i>
                </button>

                <div class="text-center mt-3">
                    <a href="{{ route('password.request') }}" style="color:#94a3b8;font-size:0.85rem;text-decoration:none;">Esqueci minha senha</a>
                </div>
            </form>
        </div>

        <div class="login-footer">
            &copy; {{ date('Y') }} ERP Comercial &mdash; Todos os direitos reservados
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('loginForm');
            const btn = document.getElementById('btnLogin');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const emailError = document.getElementById('emailError');
            const passwordError = document.getElementById('passwordError');
            const toggleBtn = document.querySelector('.toggle-password');

            // Toggle password visibility
            toggleBtn.addEventListener('click', function () {
                const input = document.getElementById('password');
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });

            // Clear inline errors on input
            emailInput.addEventListener('input', function () {
                this.classList.remove('is-invalid');
                emailError.style.display = 'none';
            });
            passwordInput.addEventListener('input', function () {
                this.classList.remove('is-invalid');
                passwordError.style.display = 'none';
            });

            // Client-side validation + loading state
            form.addEventListener('submit', function (e) {
                let valid = true;

                // Email validation
                const email = emailInput.value.trim();
                if (!email) {
                    showError(emailInput, emailError, 'Informe seu e-mail.');
                    valid = false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showError(emailInput, emailError, 'Formato de e-mail invalido.');
                    valid = false;
                }

                // Password validation
                if (!passwordInput.value) {
                    showError(passwordInput, passwordError, 'Informe sua senha.');
                    valid = false;
                }

                if (!valid) {
                    e.preventDefault();
                    return;
                }

                // Show loading state
                btn.disabled = true;
                btn.innerHTML = '<div class="spinner"></div><span>Entrando...</span>';
            });

            function showError(input, errorEl, msg) {
                input.classList.add('is-invalid');
                errorEl.innerHTML = '<i class="bi bi-exclamation-circle" style="font-size:0.75rem"></i> ' + msg;
                errorEl.style.display = 'flex';
            }
        });
    </script>
</body>
</html>
