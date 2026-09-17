<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'ColdTrace') · ColdTrace</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c2638">

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    @stack('styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php
    $user = auth()->user();
    $roleName = $user?->role?->name ?? 'Guest';
    $displayRole = $roleName;
    $roleKey = \Illuminate\Support\Str::slug($roleName);
    $initial = $user ? mb_strtoupper(mb_substr(trim($user->name), 0, 1)) : 'C';

    $navigation = [];

    if ($user?->isAdministrator()) {
        $navigation = [
            [
                'label' => 'Dashboard',
                'icon' => 'bi-grid-1x2-fill',
                'url' => route('dashboard'),
                'active' => request()->routeIs('dashboard'),
            ],
            [
                'label' => 'Orders',
                'icon' => 'bi-box-seam-fill',
                'url' => route('orders.index'),
                'active' => request()->routeIs('orders.*'),
            ],
            [
                'label' => 'Live monitoring',
                'icon' => 'bi-map-fill',
                'url' => route('monitoring.index'),
                'active' => request()->routeIs('monitoring.*'),
            ],
            [
                'label' => 'Users',
                'icon' => 'bi-people-fill',
                'url' => route('users.index'),
                'active' => request()->routeIs('users.*'),
            ],
        ];
    } elseif ($user?->isDriver()) {
        $navigation = [
            [
                'label' => 'Dashboard',
                'icon' => 'bi-grid-1x2-fill',
                'url' => route('driver.dashboard'),
                'active' => request()->routeIs('driver.dashboard'),
            ],
            [
                'label' => 'Orders & routes',
                'icon' => 'bi-signpost-split-fill',
                'url' => route('driver.orders.index'),
                'active' => request()->routeIs('driver.orders.*'),
            ],
            [
                'label' => 'My trips',
                'icon' => 'bi-truck-front-fill',
                'url' => route('driver.trips.index'),
                'active' => request()->routeIs('driver.trips.*'),
            ],
        ];
    }
@endphp

<body data-role="{{ $roleKey }}">
@auth
    <div class="dashboard-shell" id="dashboardShell">
        <aside class="dashboard-sidebar" id="dashboardSidebar" aria-label="ColdTrace navigation">
            <div class="sidebar-brand">
                <a href="{{ $navigation[0]['url'] ?? url('/') }}" class="sidebar-logo-box" aria-label="ColdTrace home">
                    <img
                        src="{{ asset('images/coldtrace-logo.png') }}"
                        alt=""
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                    >
                    <span class="sidebar-logo-fallback" style="display: none;">
                        <i class="bi bi-snow2"></i>
                    </span>
                </a>

                <span class="sidebar-logo-text">
                    <strong>ColdTrace</strong>
                    <span>{{ $displayRole }} workspace</span>
                </span>

                <button
                    type="button"
                    class="sidebar-collapse-button"
                    data-sidebar-toggle
                    aria-label="Collapse navigation"
                    aria-controls="dashboardSidebar"
                    aria-expanded="true"
                >
                    <i class="bi bi-layout-sidebar-inset"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-label">Workspace</div>

                @foreach ($navigation as $item)
                    <a
                        href="{{ $item['url'] }}"
                        class="sidebar-link {{ $item['active'] ? 'active' : '' }}"
                        title="{{ $item['label'] }}"
                        @if ($item['active']) aria-current="page" @endif
                    >
                        <span class="nav-icon"><i class="bi {{ $item['icon'] }}"></i></span>
                        <span class="nav-text">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="sidebar-bottom">
                <div class="sidebar-user">
                    <span class="sidebar-user-avatar">{{ $initial }}</span>
                    <span class="sidebar-user-copy">
                        <strong>{{ $user->name }}</strong>
                        <span>{{ $displayRole }}</span>
                    </span>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-button" title="Sign out">
                        <span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span>
                        <span class="nav-text">Sign out</span>
                    </button>
                </form>
            </div>
        </aside>

        <button
            type="button"
            class="sidebar-backdrop"
            id="sidebarBackdrop"
            aria-label="Close navigation"
        ></button>

        <main class="dashboard-main">
            <header class="dashboard-topbar">
                <div class="topbar-left">
                    <div class="topbar-context">
                        <span>{{ $displayRole }} workspace</span>
                        <strong>@yield('title', 'Dashboard')</strong>
                    </div>
                </div>

                <div class="topbar-right">
                    <span class="topbar-date">
                        <i class="bi bi-calendar3"></i>
                        {{ now()->format('D, M j') }}
                    </span>
                </div>
            </header>

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

<dialog class="ct-dialog" id="ctConfirmDialog" aria-labelledby="ctConfirmTitle">
    <form method="dialog">
        <div class="ct-dialog-body">
            <span class="ct-dialog-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <div>
                <h2 id="ctConfirmTitle">Please confirm</h2>
                <p id="ctConfirmMessage">Are you sure you want to continue?</p>
            </div>
        </div>
        <div class="ct-dialog-actions">
            <button type="submit" value="cancel" class="ct-button ct-button-light">Keep it</button>
            <button type="submit" value="confirm" class="ct-button ct-button-danger" id="ctConfirmAccept">Continue</button>
        </div>
    </form>
</dialog>

<script>
/*
 * Replaces the browser's confirm() box for destructive actions. Any form with a
 * data-confirm attribute asks here first, so the wording can explain what is
 * about to happen instead of showing a bare "Are you sure?".
 */
(function () {
    const dialog = document.getElementById('ctConfirmDialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const message = document.getElementById('ctConfirmMessage');
    const accept = document.getElementById('ctConfirmAccept');
    let pendingForm = null;

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
        if (form === pendingForm) return;

        event.preventDefault();
        pendingForm = form;
        message.textContent = form.dataset.confirm;
        accept.textContent = form.dataset.confirmAction || 'Continue';
        dialog.showModal();
    });

    dialog.addEventListener('close', function () {
        const form = pendingForm;
        pendingForm = null;

        if (dialog.returnValue === 'confirm' && form) {
            form.submit();
        }
    });
})();
</script>

<x-maps.truck-marker-assets />
<x-maps.live-location />
@stack('scripts')

<script>
    (() => {
        const shell = document.getElementById('dashboardShell');

        if (!shell) {
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 768px)');
        const toggleButtons = document.querySelectorAll('[data-sidebar-toggle]');
        const backdrop = document.getElementById('sidebarBackdrop');
        const navigationLinks = document.querySelectorAll('.sidebar-link');

        window.setColdTraceSidebarState = (isCollapsed) => {
            shell.classList.toggle('sidebar-collapsed', isCollapsed);

            toggleButtons.forEach((button) => {
                button.setAttribute('aria-expanded', String(!isCollapsed));
                const label = isCollapsed ? 'Expand navigation' : 'Collapse navigation';
                button.setAttribute('aria-label', label);
                button.setAttribute('title', label);
            });
        };

        window.toggleColdTraceSidebar = () => {
            window.setColdTraceSidebarState(
                !shell.classList.contains('sidebar-collapsed')
            );
        };

        const applyResponsiveState = () => {
            // Desktop pages always open with readable navigation labels.
            window.setColdTraceSidebarState(mobileQuery.matches);
        };

        toggleButtons.forEach((button) => {
            button.addEventListener('click', window.toggleColdTraceSidebar);
        });

        backdrop?.addEventListener('click', () => {
            window.setColdTraceSidebarState(true);
        });

        navigationLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (mobileQuery.matches) {
                    window.setColdTraceSidebarState(true);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && mobileQuery.matches) {
                window.setColdTraceSidebarState(true);
            }
        });

        mobileQuery.addEventListener?.('change', applyResponsiveState);
        applyResponsiveState();
    })();
</script>
</body>
</html>
