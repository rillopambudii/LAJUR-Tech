<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Super Admin') - Lajur Platform</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    @stack('head')
</head>
<body class="admin">
<div class="sidebar-backdrop"></div>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="{{ route('superadmin.plans.index') }}" class="brand">
            <span class="mark"><x-icon name="route" /></span> Lajur Platform
        </a>
        <nav class="admin-nav" aria-label="Menu super admin">
            <a href="{{ route('superadmin.plans.index') }}" class="{{ request()->routeIs('superadmin.plans.*') ? 'active' : '' }}">
                <x-icon name="tag" /> Plans &amp; Fitur
            </a>
            <a href="{{ route('superadmin.tenants.index') }}" class="{{ request()->routeIs('superadmin.tenants.*') ? 'active' : '' }}">
                <x-icon name="users" /> Tenant
            </a>
            <a href="{{ route('superadmin.landing.edit') }}" class="{{ request()->routeIs('superadmin.landing.*') ? 'active' : '' }}">
                <x-icon name="edit" /> Konten Landing
            </a>
        </nav>
        <div class="sidebar-foot">
            <div class="sidebar-user">
                Masuk sebagai
                <strong>{{ auth()->user()->name }}</strong>
            </div>
            <div class="sidebar-actions">
                <a href="{{ route('home') }}" class="sidebar-btn" target="_blank" rel="noopener">
                    <x-icon name="eye" /> <span>Lihat Situs</span>
                </a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="sidebar-btn danger">
                        <x-icon name="logout" /> <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <div style="display:flex;align-items:center;gap:14px">
                <button class="mobile-menu" type="button" aria-label="Buka menu"><x-icon name="menu" /></button>
                <div>
                    <span class="crumb">@yield('crumb', 'Lajur Platform')</span>
                    <h1>@yield('heading', 'Super Admin')</h1>
                </div>
            </div>
            @yield('topbar-action')
        </div>

        <div class="admin-content">
            @if (session('success'))
                <div class="alert alert-success" role="status">
                    <x-icon name="check" /> <span>{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-error" role="alert">
                    <x-icon name="alert" /> <span>{{ session('error') }}</span>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <x-icon name="alert" />
                    <span>Terdapat kesalahan pada isian:
                        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </span>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>

<script src="{{ asset('js/img-fallback.js') }}?v={{ filemtime(public_path('js/img-fallback.js')) }}" defer></script>
<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
