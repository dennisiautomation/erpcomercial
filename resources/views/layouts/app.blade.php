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
    <!-- ERP Design System -->
    <link href="{{ asset('css/erp.css') }}" rel="stylesheet">

    <style>
        /* ================================================================
           CSS Custom Properties — Theming
           ================================================================ */
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 0px;
            --sidebar-bg: #1e293b;
            --sidebar-bg-darker: #0f172a;
            --sidebar-hover: rgba(255, 255, 255, 0.06);
            --sidebar-active: rgba(99, 102, 241, 0.25);
            --sidebar-active-border: #818cf8;
            --sidebar-text: rgba(255, 255, 255, 0.55);
            --sidebar-text-hover: rgba(255, 255, 255, 0.85);
            --sidebar-text-active: #ffffff;
            --sidebar-heading: rgba(255, 255, 255, 0.35);
            --sidebar-divider: rgba(255, 255, 255, 0.06);
            --topbar-height: 56px;
            --topbar-bg: #ffffff;
            --topbar-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            --body-bg: #f1f5f9;
            --accent: #6366f1;
            --accent-light: #818cf8;
            --pdv-bg: linear-gradient(135deg, #6366f1, #8b5cf6);
            --transition-speed: 0.2s;
        }

        /* ================================================================
           Base
           ================================================================ */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            background-color: var(--body-bg);
            overflow-x: hidden;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        /* Scrollbar styling */
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }

        /* ================================================================
           Sidebar
           ================================================================ */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            z-index: 1040;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Brand */
        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 1.125rem;
            flex-shrink: 0;
            border-bottom: 1px solid var(--sidebar-divider);
        }

        .sidebar-brand a {
            color: #fff;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            letter-spacing: -0.01em;
        }

        .sidebar-brand-icon {
            width: 32px;
            height: 32px;
            background: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* Navigation area */
        .sidebar-nav {
            padding: 0.5rem;
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-heading {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--sidebar-heading);
            padding: 1rem 0.75rem 0.375rem;
            margin: 0;
            user-select: none;
        }

        /* Nav links */
        .sidebar .nav-link {
            color: var(--sidebar-text);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            transition: all var(--transition-speed) ease;
            position: relative;
            text-decoration: none;
            border: 1px solid transparent;
            margin-bottom: 1px;
        }

        .sidebar .nav-link:hover {
            color: var(--sidebar-text-hover);
            background: var(--sidebar-hover);
        }

        .sidebar .nav-link.active {
            color: var(--sidebar-text-active);
            background: var(--sidebar-active);
            border-color: rgba(99, 102, 241, 0.15);
        }

        .sidebar .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 6px;
            bottom: 6px;
            width: 3px;
            background: var(--sidebar-active-border);
            border-radius: 0 3px 3px 0;
        }

        .sidebar .nav-link i.nav-icon {
            font-size: 1.05rem;
            width: 1.25rem;
            text-align: center;
            flex-shrink: 0;
            opacity: 0.75;
            transition: opacity var(--transition-speed) ease;
        }

        .sidebar .nav-link:hover i.nav-icon,
        .sidebar .nav-link.active i.nav-icon { opacity: 1; }

        .sidebar .nav-link .nav-text { flex: 1; }

        /* Badge */
        .sidebar .nav-badge {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 0.15rem 0.45rem;
            border-radius: 10px;
            line-height: 1;
            min-width: 18px;
            text-align: center;
        }

        /* Collapsible toggle */
        .sidebar .nav-toggle {
            cursor: pointer;
        }

        .sidebar .nav-toggle .toggle-icon {
            font-size: 0.7rem;
            transition: transform 0.25s ease;
            opacity: 0.4;
            margin-left: auto;
        }

        .sidebar .nav-toggle[aria-expanded="true"] .toggle-icon {
            transform: rotate(90deg);
        }

        /* Submenu */
        .sidebar .submenu-wrapper {
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar .submenu {
            padding: 0.125rem 0 0.25rem 0;
        }

        .sidebar .submenu .nav-link {
            font-size: 0.775rem;
            padding: 0.375rem 0.75rem 0.375rem 2.625rem;
            font-weight: 400;
            color: var(--sidebar-text);
        }

        .sidebar .submenu .nav-link::before { display: none; }

        .sidebar .submenu .nav-link.active {
            color: var(--sidebar-text-active);
            background: var(--sidebar-active);
        }

        .sidebar .submenu .nav-link.active::before {
            display: block;
            left: 0;
        }

        /* PDV highlight */
        .sidebar .nav-link-pdv {
            background: var(--pdv-bg);
            color: #fff !important;
            font-weight: 600;
            border: none;
            margin: 0.375rem 0;
        }

        .sidebar .nav-link-pdv:hover {
            filter: brightness(1.1);
            background: var(--pdv-bg);
            color: #fff !important;
        }

        .sidebar .nav-link-pdv i.nav-icon { opacity: 1; }

        .sidebar .nav-link-pdv.active::before { background: #fff; }

        /* Sidebar footer / user card */
        .sidebar-footer {
            flex-shrink: 0;
            padding: 0.75rem;
            border-top: 1px solid var(--sidebar-divider);
            background: var(--sidebar-bg-darker);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.375rem;
            border-radius: 6px;
            text-decoration: none;
            color: var(--sidebar-text);
            transition: all var(--transition-speed) ease;
        }

        .sidebar-user:hover {
            background: var(--sidebar-hover);
            color: var(--sidebar-text-hover);
        }

        .sidebar-user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.8rem;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        .sidebar-user-info {
            min-width: 0;
            flex: 1;
        }

        .sidebar-user-name {
            font-size: 0.8125rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.85);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        .sidebar-user-role {
            font-size: 0.7rem;
            color: var(--sidebar-heading);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        /* ================================================================
           Sidebar Backdrop (Mobile)
           ================================================================ */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            z-index: 1035;
            backdrop-filter: blur(2px);
            transition: opacity 0.3s ease;
        }

        .sidebar-backdrop.show { display: block; }

        /* ================================================================
           Main Wrapper
           ================================================================ */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ================================================================
           Top Bar
           ================================================================ */
        .topbar {
            height: var(--topbar-height);
            background: var(--topbar-bg);
            box-shadow: var(--topbar-shadow);
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            position: sticky;
            top: 0;
            z-index: 1030;
            gap: 0.75rem;
        }

        .topbar-toggler {
            display: none;
            background: none;
            border: none;
            color: #475569;
            padding: 0.25rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background var(--transition-speed) ease;
        }

        .topbar-toggler:hover { background: #f1f5f9; }
        .topbar-toggler i { font-size: 1.35rem; }

        /* Breadcrumb */
        .topbar-breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.875rem;
            color: #64748b;
            min-width: 0;
        }

        .topbar-breadcrumb-page {
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Unidade badge */
        .topbar-unidade-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.3rem 0.7rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .topbar-unidade-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #334155;
        }

        .topbar-unidade-dot {
            width: 7px;
            height: 7px;
            background: #22c55e;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* Notification bell */
        .topbar-icon-btn {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
        }

        .topbar-icon-btn:hover {
            background: #f1f5f9;
            color: #334155;
        }

        .topbar-icon-btn i { font-size: 1.15rem; }

        .topbar-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid var(--topbar-bg);
        }

        /* User dropdown */
        .topbar-user-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem;
            padding-right: 0.5rem;
            background: none;
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            text-decoration: none;
            color: #334155;
        }

        .topbar-user-btn:hover {
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .topbar-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .topbar-user-name {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1e293b;
        }

        /* Dropdown menus */
        .dropdown-menu {
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 0.375rem;
            margin-top: 0.375rem !important;
        }

        .dropdown-item {
            border-radius: 5px;
            font-size: 0.8125rem;
            padding: 0.45rem 0.75rem;
            color: #475569;
            transition: all 0.15s ease;
        }

        .dropdown-item:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .dropdown-item i { width: 1.25rem; text-align: center; }

        .dropdown-header {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            padding: 0.5rem 0.75rem 0.25rem;
        }

        /* ================================================================
           Main Content
           ================================================================ */
        .main-content {
            padding: 1.5rem;
        }

        /* ================================================================
           Alert overrides
           ================================================================ */
        .erp-alert {
            border: none;
            border-radius: 8px;
            font-size: 0.8125rem;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            animation: alertSlideIn 0.3s ease;
        }

        .erp-alert i { margin-top: 0.1rem; font-size: 1rem; flex-shrink: 0; }

        .erp-alert .btn-close {
            font-size: 0.65rem;
            padding: 0.85rem 0.75rem;
        }

        .erp-alert-success { background: #f0fdf4; color: #166534; border-left: 3px solid #22c55e; }
        .erp-alert-danger  { background: #fef2f2; color: #991b1b; border-left: 3px solid #ef4444; }
        .erp-alert-warning { background: #fffbeb; color: #92400e; border-left: 3px solid #f59e0b; }
        .erp-alert-info    { background: #eff6ff; color: #1e40af; border-left: 3px solid #3b82f6; }

        @keyframes alertSlideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Trial banner */
        .trial-banner {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 0.625rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            font-size: 0.8125rem;
            color: #92400e;
            margin-bottom: 1rem;
            animation: alertSlideIn 0.3s ease;
        }

        .trial-banner i { font-size: 1.1rem; flex-shrink: 0; }
        .trial-banner a { color: #92400e; font-weight: 600; }

        /* ================================================================
           Responsive
           ================================================================ */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .sidebar-backdrop.show { display: block; }
            .main-wrapper { margin-left: 0; }
            .topbar-toggler { display: flex; }
        }

        @media (max-width: 575.98px) {
            .main-content { padding: 1rem; }
            .topbar { padding: 0 0.75rem; }
            .topbar-user-name { display: none; }
        }
    </style>
    @stack('styles')
</head>
<body>

    {{-- ================================================================
         Sidebar Backdrop (Mobile)
         ================================================================ --}}
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="erpToggleSidebar()"></div>

    {{-- ================================================================
         Sidebar
         ================================================================ --}}
    <nav class="sidebar" id="sidebar">
        {{-- Brand --}}
        <div class="sidebar-brand">
            @if(auth()->user()->is_admin)
                <a href="{{ route('admin.dashboard') }}">
                    <span class="sidebar-brand-icon"><i class="bi bi-grid-3x3-gap-fill"></i></span>
                    <span>ERP Admin</span>
                </a>
            @else
                <a href="{{ route('app.dashboard') }}">
                    <span class="sidebar-brand-icon"><i class="bi bi-grid-3x3-gap-fill"></i></span>
                    <span>ERP Comercial</span>
                </a>
            @endif
        </div>

        {{-- Navigation --}}
        <div class="sidebar-nav">
            @if(auth()->user()->is_admin)
                {{-- ======================================================
                     MENU ADMIN
                     ====================================================== --}}
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                           href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-speedometer2 nav-icon"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.empresas.*') ? 'active' : '' }}"
                           href="{{ route('admin.empresas.index') }}">
                            <i class="bi bi-building nav-icon"></i>
                            <span class="nav-text">Empresas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}"
                           href="{{ route('admin.usuarios.index') }}">
                            <i class="bi bi-people nav-icon"></i>
                            <span class="nav-text">Usuarios</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.planos.*') ? 'active' : '' }}"
                           href="{{ route('admin.planos.index') }}">
                            <i class="bi bi-credit-card-2-front nav-icon"></i>
                            <span class="nav-text">Planos</span>
                        </a>
                    </li>
                </ul>
            @else
                {{-- ======================================================
                     MENU EMPRESA
                     ====================================================== --}}
                <ul class="nav flex-column">
                    {{-- Dashboard --}}
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('app.dashboard') ? 'active' : '' }}"
                           href="{{ route('app.dashboard') }}">
                            <i class="bi bi-speedometer2 nav-icon"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>

                    {{-- ---- Cadastros ---- --}}
                    <li class="sidebar-heading">Cadastros</li>
                    <li class="nav-item">
                        <a class="nav-link nav-toggle {{ request()->routeIs('app.clientes.*', 'app.produtos.*', 'app.fornecedores.*', 'app.categorias.*', 'app.servicos.*', 'app.funcionarios.*', 'app.etiquetas.*') ? '' : 'collapsed' }}"
                           data-bs-toggle="collapse" href="#menuCadastros" role="button"
                           aria-expanded="{{ request()->routeIs('app.clientes.*', 'app.produtos.*', 'app.fornecedores.*', 'app.categorias.*', 'app.servicos.*', 'app.funcionarios.*', 'app.etiquetas.*') ? 'true' : 'false' }}"
                           aria-controls="menuCadastros">
                            <i class="bi bi-journal-bookmark nav-icon"></i>
                            <span class="nav-text">Cadastros</span>
                            <i class="bi bi-chevron-right toggle-icon"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('app.clientes.*', 'app.produtos.*', 'app.fornecedores.*', 'app.categorias.*', 'app.servicos.*', 'app.funcionarios.*', 'app.etiquetas.*') ? 'show' : '' }}" id="menuCadastros">
                            <ul class="nav flex-column submenu">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.clientes.*') ? 'active' : '' }}"
                                       href="{{ route('app.clientes.index') }}">Clientes</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.produtos.*') ? 'active' : '' }}"
                                       href="{{ route('app.produtos.index') }}">Produtos</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.fornecedores.*') ? 'active' : '' }}"
                                       href="{{ route('app.fornecedores.index') }}">Fornecedores</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.categorias.*') ? 'active' : '' }}"
                                       href="{{ route('app.categorias.index') }}">Categorias</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.servicos.*') ? 'active' : '' }}"
                                       href="{{ route('app.servicos.index') }}">Servicos</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.funcionarios.*') ? 'active' : '' }}"
                                       href="{{ route('app.funcionarios.index') }}">Funcionarios</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.etiquetas.*') ? 'active' : '' }}"
                                       href="{{ route('app.etiquetas.index') }}">Etiquetas</a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- ---- Vendas ---- --}}
                    <li class="sidebar-heading">Comercial</li>
                    <li class="nav-item">
                        <a class="nav-link nav-toggle {{ request()->routeIs('app.orcamentos.*', 'app.pedidos.*', 'app.vendas.*', 'app.ordens-servico.*') ? '' : 'collapsed' }}"
                           data-bs-toggle="collapse" href="#menuVendas" role="button"
                           aria-expanded="{{ request()->routeIs('app.orcamentos.*', 'app.pedidos.*', 'app.vendas.*', 'app.ordens-servico.*') ? 'true' : 'false' }}"
                           aria-controls="menuVendas">
                            <i class="bi bi-cart3 nav-icon"></i>
                            <span class="nav-text">Vendas</span>
                            <i class="bi bi-chevron-right toggle-icon"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('app.orcamentos.*', 'app.pedidos.*', 'app.vendas.*', 'app.ordens-servico.*') ? 'show' : '' }}" id="menuVendas">
                            <ul class="nav flex-column submenu">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.orcamentos.*') ? 'active' : '' }}"
                                       href="{{ route('app.orcamentos.index') }}">Orcamentos</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.pedidos.*') ? 'active' : '' }}"
                                       href="{{ route('app.pedidos.index') }}">Pedidos</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.vendas.*') ? 'active' : '' }}"
                                       href="{{ route('app.vendas.index') }}">Vendas</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.ordens-servico.*') ? 'active' : '' }}"
                                       href="{{ route('app.ordens-servico.index') }}">Ordens de Servico</a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- PDV — highlighted --}}
                    <li class="nav-item">
                        <a class="nav-link nav-link-pdv {{ request()->routeIs('app.pdv.*') ? 'active' : '' }}"
                           href="{{ route('app.pdv.index') }}">
                            <i class="bi bi-upc-scan nav-icon"></i>
                            <span class="nav-text">PDV</span>
                        </a>
                    </li>

                    {{-- ---- Estoque ---- --}}
                    <li class="sidebar-heading">Estoque</li>
                    <li class="nav-item">
                        <a class="nav-link nav-toggle {{ request()->routeIs('app.movimentacoes.*', 'app.transferencias.*') ? '' : 'collapsed' }}"
                           data-bs-toggle="collapse" href="#menuEstoque" role="button"
                           aria-expanded="{{ request()->routeIs('app.movimentacoes.*', 'app.transferencias.*') ? 'true' : 'false' }}"
                           aria-controls="menuEstoque">
                            <i class="bi bi-boxes nav-icon"></i>
                            <span class="nav-text">Estoque</span>
                            <i class="bi bi-chevron-right toggle-icon"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('app.movimentacoes.*', 'app.transferencias.*') ? 'show' : '' }}" id="menuEstoque">
                            <ul class="nav flex-column submenu">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.movimentacoes.*') ? 'active' : '' }}"
                                       href="{{ route('app.movimentacoes.index') }}">Movimentacoes</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.transferencias.*') ? 'active' : '' }}"
                                       href="{{ route('app.transferencias.index') }}">Transferencias</a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- ---- Financeiro ---- --}}
                    <li class="sidebar-heading">Financeiro</li>
                    <li class="nav-item">
                        <a class="nav-link nav-toggle {{ request()->routeIs('app.contas-receber.*', 'app.contas-pagar.*', 'app.financeiro.*', 'app.boletos.*', 'app.conciliacao.*', 'app.contratos.*') ? '' : 'collapsed' }}"
                           data-bs-toggle="collapse" href="#menuFinanceiro" role="button"
                           aria-expanded="{{ request()->routeIs('app.contas-receber.*', 'app.contas-pagar.*', 'app.financeiro.*', 'app.boletos.*', 'app.conciliacao.*', 'app.contratos.*') ? 'true' : 'false' }}"
                           aria-controls="menuFinanceiro">
                            <i class="bi bi-wallet2 nav-icon"></i>
                            <span class="nav-text">Financeiro</span>
                            <i class="bi bi-chevron-right toggle-icon"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('app.contas-receber.*', 'app.contas-pagar.*', 'app.financeiro.*', 'app.boletos.*', 'app.conciliacao.*', 'app.contratos.*') ? 'show' : '' }}" id="menuFinanceiro">
                            <ul class="nav flex-column submenu">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.contas-receber.*') ? 'active' : '' }}"
                                       href="{{ route('app.contas-receber.index') }}">Contas a Receber</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.contas-pagar.*') ? 'active' : '' }}"
                                       href="{{ route('app.contas-pagar.index') }}">Contas a Pagar</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.financeiro.fluxo-caixa') ? 'active' : '' }}"
                                       href="{{ route('app.financeiro.fluxo-caixa') }}">Fluxo de Caixa</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.boletos.*') ? 'active' : '' }}"
                                       href="{{ route('app.boletos.index') }}">Boletos</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.conciliacao.*') ? 'active' : '' }}"
                                       href="{{ route('app.conciliacao.index') }}">Conciliacao</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.contratos.*') ? 'active' : '' }}"
                                       href="{{ route('app.contratos.index') }}">Contratos</a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- ---- Fiscal ---- --}}
                    <li class="sidebar-heading">Fiscal</li>
                    <li class="nav-item">
                        <a class="nav-link nav-toggle {{ request()->routeIs('app.notas-fiscais.*', 'app.configuracao-fiscal.*') ? '' : 'collapsed' }}"
                           data-bs-toggle="collapse" href="#menuFiscal" role="button"
                           aria-expanded="{{ request()->routeIs('app.notas-fiscais.*', 'app.configuracao-fiscal.*') ? 'true' : 'false' }}"
                           aria-controls="menuFiscal">
                            <i class="bi bi-file-earmark-ruled nav-icon"></i>
                            <span class="nav-text">Fiscal</span>
                            <i class="bi bi-chevron-right toggle-icon"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('app.notas-fiscais.*', 'app.configuracao-fiscal.*') ? 'show' : '' }}" id="menuFiscal">
                            <ul class="nav flex-column submenu">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.notas-fiscais.*') ? 'active' : '' }}"
                                       href="{{ route('app.notas-fiscais.index') }}">Notas Fiscais</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.configuracao-fiscal.*') ? 'active' : '' }}"
                                       href="{{ route('app.configuracao-fiscal.edit') }}">Configuracao Fiscal</a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- ---- Relatorios ---- --}}
                    <li class="sidebar-heading">Relatorios</li>
                    <li class="nav-item">
                        <a class="nav-link nav-toggle {{ request()->routeIs('app.relatorios.*', 'app.dre.*', 'app.comissoes.*') ? '' : 'collapsed' }}"
                           data-bs-toggle="collapse" href="#menuRelatorios" role="button"
                           aria-expanded="{{ request()->routeIs('app.relatorios.*', 'app.dre.*', 'app.comissoes.*') ? 'true' : 'false' }}"
                           aria-controls="menuRelatorios">
                            <i class="bi bi-bar-chart-line nav-icon"></i>
                            <span class="nav-text">Relatorios</span>
                            <i class="bi bi-chevron-right toggle-icon"></i>
                        </a>
                        <div class="collapse {{ request()->routeIs('app.relatorios.*', 'app.dre.*', 'app.comissoes.*') ? 'show' : '' }}" id="menuRelatorios">
                            <ul class="nav flex-column submenu">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.relatorios.vendas') ? 'active' : '' }}"
                                       href="{{ route('app.relatorios.vendas') }}">Vendas</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.relatorios.estoque') ? 'active' : '' }}"
                                       href="{{ route('app.relatorios.estoque') }}">Estoque</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.relatorios.financeiro') ? 'active' : '' }}"
                                       href="{{ route('app.relatorios.financeiro') }}">Financeiro</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.dre.*') ? 'active' : '' }}"
                                       href="{{ route('app.dre.index') }}">DRE</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('app.comissoes.*') ? 'active' : '' }}"
                                       href="{{ route('app.comissoes.index') }}">Comissoes</a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- ---- Gestao ---- --}}
                    @if(auth()->user()->perfil && in_array(auth()->user()->perfil->value, ['dono', 'admin']))
                        <li class="sidebar-heading">Gestao</li>
                        <li class="nav-item">
                            <a class="nav-link nav-toggle {{ request()->routeIs('app.multilojas.*', 'app.plano-contas.*', 'app.centros-custo.*') ? '' : 'collapsed' }}"
                               data-bs-toggle="collapse" href="#menuGestao" role="button"
                               aria-expanded="{{ request()->routeIs('app.multilojas.*', 'app.plano-contas.*', 'app.centros-custo.*') ? 'true' : 'false' }}"
                               aria-controls="menuGestao">
                                <i class="bi bi-gear nav-icon"></i>
                                <span class="nav-text">Gestao</span>
                                <i class="bi bi-chevron-right toggle-icon"></i>
                            </a>
                            <div class="collapse {{ request()->routeIs('app.multilojas.*', 'app.plano-contas.*', 'app.centros-custo.*') ? 'show' : '' }}" id="menuGestao">
                                <ul class="nav flex-column submenu">
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('app.multilojas.*') ? 'active' : '' }}"
                                           href="{{ route('app.multilojas.index') }}">Multilojas</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('app.plano-contas.*') ? 'active' : '' }}"
                                           href="{{ route('app.plano-contas.index') }}">Plano de Contas</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('app.centros-custo.*') ? 'active' : '' }}"
                                           href="{{ route('app.centros-custo.index') }}">Centros de Custo</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('app.auditoria.*') ? 'active' : '' }}"
                                           href="{{ route('app.auditoria.index') }}">
                                            <i class="bi bi-shield-check me-1"></i> Auditoria
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    @endif

                    {{-- Meu Plano --}}
                    <li class="nav-item" style="margin-top: 0.25rem;">
                        <a class="nav-link {{ request()->routeIs('app.plano.*') ? 'active' : '' }}"
                           href="{{ route('app.plano.index') }}">
                            <i class="bi bi-star nav-icon"></i>
                            <span class="nav-text">Meu Plano</span>
                        </a>
                    </li>
                </ul>
            @endif
        </div>

        {{-- Sidebar Footer — User Card --}}
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="sidebar-user-info">
                    <span class="sidebar-user-name">{{ auth()->user()->name }}</span>
                    <span class="sidebar-user-role">
                        @if(auth()->user()->is_admin)
                            Administrador
                        @else
                            {{ auth()->user()->perfil ? auth()->user()->perfil->label() : 'Usuario' }}
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </nav>

    {{-- ================================================================
         Main Wrapper
         ================================================================ --}}
    <div class="main-wrapper">
        {{-- Top Bar --}}
        <div class="topbar">
            {{-- Mobile toggle --}}
            <button class="topbar-toggler" onclick="erpToggleSidebar()" aria-label="Abrir menu">
                <i class="bi bi-list"></i>
            </button>

            {{-- Breadcrumb --}}
            <div class="topbar-breadcrumb">
                <i class="bi bi-house-door" style="font-size: 0.9rem; opacity: 0.5;"></i>
                <i class="bi bi-chevron-right" style="font-size: 0.55rem; opacity: 0.35;"></i>
                <span class="topbar-breadcrumb-page">@yield('title', 'Dashboard')</span>
            </div>

            {{-- Global Search --}}
            <div class="position-relative d-none d-md-block" style="min-width:300px">
                <input type="text" id="globalSearch" class="form-control form-control-sm"
                       placeholder="Buscar clientes, produtos, vendas..."
                       autocomplete="off" style="border-radius:2rem;padding-left:2.5rem;border-color:#e2e8f0">
                <i class="bi bi-search position-absolute" style="left:1rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.85rem"></i>
                <div id="globalSearchResults" class="position-absolute w-100 mt-1" style="display:none;z-index:1060"></div>
            </div>

            <div class="ms-auto d-flex align-items-center gap-2">
                {{-- Unidade Selector --}}
                @if(!auth()->user()->is_admin && session('unidade_id'))
                    @php
                        $unidadeAtual = \App\Models\Unidade::find(session('unidade_id'));
                    @endphp
                    @if($unidadeAtual)
                        <div class="dropdown">
                            <button class="topbar-unidade-btn dropdown-toggle" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="topbar-unidade-dot"></span>
                                <span class="d-none d-sm-inline">{{ $unidadeAtual->nome }}</span>
                                <span class="d-sm-none">{{ Str::limit($unidadeAtual->nome, 10) }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-header">Unidade Ativa</span></li>
                                <li>
                                    <span class="dropdown-item-text small fw-semibold text-dark">
                                        <i class="bi bi-geo-alt me-1"></i>{{ $unidadeAtual->nome }}
                                    </span>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('selecionar-unidade') }}">
                                        <i class="bi bi-arrow-repeat me-2"></i>Trocar Unidade
                                    </a>
                                </li>
                            </ul>
                        </div>
                    @endif
                @endif

                {{-- Notifications --}}
                @if(!auth()->user()->is_admin)
                <div class="dropdown">
                    <button class="topbar-icon-btn dropdown-toggle" type="button" id="notificacoesDropdown"
                            data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false" title="Notificacoes">
                        <i class="bi bi-bell"></i>
                        <span class="topbar-badge d-none" id="notificacaoBadge"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow" style="min-width: 340px; max-width: 400px;" aria-labelledby="notificacoesDropdown">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <h6 class="mb-0 fw-bold" style="font-size:0.875rem;">Notificacoes</h6>
                            <form method="POST" action="{{ route('app.notificacoes.todas-lidas') }}" id="formMarcarTodasLidas">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0" style="font-size:0.75rem;">
                                    Marcar todas como lidas
                                </button>
                            </form>
                        </div>
                        <div id="notificacoesLista" style="max-height: 320px; overflow-y: auto;">
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-bell-slash fs-4 d-block mb-1"></i>
                                <small>Carregando...</small>
                            </div>
                        </div>
                        <div class="border-top px-3 py-2 text-center">
                            <a href="{{ route('app.notificacoes.index') }}" class="text-decoration-none" style="font-size:0.8125rem;">
                                <i class="bi bi-list-ul me-1"></i> Ver todas
                            </a>
                        </div>
                    </div>
                </div>
                @endif

                {{-- User Dropdown --}}
                <div class="dropdown">
                    <button class="topbar-user-btn dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="true">
                        <div class="topbar-user-avatar">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <span class="topbar-user-name d-none d-md-inline">{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 200px;">
                        <li>
                            <span class="dropdown-item-text small text-muted">
                                {{ auth()->user()->email }}
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @if(!auth()->user()->is_admin)
                            <li>
                                <a class="dropdown-item" href="{{ route('app.plano.index') }}">
                                    <i class="bi bi-star me-2"></i>Meu Plano
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger" type="submit">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sair
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Page Content --}}
        <main class="main-content">
            {{-- Trial Banner --}}
            @include('components.trial-banner')

            {{-- Flash Messages --}}
            @include('components.alert')

            {{-- Validation Errors --}}
            @if($errors && $errors->any())
                <div class="erp-alert erp-alert-danger alert alert-dismissible fade show mb-3" role="alert">
                    <i class="bi bi-exclamation-octagon"></i>
                    <div class="flex-grow-1">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- ERP Core Intelligence -->
    <script src="{{ asset('js/erp-core.js') }}"></script>
    <script>
        /**
         * Toggle sidebar visibility (mobile)
         */
        function erpToggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarBackdrop').classList.toggle('show');
            document.body.style.overflow = document.getElementById('sidebar').classList.contains('show') ? 'hidden' : '';
        }

        /**
         * Close sidebar when pressing Escape
         */
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('sidebar').classList.contains('show')) {
                erpToggleSidebar();
            }
        });

        /**
         * Auto-dismiss alerts after 6 seconds
         */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.erp-alert[data-auto-dismiss]').forEach(function(alert) {
                var delay = parseInt(alert.getAttribute('data-auto-dismiss')) || 6000;
                setTimeout(function() {
                    if (alert && alert.parentNode) {
                        alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-8px)';
                        setTimeout(function() {
                            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                            if (bsAlert) bsAlert.close();
                        }, 400);
                    }
                }, delay);
            });
        });
        /**
         * Init Bootstrap tooltips
         */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                new bootstrap.Tooltip(el);
            });
        });

        /**
         * Notifications bell — AJAX
         */
        @if(!auth()->user()->is_admin)
        document.addEventListener('DOMContentLoaded', function() {
            var badge = document.getElementById('notificacaoBadge');
            var lista = document.getElementById('notificacoesLista');

            function carregarNotificacoes() {
                fetch('{{ route("app.notificacoes.contar") }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    // Badge
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }

                    // Lista
                    if (data.notificacoes && data.notificacoes.length > 0) {
                        var html = '';
                        data.notificacoes.forEach(function(n) {
                            html += '<a href="' + (n.url || '#') + '" class="dropdown-item d-flex align-items-start gap-2 py-2 px-3" style="white-space:normal;">';
                            html += '<div class="rounded-2 p-1 bg-' + n.cor + ' bg-opacity-10 flex-shrink-0" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;">';
                            html += '<i class="bi bi-' + n.icone + ' text-' + n.cor + '" style="font-size:0.85rem;"></i>';
                            html += '</div>';
                            html += '<div class="min-w-0">';
                            html += '<div class="fw-semibold" style="font-size:0.8125rem;">' + n.titulo + '</div>';
                            html += '<small class="text-muted">' + n.tempo + '</small>';
                            html += '</div></a>';
                        });
                        lista.innerHTML = html;
                    } else {
                        lista.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-bell-slash fs-4 d-block mb-1"></i><small>Nenhuma notificacao nova</small></div>';
                    }
                })
                .catch(function() {});
            }

            carregarNotificacoes();

            // Recarrega a cada 60 segundos
            setInterval(carregarNotificacoes, 60000);
        });
        @endif
    </script>
    {{-- Global Search JS --}}
    <script>
    (function() {
        const globalSearch = document.getElementById('globalSearch');
        const globalResults = document.getElementById('globalSearchResults');
        if (!globalSearch) return;
        let debounce;
        globalSearch.addEventListener('input', function() {
            clearTimeout(debounce);
            const q = globalSearch.value.trim();
            if (q.length < 2) { globalResults.style.display = 'none'; return; }
            debounce = setTimeout(async function() {
                try {
                    const res = await fetch('/app/search/global?q=' + encodeURIComponent(q), {
                        headers: {'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content}
                    });
                    const data = await res.json();
                    const labels = {clientes:'Clientes',produtos:'Produtos',vendas:'Vendas'};
                    const icons = {clientes:'people',produtos:'box',vendas:'bag-check'};
                    let html = '<div class="bg-white border rounded shadow-lg p-2" style="max-height:400px;overflow-y:auto">';
                    let hasResults = false;
                    for (const [type, items] of Object.entries(data)) {
                        if (!items.length) continue;
                        hasResults = true;
                        html += '<div class="small fw-bold text-muted px-2 py-1"><i class="bi bi-' + (icons[type]||'search') + ' me-1"></i>' + (labels[type]||type) + '</div>';
                        items.forEach(function(item) {
                            html += '<a href="' + item.url + '" class="d-block px-2 py-1 text-decoration-none text-dark rounded" style="transition:background .15s" onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'transparent\'">' + item.label + ' <small class="text-muted">' + (item.detail||'') + '</small></a>';
                        });
                    }
                    if (!hasResults) {
                        html += '<div class="text-center text-muted py-2">Nenhum resultado</div>';
                    }
                    html += '</div>';
                    globalResults.innerHTML = html;
                    globalResults.style.display = 'block';
                } catch(e) { globalResults.style.display = 'none'; }
            }, 300);
        });
        document.addEventListener('click', function(e) {
            if (!globalSearch.parentElement.contains(e.target)) globalResults.style.display = 'none';
        });
        globalSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { globalResults.style.display = 'none'; globalSearch.blur(); }
        });
    })();
    </script>
    @stack('scripts')
</body>
</html>
