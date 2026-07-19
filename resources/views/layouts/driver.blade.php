<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard Driver') — Lajur</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .drv-shell{max-width:960px;margin:0 auto;padding:20px 18px 60px}
        .drv-top{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:18px 0;flex-wrap:wrap}
        .drv-brand{font-family:'Sora',sans-serif;font-weight:800;font-size:1.2rem;display:flex;align-items:center;gap:10px}
        .drv-user{font-size:.9rem;color:rgba(0,0,0,.6)}
        .drv-user strong{color:inherit}
        .drv-section-title{font-family:'Sora',sans-serif;font-weight:700;margin:26px 0 12px;font-size:1.05rem}
    </style>
    @stack('head')
</head>
<body>
<div class="drv-shell">
    <header class="drv-top">
        <div class="drv-brand"><x-icon name="route" /> Lajur — Driver</div>
        <div style="display:flex;align-items:center;gap:16px">
            <span class="drv-user">Halo, <strong>{{ auth()->user()->name }}</strong></span>
            <form action="{{ route('logout') }}" method="POST">@csrf
                <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="logout" /> Keluar</button>
            </form>
        </div>
    </header>

    @if (session('success'))
        <div class="alert alert-success" role="status"><x-icon name="check" /> <span>{{ session('success') }}</span></div>
    @endif

    @yield('content')
</div>
</body>
</html>
