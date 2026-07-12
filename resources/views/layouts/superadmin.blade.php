<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Super Admin') — Lajur Platform</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="{{ route('superadmin.plans.index') }}" class="brand">
            <span class="mark"><x-icon name="route" /></span> Lajur Platform
        </a>
        <nav class="admin-nav" aria-label="Menu super admin">
            <a href="{{ route('superadmin.plans.index') }}" class="{{ request()->routeIs('superadmin.plans.*') ? 'active' : '' }}">
                <x-icon name="gauge" /> Plans &amp; Fitur
            </a>
            <a href="{{ route('superadmin.tenants.index') }}" class="{{ request()->routeIs('superadmin.tenants.*') ? 'active' : '' }}">
                <x-icon name="users" /> Tenant
            </a>
        </nav>
        <div class="sidebar-foot">
            <div class="sidebar-user">
                Masuk sebagai
                <strong>{{ auth()->user()->name }}</strong>
            </div>
            <div class="sidebar-actions">
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
            <div>
                <span class="crumb">@yield('crumb', 'Lajur Platform')</span>
                <h1>@yield('heading', 'Super Admin')</h1>
            </div>
        </div>

        <div class="admin-content">
            @if (session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error" role="alert">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
