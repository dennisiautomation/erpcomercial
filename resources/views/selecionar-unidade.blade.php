<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Unidade - ERP Comercial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f172a 100%);
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient background */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.1;
            z-index: 0;
            animation: float 20s ease-in-out infinite;
        }
        body::before {
            width: 500px;
            height: 500px;
            background: var(--primary);
            top: -150px;
            right: -100px;
        }
        body::after {
            width: 400px;
            height: 400px;
            background: #7c3aed;
            bottom: -100px;
            left: -100px;
            animation-delay: -10s;
        }
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        /* Header */
        .page-header {
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeInUp 0.5s ease-out;
        }
        .header-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.3);
        }
        .header-icon i {
            font-size: 1.5rem;
            color: #fff;
        }
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0 0 0.4rem;
            letter-spacing: -0.025em;
        }
        .page-header p {
            color: #94a3b8;
            font-size: 0.95rem;
            margin: 0;
        }
        .page-header .user-name {
            color: #e2e8f0;
            font-weight: 600;
        }

        /* Alert */
        .page-alert {
            max-width: 720px;
            width: 100%;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            animation: slideDown 0.3s ease-out;
        }
        .page-alert p {
            color: #fca5a5;
            font-size: 0.85rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-alert {
            background: rgba(234, 179, 8, 0.12);
            border-color: rgba(234, 179, 8, 0.25);
        }
        .warning-alert p {
            color: #fde68a;
        }

        /* Grid */
        .units-grid {
            max-width: 720px;
            width: 100%;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1rem;
        }

        /* Unit card */
        .unit-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1rem;
            padding: 0;
            cursor: pointer;
            transition: all 0.25s ease;
            animation: fadeInUp 0.5s ease-out backwards;
            position: relative;
            overflow: hidden;
        }
        .unit-card:nth-child(1) { animation-delay: 0.05s; }
        .unit-card:nth-child(2) { animation-delay: 0.1s; }
        .unit-card:nth-child(3) { animation-delay: 0.15s; }
        .unit-card:nth-child(4) { animation-delay: 0.2s; }
        .unit-card:nth-child(5) { animation-delay: 0.25s; }
        .unit-card:nth-child(6) { animation-delay: 0.3s; }

        .unit-card:hover {
            border-color: rgba(37, 99, 235, 0.4);
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(37, 99, 235, 0.2);
        }
        .unit-card:active {
            transform: translateY(-1px);
        }

        .unit-card-btn {
            display: flex;
            align-items: center;
            gap: 1rem;
            width: 100%;
            padding: 1.25rem;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
        }

        .unit-icon {
            width: 48px;
            height: 48px;
            background: rgba(37, 99, 235, 0.15);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }
        .unit-icon i {
            font-size: 1.25rem;
            color: var(--primary-light);
            transition: color 0.25s;
        }
        .unit-card:hover .unit-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .unit-card:hover .unit-icon i {
            color: #fff;
        }

        .unit-info {
            flex: 1;
            min-width: 0;
        }
        .unit-name {
            font-size: 1rem;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0 0 0.25rem;
            line-height: 1.3;
        }
        .unit-detail {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            color: #94a3b8;
            font-size: 0.8rem;
            margin-top: 0.15rem;
        }
        .unit-detail i {
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .unit-empresa {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 0.375rem;
            padding: 0.15rem 0.5rem;
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.4rem;
        }

        .unit-arrow {
            flex-shrink: 0;
            color: #475569;
            font-size: 1.1rem;
            transition: all 0.25s ease;
        }
        .unit-card:hover .unit-arrow {
            color: var(--primary-light);
            transform: translateX(3px);
        }

        /* Loading overlay for unit card */
        .unit-card.is-loading {
            pointer-events: none;
        }
        .unit-card.is-loading::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
        }
        .unit-card.is-loading .unit-arrow {
            visibility: hidden;
        }
        .unit-card.is-loading .unit-spinner {
            display: block;
        }
        .unit-spinner {
            display: none;
            width: 22px;
            height: 22px;
            border: 2.5px solid rgba(255, 255, 255, 0.15);
            border-top-color: var(--primary-light);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            flex-shrink: 0;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Logout */
        .logout-section {
            margin-top: 2rem;
            text-align: center;
            animation: fadeInUp 0.5s ease-out 0.35s backwards;
        }
        .btn-logout {
            background: none;
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #64748b;
            padding: 0.5rem 1.25rem;
            border-radius: 0.625rem;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-family: inherit;
        }
        .btn-logout:hover {
            color: #e2e8f0;
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.04);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .units-grid {
                grid-template-columns: 1fr;
            }
            .page-header h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        {{-- Header --}}
        <div class="page-header">
            <div class="header-icon">
                <i class="bi bi-building"></i>
            </div>
            <h1>Selecionar Unidade</h1>
            <p>
                Ola, <span class="user-name">{{ auth()->user()->name }}</span>!
                Escolha a unidade para continuar.
            </p>
        </div>

        {{-- Errors --}}
        @if($errors->any())
            <div class="page-alert">
                @foreach($errors->all() as $error)
                    <p><i class="bi bi-exclamation-circle"></i> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Units --}}
        @if($unidades->isEmpty())
            <div class="page-alert warning-alert">
                <p>
                    <i class="bi bi-exclamation-triangle"></i>
                    Nenhuma unidade disponivel. Entre em contato com o administrador.
                </p>
            </div>
        @else
            <div class="units-grid">
                @foreach($unidades as $unidade)
                    <div class="unit-card" data-unit-id="{{ $unidade->id }}">
                        <form method="POST" action="{{ route('selecionar-unidade.store') }}">
                            @csrf
                            <input type="hidden" name="unidade_id" value="{{ $unidade->id }}">
                            <button type="submit" class="unit-card-btn">
                                <div class="unit-icon">
                                    <i class="bi bi-shop"></i>
                                </div>
                                <div class="unit-info">
                                    <h3 class="unit-name">{{ $unidade->nome }}</h3>
                                    @if($unidade->cidade && $unidade->uf)
                                        <div class="unit-detail">
                                            <i class="bi bi-geo-alt-fill"></i>
                                            {{ $unidade->cidade }}/{{ $unidade->uf }}
                                        </div>
                                    @endif
                                    @if($unidade->telefone)
                                        <div class="unit-detail">
                                            <i class="bi bi-telephone-fill"></i>
                                            {{ $unidade->telefone }}
                                        </div>
                                    @endif
                                    @if($unidade->endereco)
                                        <div class="unit-detail">
                                            <i class="bi bi-pin-map-fill"></i>
                                            {{ $unidade->endereco }}
                                        </div>
                                    @endif
                                    @if($unidade->relationLoaded('empresa') && $unidade->empresa)
                                        <div class="unit-empresa">
                                            <i class="bi bi-briefcase-fill"></i>
                                            {{ $unidade->empresa->nome_fantasia ?? $unidade->empresa->razao_social }}
                                        </div>
                                    @endif
                                </div>
                                <i class="bi bi-chevron-right unit-arrow"></i>
                                <div class="unit-spinner"></div>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Logout --}}
        <div class="logout-section">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">
                    <i class="bi bi-box-arrow-left"></i>
                    Sair da conta
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Add loading state when a unit card form is submitted
            document.querySelectorAll('.unit-card form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    var card = this.closest('.unit-card');
                    card.classList.add('is-loading');
                    // Disable all other cards
                    document.querySelectorAll('.unit-card').forEach(function (c) {
                        if (c !== card) {
                            c.style.opacity = '0.4';
                            c.style.pointerEvents = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
