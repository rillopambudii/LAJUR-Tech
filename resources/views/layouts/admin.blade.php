<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — Lajur Admin</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('head')
</head>
<body class="admin">
@php
    $unread = \App\Models\ContactMessage::where('is_read', false)->count();
@endphp
<div class="sidebar-backdrop"></div>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="{{ route('admin.dashboard') }}" class="brand">
            <span class="mark"><x-icon name="route" /></span> Lajur
        </a>
        <nav class="admin-nav" aria-label="Menu admin">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <x-icon name="dashboard" /> Dashboard
            </a>
            <a href="{{ route('admin.cars.index') }}" class="{{ request()->routeIs('admin.cars.*') ? 'active' : '' }}">
                <x-icon name="car" /> Mobil
            </a>
            <a href="{{ route('admin.drivers.index') }}" class="{{ request()->routeIs('admin.drivers.*') ? 'active' : '' }}">
                <x-icon name="users" /> Driver
            </a>
            <a href="{{ route('admin.bookings.index') }}" class="{{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">
                <x-icon name="list" /> Booking
            </a>
            <a href="{{ route('admin.calendar') }}" class="{{ request()->routeIs('admin.calendar') ? 'active' : '' }}">
                <x-icon name="calendar" /> Kalender
            </a>
            @php($currentTenant = app(\App\Tenancy\TenantManager::class)->current())
            @if ($currentTenant?->hasFeature('gps_tracking'))
            <a href="{{ route('admin.tracking') }}" class="{{ request()->routeIs('admin.tracking') ? 'active' : '' }}">
                <x-icon name="pin" /> Pelacakan
            </a>
            @endif
            @if ($currentTenant?->hasFeature('fuel_tracking'))
            <a href="{{ route('admin.fuel.index') }}" class="{{ request()->routeIs('admin.fuel.*') ? 'active' : '' }}">
                <x-icon name="fuel" /> BBM &amp; Solar
            </a>
            @endif
            <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports') ? 'active' : '' }}">
                <x-icon name="gauge" /> Laporan
            </a>
            @if ($currentTenant?->hasFeature('ai_assistant'))
            <a href="{{ route('admin.assistant') }}" class="{{ request()->routeIs('admin.assistant') ? 'active' : '' }}">
                <x-icon name="sparkle" /> Asisten AI
            </a>
            @endif
            <a href="{{ route('admin.testimonials.index') }}" class="{{ request()->routeIs('admin.testimonials.*') ? 'active' : '' }}">
                <x-icon name="star" /> Testimoni
            </a>
            <a href="{{ route('admin.messages.index') }}" class="{{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">
                <x-icon name="chat" /> Pesan
                @if ($unread > 0)<span class="badge">{{ $unread }}</span>@endif
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
                    <span class="crumb">@yield('crumb', 'Lajur Admin')</span>
                    <h1>@yield('heading', 'Dashboard')</h1>
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

<script src="{{ asset('js/admin.js') }}" defer></script>
@stack('scripts')
</body>
</html>
