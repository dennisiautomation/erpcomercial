<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ERP Comercial')</title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #1e293b;
            --sidebar-hover: rgba(255, 255, 255, 0.08);
            --sidebar-active: rgba(255, 255, 255, 0.15);
            --sidebar-text: rgba(255, 255, 255, 0.7);
            --sidebar-text-active: #ffffff;
            --sidebar-heading: rgba(255, 255, 255, 0.4);
            --topbar-height: 56px;
        }

        body {
            background-color: #f1f5f9;
            overflow-x: hidden;
        }

        /* ---- Sidebar ---- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            z-index: 1040;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-brand a {
            color: #fff;
            text-decoration: none;
            font-size: 1.15rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-nav {
            padding: 0.75rem 0.5rem;
        }

        .sidebar-heading {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--sidebar-heading);
            padding: 0.75rem 0.75rem 0.35rem;
        }

        .sidebar .nav-link {
            color: var(--sidebar-text);
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.15s ease;
        }

        .sidebar .nav-link:hover {
            color: var(--sidebar-text-active);
            background: var(--sidebar-hover);
        }

        .sidebar .nav-link.active {
            color: var(--sidebar-text-active);
            background: var(--sidebar-active);
        }

        .sidebar .nav-link i {
            font-size: 1.1rem;
            width: 1.25rem;
            text-align: center;
        }

        .sidebar .submenu {
            padding-left: 1rem;
        }

        .sidebar .submenu .nav-link {
            font-size: 0.8125rem;
            padding: 0.35rem 0.75rem;
        }

        /* ---- Main Area ---- */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .topbar {
            height: var(--topbar-height);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .main-content {
            padding: 1.5rem;
        }

        .stat-card {
            border: none;
            border-radius: 0.75rem;
            transition: transform 0.15s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        /* ---- Sidebar backdrop (mobile) ---- */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1035;
        }

        /* ---- Responsive ---- */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-backdrop.show {
                display: block;
            }

            .main-wrapper {
                margin-left: 0;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    {{-- Sidebar Backdrop (mobile) --}}
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

    {{-- Sidebar --}}
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            @if(auth()->user()->is_admin)
                <a href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-box-seam"></i>
                    <span>ERP Admin</span>
                </a>
            @else
                <a href="{{ route('app.dashboard') }}">
                    <i class="bi bi-box-seam"></i>
                    <span>ERP Comercial</span>
                </a>
            @endif
        </div>

        <div class="sidebar-nav">
            @if(auth()->user()->is_admin)
                {{-- ======== MENU ADMIN ======== --}}
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                           href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.empresas.*') ? 'active' : '' }}"
                           href="{{ route('admin.empresas.index') }}">
                            <i class="bi bi-building"></i> Empresas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}"
                           href="{{ route('admin.usuarios.index') }}">
                            <i class="bi bi-people"></i> Usuarios
                        </a>
                    </li>
                </ul>
            @else
                {{-- ======== MENU EMPRESA ======== --}}
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.dashboard') ? 'active' : '' }}"
                           href="{{ route('app.dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>

                    {{-- Cadastros --}}
                    <div class="sidebar-heading">Cadastros</div>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.clientes.*') ? 'active' : '' }}"
                           href="{{ route('app.clientes.index') }}">
                            <i class="bi bi-person-lines-fill"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.produtos.*') ? 'active' : '' }}"
                           href="{{ route('app.produtos.index') }}">
                            <i class="bi bi-box"></i> Produtos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.servicos.*') ? 'active' : '' }}"
                           href="{{ route('app.servicos.index') }}">
                            <i class="bi bi-tools"></i> Servicos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.categorias.*') ? 'active' : '' }}"
                           href="{{ route('app.categorias.index') }}">
                            <i class="bi bi-tags"></i> Categorias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.fornecedores.*') ? 'active' : '' }}"
                           href="{{ route('app.fornecedores.index') }}">
                            <i class="bi bi-truck"></i> Fornecedores
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.funcionarios.*') ? 'active' : '' }}"
                           href="{{ route('app.funcionarios.index') }}">
                            <i class="bi bi-person-badge"></i> Funcionarios
                        </a>
                    </li>

                    {{-- Vendas --}}
                    <div class="sidebar-heading">Vendas</div>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.orcamentos.*') ? 'active' : '' }}"
                           href="{{ route('app.orcamentos.index') }}">
                            <i class="bi bi-file-earmark-text"></i> Orcamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.pedidos.*') ? 'active' : '' }}"
                           href="{{ route('app.pedidos.index') }}">
                            <i class="bi bi-cart-check"></i> Pedidos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.vendas.*') ? 'active' : '' }}"
                           href="{{ route('app.vendas.index') }}">
                            <i class="bi bi-receipt"></i> Vendas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.ordens-servico.*') ? 'active' : '' }}"
                           href="{{ route('app.ordens-servico.index') }}">
                            <i class="bi bi-wrench-adjustable"></i> Ordens de Servico
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.pdv.*') ? 'active' : '' }}"
                           href="{{ route('app.pdv.index') }}">
                            <i class="bi bi-upc-scan"></i> PDV
                        </a>
                    </li>

                    {{-- Estoque --}}
                    <div class="sidebar-heading">Estoque</div>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.movimentacoes.*') ? 'active' : '' }}"
                           href="{{ route('app.movimentacoes.index') }}">
                            <i class="bi bi-arrow-left-right"></i> Movimentacoes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.transferencias.*') ? 'active' : '' }}"
                           href="{{ route('app.transferencias.index') }}">
                            <i class="bi bi-arrow-repeat"></i> Transferencias
                        </a>
                    </li>

                    {{-- Financeiro --}}
                    <div class="sidebar-heading">Financeiro</div>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.contas-receber.*') ? 'active' : '' }}"
                           href="{{ route('app.contas-receber.index') }}">
                            <i class="bi bi-cash-stack"></i> Contas a Receber
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.contas-pagar.*') ? 'active' : '' }}"
                           href="{{ route('app.contas-pagar.index') }}">
                            <i class="bi bi-credit-card"></i> Contas a Pagar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.financeiro.fluxo-caixa') ? 'active' : '' }}"
                           href="{{ route('app.financeiro.fluxo-caixa') }}">
                            <i class="bi bi-graph-up"></i> Fluxo de Caixa
                        </a>
                    </li>

                    {{-- Relatorios --}}
                    <div class="sidebar-heading">Relatorios</div>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.relatorios.vendas') ? 'active' : '' }}"
                           href="{{ route('app.relatorios.vendas') }}">
                            <i class="bi bi-bar-chart-line"></i> Vendas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.relatorios.estoque') ? 'active' : '' }}"
                           href="{{ route('app.relatorios.estoque') }}">
                            <i class="bi bi-clipboard-data"></i> Estoque
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.relatorios.financeiro') ? 'active' : '' }}"
                           href="{{ route('app.relatorios.financeiro') }}">
                            <i class="bi bi-piggy-bank"></i> Financeiro
                        </a>
                    </li>

                    {{-- Multilojas --}}
                    @if(in_array(auth()->user()->papel ?? '', ['dono', 'admin']))
                        <div class="sidebar-heading">Gestao</div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('app.multilojas.*') ? 'active' : '' }}"
                               href="{{ route('app.multilojas.index') }}">
                                <i class="bi bi-shop-window"></i> Multilojas
                            </a>
                        </li>
                    @endif
                </ul>
            @endif
        </div>
    </nav>

    {{-- Main Wrapper --}}
    <div class="main-wrapper">
        {{-- Top Bar --}}
        <div class="topbar">
            {{-- Mobile toggle --}}
            <button class="btn btn-link text-dark d-lg-none me-2 p-0" onclick="toggleSidebar()">
                <i class="bi bi-list fs-4"></i>
            </button>

            <span class="fw-semibold text-muted">@yield('title', 'Dashboard')</span>

            <div class="ms-auto d-flex align-items-center gap-3">
                {{-- Unidade Selector (only for empresa users) --}}
                @if(!auth()->user()->is_admin && session('unidade_id'))
                    @php
                        $unidadeAtual = \App\Models\Unidade::find(session('unidade_id'));
                    @endphp
                    @if($unidadeAtual)
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-geo-alt me-1"></i>{{ $unidadeAtual->nome }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="{{ route('selecionar-unidade') }}">
                                        <i class="bi bi-arrow-repeat me-1"></i> Trocar Unidade
                                    </a>
                                </li>
                            </ul>
                        </div>
                    @endif
                @endif

                {{-- User Info --}}
                <div class="dropdown">
                    <button class="btn btn-link text-dark text-decoration-none dropdown-toggle p-0" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-item-text text-muted small">
                            {{ auth()->user()->email }}
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item" type="submit">
                                    <i class="bi bi-box-arrow-right me-1"></i> Sair
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Page Content --}}
        <main class="main-content">
            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarBackdrop').classList.toggle('show');
        }
    </script>
    @stack('scripts')
</body>
</html>
