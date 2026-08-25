<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('Dashboard')) — {{ __('Inventory Management') }}</title>

    {{-- Fonts & icons --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- App stylesheet --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    @if (app()->getLocale() === 'ar')
        <style>
            body, button, input, select, textarea {
                font-family: 'Tajawal', 'Inter', sans-serif !important;
            }
        </style>
    @endif

    @stack('styles')
</head>
<body>

    <div class="app-shell" id="appShell">

        {{-- =========================================================
             Sidebar
        ========================================================== --}}
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-mark">IM</div>
                <div class="sidebar-brand-text">
                    <strong>{{ __('Inventory') }}</strong>
                    <span>{{ __('MANAGEMENT') }}</span>
                </div>
            </div>

            <nav class="sidebar-nav">

                <div class="sidebar-group">
                    <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->is('dashboard') || request()->is('/') ? 'active' : '' }}">
                        <i class="fa-solid fa-gauge"></i> {{ __('Dashboard') }}
                    </a>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-group-label">{{ __('Inventory') }}</div>
                    <a href="{{ route('products.index') }}" class="sidebar-link {{ request()->is('products*') ? 'active' : '' }}">
                        <i class="fa-solid fa-box"></i> {{ __('Products') }}
                    </a>
                    <a href="{{ route('categories.index') }}" class="sidebar-link {{ request()->is('categories*') ? 'active' : '' }}">
                        <i class="fa-solid fa-tags"></i> {{ __('Categories') }}
                    </a>
                    <a href="{{ route('stock-in.index') }}" class="sidebar-link {{ request()->is('stock-in*') ? 'active' : '' }}">
                        <i class="fa-solid fa-inbox"></i> {{ __('Stock In') }}
                    </a>
                    <a href="{{ route('stock-out.index') }}" class="sidebar-link {{ request()->is('stock-out*') ? 'active' : '' }}">
                        <i class="fa-solid fa-arrow-up-from-bracket"></i> {{ __('Stock Out') }}
                    </a>
                    <a href="{{ route('transfers.index') }}" class="sidebar-link {{ request()->is('transfers*') ? 'active' : '' }}">
                        <i class="fa-solid fa-truck-ramp-box"></i> {{ __('Transfers') }}
                    </a>
                    <a href="{{ route('adjustments.index') }}" class="sidebar-link {{ request()->is('adjustments*') ? 'active' : '' }}">
                        <i class="fa-solid fa-sliders"></i> {{ __('Adjustments') }}
                    </a>
                    <a href="{{ route('stock-movements.index') }}" class="sidebar-link {{ request()->is('stock-movements*') ? 'active' : '' }}">
                        <i class="fa-solid fa-right-left"></i> {{ __('Stock Movements') }}
                    </a>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-group-label">{{ __('Partners') }}</div>
                    <a href="{{ route('suppliers.index') }}" class="sidebar-link {{ request()->is('suppliers*') ? 'active' : '' }}">
                        <i class="fa-solid fa-truck-field"></i> {{ __('Suppliers') }}
                    </a>
                    <a href="{{ route('distributors.index') }}" class="sidebar-link {{ request()->is('distributors*') ? 'active' : '' }}">
                        <i class="fa-solid fa-truck-fast"></i> {{ __('Distributors') }}
                    </a>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-group-label">{{ __('Management') }}</div>
                    <a href="{{ route('warehouses.index') }}" class="sidebar-link {{ request()->is('warehouses*') ? 'active' : '' }}">
                        <i class="fa-solid fa-warehouse"></i> {{ __('Warehouses') }}
                    </a>
                    <a href="{{ route('reports.index') }}" class="sidebar-link {{ request()->is('reports*') ? 'active' : '' }}">
                        <i class="fa-solid fa-chart-simple"></i> {{ __('Reports') }}
                    </a>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-group-label">{{ __('Account') }}</div>
                    @auth
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('users.index') }}" class="sidebar-link {{ request()->is('users*') ? 'active' : '' }}">
                                <i class="fa-solid fa-users"></i> {{ __('Users') }}
                            </a>
                            <a href="{{ route('activity-logs.index') }}" class="sidebar-link {{ request()->is('activity-logs*') ? 'active' : '' }}">
                                <i class="fa-solid fa-clipboard-list"></i> {{ __('Activity Logs') }}
                            </a>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="sidebar-link {{ request()->is('profile*') ? 'active' : '' }}">
                            <i class="fa-solid fa-user-gear"></i> {{ __('Profile') }}
                        </a>
                    @endauth
                </div>

            </nav>

            @auth
                <div class="sidebar-footer">
                    <div class="sidebar-avatar">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                    <div class="sidebar-footer-text">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ __(auth()->user()->role?->name ?? 'User') }}</span>
                    </div>
                </div>
            @endauth
        </aside>

        {{-- =========================================================
             Main column: topbar + page content
        ========================================================== --}}
        <div class="main-col">

            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" id="menuToggle" aria-label="Toggle Navigation Sidebar" title="{{ __('Toggle Navigation Sidebar') }}">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="page-title-block">
                        <h1>@yield('title', __('Dashboard'))</h1>
                        @hasSection('subtitle')
                            <p>@yield('subtitle')</p>
                        @endif
                    </div>
                </div>

                <div class="topbar-right">

                    {{-- Notification bell.
                         Only rendered for users holding receive_notifications
                         (with a phone number) — LowStockNotifier enforces that
                         itself, so the view cannot leak alerts to someone who
                         was not granted the permission. --}}
                    @auth
                        @php
                            $lowStockAlerts = App\Support\LowStockNotifier::for(auth()->user());
                        @endphp

                        @if (auth()->user()->canReceiveNotifications())
                            <div class="notif-wrap">
                                <button type="button" class="btn btn-secondary btn-sm" id="notifToggle"
                                        aria-haspopup="true" aria-expanded="false"
                                        title="{{ __('Notifications') }}">
                                    <i class="fa-regular fa-bell"></i>
                                    @if ($lowStockAlerts->isNotEmpty())
                                        <span class="notif-badge">{{ $lowStockAlerts->count() }}</span>
                                    @endif
                                </button>

                                <div class="notif-panel" id="notifPanel" hidden>
                                    <div class="notif-head">{{ __('Low Stock Alerts') }}</div>

                                    @forelse ($lowStockAlerts as $alert)
                                        <a href="{{ route('reports.low-stock', ['product_id' => $alert->product_id]) }}" class="notif-item">
                                            <strong>{{ $alert->product_name }}</strong>
                                            <span>
                                                {{ $alert->warehouse_name }} &middot;
                                                {{ (int) $alert->current_stock }} / {{ (int) $alert->minimum_stock }}
                                            </span>
                                        </a>
                                    @empty
                                        <div class="notif-empty">{{ __('Nothing needs attention.') }}</div>
                                    @endforelse

                                    @if ($lowStockAlerts->isNotEmpty())
                                        <a href="{{ route('reports.low-stock') }}" class="notif-foot">{{ __('View all') }}</a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endauth

                    {{-- Language Switcher Button --}}
                    @if (app()->getLocale() === 'ar')
                        <a href="{{ route('lang.switch', 'en') }}" class="btn btn-secondary btn-sm" title="Switch to English" style="gap:6px;">
                            <i class="fa-solid fa-globe"></i> English
                        </a>
                    @else
                        <a href="{{ route('lang.switch', 'ar') }}" class="btn btn-secondary btn-sm" title="التحويل إلى العربية" style="gap:6px;">
                            <i class="fa-solid fa-globe"></i> العربية
                        </a>
                    @endif

                    @auth
                        <a href="{{ route('profile.edit') }}" class="topbar-user" title="{{ __('Profile') }}">
                            <div class="sidebar-avatar">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </div>
                            <div class="topbar-user-text">
                                <strong>{{ auth()->user()->name }}</strong>
                                <span>{{ __(auth()->user()->role?->name ?? 'User') }}</span>
                            </div>
                        </a>

                        <form action="{{ route('logout') }}" method="POST" style="margin-left: 6px; margin-right: 6px;">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm" title="{{ __('Logout') }}">
                                <i class="fa-solid fa-right-from-bracket"></i> {{ __('Logout') }}
                            </button>
                        </form>
                    @endauth
                </div>
            </header>

            <main class="page-content">
                @yield('content')
            </main>

        </div>
    </div>

    {{-- Sidebar Toggle & State Persistence Script --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.getElementById('menuToggle');
            const appShell = document.getElementById('appShell');
            const sidebar = document.getElementById('sidebar');

            // Restore collapsed preference from localStorage
            if (localStorage.getItem('sidebar_collapsed') === 'true') {
                appShell.classList.add('sidebar-collapsed');
            }

            if (menuToggle) {
                menuToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (window.innerWidth <= 860) {
                        sidebar.classList.toggle('open');
                    } else {
                        appShell.classList.toggle('sidebar-collapsed');
                        const isCollapsed = appShell.classList.contains('sidebar-collapsed');
                        localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
                    }
                });
            }

            // Notification bell dropdown. Only present for users holding the
            // receive_notifications permission, so guard before binding.
            const notifToggle = document.getElementById('notifToggle');
            const notifPanel = document.getElementById('notifPanel');

            if (notifToggle && notifPanel) {
                notifToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const open = !notifPanel.hidden;
                    notifPanel.hidden = open;
                    notifToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
                });

                // Click anywhere else, or press Escape, to dismiss.
                document.addEventListener('click', () => {
                    notifPanel.hidden = true;
                    notifToggle.setAttribute('aria-expanded', 'false');
                });

                notifPanel.addEventListener('click', (e) => e.stopPropagation());

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        notifPanel.hidden = true;
                        notifToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
