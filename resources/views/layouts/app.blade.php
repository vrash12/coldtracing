<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'ColdTrace')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    {{-- Icons --}}
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>
        :root {
            --primary: #0f172a;
            --primary-dark: #020617;
            --primary-soft: #10263a;

            --accent: #2563eb;
            --accent-dark: #1d4ed8;
            --accent-cyan: #06b6d4;

            --bg: #f4f7fb;
            --surface: #ffffff;
            --surface-soft: #f8fafc;

            --border: #e5e7eb;
            --border-soft: #dbeafe;

            --text: #0f172a;
            --muted: #64748b;

            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #0891b2;

            --shadow-sm: 0 4px 12px rgba(15, 23, 42, 0.06);
            --shadow-md: 0 12px 32px rgba(15, 23, 42, 0.10);
            --shadow-lg: 0 18px 48px rgba(15, 23, 42, 0.16);

            --radius-lg: 24px;

            --sidebar-width: 310px;
            --sidebar-collapsed-width: 92px;
            --mobile-sidebar-width: 288px;

            --transition: 0.25s ease;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
            overflow-x: hidden;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        a {
            color: inherit;
        }

        button,
        input,
        select,
        textarea {
            font-family: inherit;
        }

        .dashboard-shell {
            width: 100%;
            min-height: 100vh;
            background: var(--bg);
        }

        .dashboard-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1000;
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            padding: 22px 20px 20px;
            overflow-x: hidden;
            overflow-y: auto;
            background:
                radial-gradient(circle at top left, rgba(34, 211, 238, 0.16), transparent 36%),
                linear-gradient(180deg, #10263a 0%, #07111f 46%, #020617 100%);
            color: #ffffff;
            box-shadow: 14px 0 36px rgba(2, 6, 23, 0.24);
            transition:
                width var(--transition),
                min-width var(--transition),
                max-width var(--transition),
                padding var(--transition),
                transform var(--transition);
        }

        .dashboard-shell.sidebar-collapsed .dashboard-sidebar {
            width: var(--sidebar-collapsed-width);
            min-width: var(--sidebar-collapsed-width);
            max-width: var(--sidebar-collapsed-width);
            padding: 22px 12px;
        }

        .sidebar-logo-button {
            width: 100%;
            min-height: 64px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: none;
            background: transparent;
            color: #ffffff;
            padding: 0;
            margin-bottom: 28px;
            cursor: pointer;
            text-align: left;
        }

        .sidebar-logo-box {
            width: 58px;
            height: 58px;
            min-width: 58px;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.25);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(2, 6, 23, 0.22);
            transition:
                transform var(--transition),
                box-shadow var(--transition);
        }

        .sidebar-logo-button:hover .sidebar-logo-box {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(2, 6, 23, 0.30);
        }

        .sidebar-logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-logo-fallback {
            width: 100%;
            height: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 28px;
        }

        .sidebar-logo-text {
            min-width: 0;
            overflow: hidden;
        }

        .sidebar-logo-text strong {
            display: block;
            color: #ffffff;
            font-size: 22px;
            font-weight: 900;
            line-height: 1;
            white-space: nowrap;
        }

        .sidebar-logo-text span {
            display: block;
            margin-top: 6px;
            color: rgba(255, 255, 255, 0.68);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .dashboard-shell.sidebar-collapsed .sidebar-logo-button {
            justify-content: center;
            margin-bottom: 34px;
        }

        .dashboard-shell.sidebar-collapsed .sidebar-logo-box {
            width: 62px;
            height: 62px;
            min-width: 62px;
            border-radius: 20px;
        }

        .dashboard-shell.sidebar-collapsed .sidebar-logo-text {
            display: none;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 0;
        }

        .nav-label {
            margin: 12px 12px 8px;
            color: rgba(255, 255, 255, 0.56);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .sidebar-link,
        .logout-button {
            width: 100%;
            min-height: 56px;
            border: none;
            border-radius: 18px;
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 18px;
            color: rgba(255, 255, 255, 0.84);
            background: transparent;
            text-decoration: none;
            font-size: 15px;
            font-weight: 850;
            cursor: pointer;
            transition:
                background var(--transition),
                color var(--transition),
                transform var(--transition),
                box-shadow var(--transition);
            white-space: nowrap;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.10);
            color: #ffffff;
        }

        .sidebar-link.active {
            background: #ffffff;
            color: var(--accent);
            box-shadow: 0 18px 36px rgba(255, 255, 255, 0.12);
        }

        .nav-icon {
            width: 23px;
            min-width: 23px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .nav-text {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dashboard-shell.sidebar-collapsed .nav-label {
            display: none;
        }

        .dashboard-shell.sidebar-collapsed .sidebar-nav {
            gap: 13px;
        }

        .dashboard-shell.sidebar-collapsed .sidebar-link {
            justify-content: center;
            padding: 15px 0;
            border-radius: 19px;
        }

        .dashboard-shell.sidebar-collapsed .nav-icon {
            width: 24px;
            min-width: 24px;
            font-size: 20px;
        }

        .dashboard-shell.sidebar-collapsed .nav-text {
            display: none;
        }

        .sidebar-bottom {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding-top: 18px;
        }

        .sidebar-footer {
            margin: 0;
            padding: 0;
        }

        .logout-button {
            min-height: 58px;
            justify-content: center;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff;
            box-shadow: 0 14px 28px rgba(220, 38, 38, 0.28);
        }

        .logout-button:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(220, 38, 38, 0.34);
        }

        .dashboard-shell.sidebar-collapsed .sidebar-bottom {
            align-items: center;
            gap: 12px;
            padding-top: 16px;
        }

        .dashboard-shell.sidebar-collapsed .logout-button {
            width: 62px;
            height: 62px;
            min-height: 62px;
            padding: 0;
            border-radius: 22px;
            justify-content: center;
        }

        .dashboard-shell.sidebar-collapsed .logout-button .nav-text {
            display: none;
        }

        .dashboard-shell.sidebar-collapsed .logout-button .nav-icon {
            width: 24px;
            min-width: 24px;
            font-size: 22px;
        }

        .dashboard-main {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(6, 182, 212, 0.10), transparent 30%),
                linear-gradient(180deg, #f8fafc 0%, #eef4fb 100%);
            transition:
                margin-left var(--transition),
                width var(--transition);
        }

        .dashboard-shell.sidebar-collapsed .dashboard-main {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        .dashboard-content {
            padding: 32px 34px 48px;
        }

        .page-container {
            max-width: 1380px;
            margin: 0 auto;
        }

        .page-wrapper {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(6, 182, 212, 0.10), transparent 30%),
                linear-gradient(180deg, #f8fafc 0%, #eef4fb 100%);
            padding: 32px;
        }

        .panel,
        .card,
        .table-card,
        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .primary-button,
        .secondary-button,
        .btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid transparent;
            text-decoration: none;
            font-size: 14px;
            font-weight: 900;
            line-height: 1;
            cursor: pointer;
            transition:
                background var(--transition),
                color var(--transition),
                border-color var(--transition),
                transform var(--transition),
                box-shadow var(--transition);
        }

        .primary-button,
        .btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.20);
        }

        .primary-button:hover,
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(37, 99, 235, 0.28);
        }

        .secondary-button {
            background: #ffffff;
            color: #334155;
            border-color: #e2e8f0;
        }

        .secondary-button:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .flash-message {
            padding: 14px 16px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 800;
        }

        .flash-message.success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .flash-message.error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .metric-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 18px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            position: relative;
        }

        .metric-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 14px;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
        }

        .metric-icon.blue {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }

        .metric-icon.cyan {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }

        .metric-icon.green {
            background: linear-gradient(135deg, #16a34a, #15803d);
        }

        .metric-icon.amber {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .metric-icon.red {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .metric-icon.violet {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .metric-card span {
            display: block;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .metric-card strong {
            display: block;
            color: #0f172a;
            font-size: 28px;
            font-weight: 900;
            line-height: 1;
        }

        .metric-card small {
            display: block;
            margin-top: 6px;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
        }

        .danger-card strong {
            color: #dc2626;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            color: #64748b;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 14px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .main-cell strong,
        .date-cell strong {
            display: block;
            color: #0f172a;
            font-size: 14px;
            font-weight: 900;
        }

        .main-cell small,
        .date-cell small,
        .route-cell small {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }

        .route-cell span {
            display: block;
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
        }

        .status-badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
        }

        .status-pending {
            background: #fffbeb;
            color: #d97706;
        }

        .status-approved {
            background: #eff6ff;
            color: #2563eb;
        }

        .status-assigned,
        .status-in_transit,
        .status-in_progress {
            background: #ecfeff;
            color: #0891b2;
        }

        .status-delivered,
        .status-completed {
            background: #f0fdf4;
            color: #15803d;
        }

        .status-cancelled {
            background: #fef2f2;
            color: #dc2626;
        }

        .muted {
            color: #94a3b8;
            font-weight: 700;
        }

        .sidebar-backdrop {
            display: none;
        }

        .mobile-sidebar-open-button {
            display: none;
        }

        @media (max-width: 1024px) {
            .dashboard-content {
                padding: 28px 22px 42px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-sidebar {
                width: var(--mobile-sidebar-width);
                min-width: var(--mobile-sidebar-width);
                max-width: var(--mobile-sidebar-width);
                transform: translateX(0);
            }

            .dashboard-shell.sidebar-collapsed .dashboard-sidebar {
                width: var(--mobile-sidebar-width);
                min-width: var(--mobile-sidebar-width);
                max-width: var(--mobile-sidebar-width);
                padding: 22px 20px 20px;
                transform: translateX(-110%);
            }

            .dashboard-shell.sidebar-collapsed .sidebar-logo-text,
            .dashboard-shell.sidebar-collapsed .nav-text {
                display: block;
            }

            .dashboard-shell.sidebar-collapsed .nav-label {
                display: block;
            }

            .dashboard-shell.sidebar-collapsed .sidebar-link {
                justify-content: flex-start;
                padding: 15px 18px;
            }

            .dashboard-shell.sidebar-collapsed .logout-button {
                width: 100%;
                height: auto;
                min-height: 58px;
                padding: 15px 18px;
                border-radius: 18px;
            }

            .dashboard-main,
            .dashboard-shell.sidebar-collapsed .dashboard-main {
                margin-left: 0;
                width: 100%;
            }

            .sidebar-backdrop {
                position: fixed;
                inset: 0;
                z-index: 900;
                background: rgba(15, 23, 42, 0.42);
                backdrop-filter: blur(2px);
                display: block;
                opacity: 1;
                pointer-events: auto;
                transition: opacity var(--transition);
            }

            .dashboard-shell.sidebar-collapsed .sidebar-backdrop {
                opacity: 0;
                pointer-events: none;
            }

            .mobile-sidebar-open-button {
                position: fixed;
                top: 18px;
                left: 18px;
                z-index: 850;
                width: 48px;
                height: 48px;
                border: none;
                border-radius: 16px;
                background: linear-gradient(135deg, var(--accent), var(--accent-cyan));
                color: #ffffff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                box-shadow: 0 14px 28px rgba(37, 99, 235, 0.28);
                cursor: pointer;
            }

            .dashboard-shell:not(.sidebar-collapsed) .mobile-sidebar-open-button {
                display: none;
            }

            .dashboard-content {
                padding: 84px 16px 32px;
            }
        }

        @media (max-width: 420px) {
            .dashboard-content {
                padding: 84px 12px 28px;
            }

            .sidebar-link,
            .logout-button {
                font-size: 14px;
            }
        }
    </style>

    @stack('styles')
</head>

@php
    $user = auth()->user();

    $roleName = $user?->role?->name ?? 'Guest';
    $displayRole = $roleName === 'Receiver' ? 'Customer / Receiver' : $roleName;

    /*
    |--------------------------------------------------------------------------
    | One dashboard URL only
    |--------------------------------------------------------------------------
    | This prevents duplicate dashboard links. The sidebar uses one shared
    | "Dashboard" link, then changes its destination depending on role.
    |--------------------------------------------------------------------------
    */

    $dashboardUrl = Route::has('dashboard')
        ? route('dashboard')
        : url('/dashboard');

    if ($user && $user->isDriver() && Route::has('driver.dashboard')) {
        $dashboardUrl = route('driver.dashboard');
    }

    if ($user && $user->isReceiver() && Route::has('customer.dashboard')) {
        $dashboardUrl = route('customer.dashboard');
    }

    $dashboardIsActive =
        request()->routeIs('dashboard') ||
        request()->routeIs('driver.dashboard') ||
        request()->routeIs('customer.dashboard');

    /*
    |--------------------------------------------------------------------------
    | Admin URLs
    |--------------------------------------------------------------------------
    */

    $usersUrl = Route::has('users.index')
        ? route('users.index')
        : url('/users');

    $ordersUrl = Route::has('orders.index')
        ? route('orders.index')
        : url('/orders');

    /*
    |--------------------------------------------------------------------------
    | Customer URLs
    |--------------------------------------------------------------------------
    */

    $customerOrdersUrl = Route::has('customer.orders.index')
        ? route('customer.orders.index')
        : url('/customer/orders');

    /*
    |--------------------------------------------------------------------------
    | Driver URLs
    |--------------------------------------------------------------------------
    */

    $driverOrdersUrl = Route::has('driver.orders.index')
        ? route('driver.orders.index')
        : url('/driver/orders');

    $driverTripsUrl = Route::has('driver.trips.index')
        ? route('driver.trips.index')
        : url('/driver/trips');

@endphp

<body>

@auth
    <div class="dashboard-shell" id="dashboardShell">
        <button
            type="button"
            class="mobile-sidebar-open-button"
            onclick="setColdTraceSidebarState(false)"
            aria-label="Open sidebar"
        >
            <i class="bi bi-list"></i>
        </button>

        <aside class="dashboard-sidebar" aria-label="ColdTrace sidebar">

            <button
                type="button"
                class="sidebar-logo-button"
                id="sidebarLogoButton"
                title="Toggle sidebar"
                aria-label="Toggle sidebar"
                aria-expanded="true"
            >
                <span class="sidebar-logo-box">
                    <img
                        src="{{ asset('images/coldtrace-logo.png') }}"
                        alt="ColdTrace Logo"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                    >

                    <span class="sidebar-logo-fallback" style="display: none;">
                        <i class="bi bi-snow2"></i>
                    </span>
                </span>

                <span class="sidebar-logo-text">
                    <strong>ColdTrace</strong>
                    <span>{{ $displayRole }}</span>
                </span>
            </button>

            <nav class="sidebar-nav">

                {{-- ONE DASHBOARD ONLY --}}
                <a
                    href="{{ $dashboardUrl }}"
                    class="sidebar-link {{ $dashboardIsActive ? 'active' : '' }}"
                >
                    <span class="nav-icon"><i class="bi bi-grid-1x2"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>

                {{-- ADMIN MENU --}}
                @if ($user->isAdministrator())
                    <div class="nav-label">Admin Menu</div>

                    <a
                        href="{{ $usersUrl }}"
                        class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                    >
                        <span class="nav-icon"><i class="bi bi-people"></i></span>
                        <span class="nav-text">Users</span>
                    </a>

                    <a
                        href="{{ $ordersUrl }}"
                        class="sidebar-link {{ request()->routeIs('orders.*') ? 'active' : '' }}"
                    >
                        <span class="nav-icon"><i class="bi bi-bag-check"></i></span>
                        <span class="nav-text">Orders</span>
                    </a>

                 

                 
              
                    <a
                        href="{{ route('reports.index') }}"
                        class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                    >
                        <span class="nav-icon"><i class="bi bi-bar-chart-line"></i></span>
                        <span class="nav-text">Reports</span>
                    </a>
                @endif

                {{-- DRIVER MENU --}}
                @if ($user->isDriver())
                    <div class="nav-label">Driver Menu</div>

                    <a
                        href="{{ $driverOrdersUrl }}"
                        class="sidebar-link {{ request()->routeIs('driver.orders.*') ? 'active' : '' }}"
                    >
                        <span class="nav-icon"><i class="bi bi-bag-check"></i></span>
                        <span class="nav-text">My Orders</span>
                    </a>

                    @if (Route::has('driver.trips.index'))
                        <a
                            href="{{ $driverTripsUrl }}"
                            class="sidebar-link {{ request()->routeIs('driver.trips.*') ? 'active' : '' }}"
                        >
                            <span class="nav-icon"><i class="bi bi-truck"></i></span>
                            <span class="nav-text">My Trips</span>
                        </a>
                    @endif
                @endif

                {{-- CUSTOMER MENU --}}
                @if ($user->isReceiver())
                    <div class="nav-label">Customer Menu</div>

                    <a
                        href="{{ $customerOrdersUrl }}"
                        class="sidebar-link {{ request()->routeIs('customer.orders.*') ? 'active' : '' }}"
                    >
                        <span class="nav-icon"><i class="bi bi-box2-heart"></i></span>
                        <span class="nav-text">Orders</span>
                    </a>
                @endif
            </nav>

            <div class="sidebar-bottom">
                <div class="sidebar-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button type="submit" class="logout-button">
                            <span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span>
                            <span class="nav-text">Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <main class="dashboard-main">
            <section class="dashboard-content">
                <div class="page-container">
                    @yield('content')
                </div>
            </section>
        </main>
    </div>
@else
    <div class="page-wrapper">
        <div class="page-container">
            @yield('content')
        </div>
    </div>
@endauth

@stack('scripts')

<script>
    function setColdTraceSidebarState(isCollapsed) {
        const shell = document.getElementById('dashboardShell');
        const logoButton = document.getElementById('sidebarLogoButton');

        if (!shell) {
            return;
        }

        if (isCollapsed) {
            shell.classList.add('sidebar-collapsed');
        } else {
            shell.classList.remove('sidebar-collapsed');
        }

        if (logoButton) {
            logoButton.setAttribute('aria-expanded', String(!isCollapsed));
        }

        localStorage.setItem('coldtrace_sidebar_collapsed', isCollapsed ? '1' : '0');
    }

    function toggleColdTraceSidebar() {
        const shell = document.getElementById('dashboardShell');

        if (!shell) {
            return;
        }

        setColdTraceSidebarState(!shell.classList.contains('sidebar-collapsed'));
    }

    document.addEventListener('DOMContentLoaded', function () {
        const shell = document.getElementById('dashboardShell');
        const logoButton = document.getElementById('sidebarLogoButton');
        const backdrop = document.getElementById('sidebarBackdrop');

        if (!shell) {
            return;
        }

        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        const savedState = localStorage.getItem('coldtrace_sidebar_collapsed');

        if (isMobile) {
            setColdTraceSidebarState(true);
        } else if (savedState === '1') {
            setColdTraceSidebarState(true);
        } else {
            setColdTraceSidebarState(false);
        }

        if (logoButton) {
            logoButton.addEventListener('click', toggleColdTraceSidebar);
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                setColdTraceSidebarState(true);
            });
        }
    });
</script>

</body>
</html>
