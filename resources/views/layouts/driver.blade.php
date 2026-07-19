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
        .drv-shell{max-width:760px;margin:0 auto;padding:20px 18px 60px}
        .drv-top{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:18px 0;flex-wrap:wrap}
        .drv-brand{font-family:'Sora',sans-serif;font-weight:800;font-size:1.2rem;display:flex;align-items:center;gap:10px}
        .drv-brand svg{width:34px;height:34px;padding:7px;border-radius:10px;background:var(--petrol);color:var(--amber)}
        .drv-user{font-size:.9rem;color:var(--graphite)}
        .drv-user strong{color:inherit}
        .drv-section-title{font-family:'Sora',sans-serif;font-weight:700;margin:28px 0 12px;font-size:1.05rem}

        .drv-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;flex-wrap:wrap;
            background:var(--petrol);color:var(--ivory);border-radius:var(--radius-lg);padding:24px 26px;box-shadow:var(--shadow)}
        .drv-hero .eyebrow{margin-bottom:6px}
        .drv-title{font-size:1.45rem;color:var(--ivory)}
        .drv-stats{display:flex;gap:10px}
        .drv-stat{min-width:86px;text-align:center;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
            border-radius:var(--radius);padding:10px 14px}
        .drv-stat .n{display:block;font-family:var(--font-mono);font-size:1.4rem;font-weight:700;color:var(--amber);line-height:1.2}
        .drv-stat .l{font-size:.78rem;color:rgba(247,248,251,.7)}

        .drv-card{display:flex;align-items:center;gap:16px;background:var(--white);border:1px solid var(--ivory-200);
            border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);padding:16px 18px;margin-bottom:12px}
        .drv-card.now{border-color:rgba(231,178,76,.55);box-shadow:0 10px 26px -12px var(--amber-glow),var(--shadow-sm)}
        .drv-card.past{opacity:.72}
        .drv-date{flex:none;width:54px;text-align:center;background:var(--ivory);border:1px solid var(--ivory-200);
            border-radius:var(--radius);padding:7px 0}
        .drv-card.now .drv-date{background:var(--amber);border-color:var(--amber);color:#2a1c05}
        .drv-date .d{display:block;font-family:var(--font-mono);font-weight:700;font-size:1.25rem;line-height:1.15}
        .drv-date .m{font-size:.74rem;text-transform:uppercase;letter-spacing:.08em}
        .drv-info{flex:1;min-width:0}
        .drv-car{font-family:'Sora',sans-serif;font-weight:700;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .drv-now-badge{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;
            background:var(--amber-glow);color:var(--amber-600);border-radius:var(--radius-pill);padding:3px 10px}
        .drv-row{display:flex;align-items:center;gap:7px;font-size:.88rem;color:var(--graphite);margin-top:3px}
        .drv-row svg{width:15px;height:15px;flex:none;color:var(--graphite-300)}
        .drv-side{flex:none;display:flex;flex-direction:column;align-items:flex-end;gap:8px}
        .drv-empty{text-align:center;color:var(--graphite);background:var(--white);border:1px dashed var(--ivory-200);
            border-radius:var(--radius-lg);padding:38px 20px}
        .drv-empty svg{width:34px;height:34px;margin:0 auto 10px;color:var(--graphite-300)}
        @media (max-width:560px){
            .drv-card{flex-wrap:wrap}
            .drv-side{flex-direction:row;width:100%;justify-content:space-between;align-items:center}
        }
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
