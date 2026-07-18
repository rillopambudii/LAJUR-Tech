<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Lajur — Software Manajemen Rental Mobil')</title>
    <meta name="description" content="@yield('meta', 'Lajur bantu pemilik rental mobil kelola booking, armada, BBM, dan laporan dari satu dashboard. Coba gratis 14 hari.')">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    {{-- Loading screen: tampil sekali per sesi. Cek dulu SEBELUM body render agar
         tak berkedip saat pindah halaman. --}}
    <script>try{if(sessionStorage.getItem('lajurSplash')){document.documentElement.classList.add('no-splash');}else{sessionStorage.setItem('lajurSplash','1');}}catch(e){}</script>
    <style>
        #splash{position:fixed;inset:0;z-index:9999;background:#0F1B33;display:grid;place-items:center;
            transition:opacity .55s ease,visibility .55s ease}
        #splash.hide{opacity:0;visibility:hidden}
        .no-splash #splash{display:none}
        .splash-inner{display:flex;flex-direction:column;align-items:center;gap:24px}
        .splash-logo{display:flex;align-items:center;gap:13px;color:#F7F8FB;
            font-family:'Sora',system-ui,sans-serif;font-weight:800;font-size:2rem;letter-spacing:-.02em;
            animation:splashPop .6s cubic-bezier(.2,.7,.2,1) both}
        .splash-mark{width:48px;height:48px;border-radius:13px;background:#E7B24C;color:#0F1B33;
            display:grid;place-items:center}
        .splash-mark svg{width:28px;height:28px}
        .splash-bar{width:160px;height:4px;border-radius:4px;background:rgba(247,248,251,.14);overflow:hidden}
        .splash-bar span{display:block;height:100%;width:42%;border-radius:4px;background:#E7B24C;
            animation:splashSlide 1.15s ease-in-out infinite}
        @keyframes splashSlide{0%{transform:translateX(-130%)}100%{transform:translateX(360%)}}
        @keyframes splashPop{from{opacity:0;transform:translateY(12px) scale(.96)}to{opacity:1;transform:none}}
        /* Jaring pengaman tanpa JS: sembunyi otomatis setelah 4 detik. */
        @keyframes splashSafety{to{opacity:0;visibility:hidden}}
        #splash{animation:splashSafety .01s linear 4s forwards}
        @media(prefers-reduced-motion:reduce){#splash{display:none}}
    </style>
    @stack('head')
</head>
<body>
    <div id="splash" aria-hidden="true">
        <div class="splash-inner">
            <div class="splash-logo"><span class="splash-mark"><x-icon name="route" /></span> Lajur</div>
            <div class="splash-bar"><span></span></div>
        </div>
    </div>
    <a href="#main" class="skip-link">Lewati ke konten</a>

    {{-- Page induk = selalu brand Lajur (bukan brand tenant). --}}
    <header class="site-header">
        <div class="container nav">
            <a href="{{ url('/') }}" class="brand" aria-label="Lajur beranda">
                <span class="mark"><x-icon name="route" /></span> Lajur
            </a>
            <nav class="nav-links" id="nav-links" aria-label="Navigasi utama">
                <a href="{{ url('/') }}#fitur">Fitur</a>
                <a href="{{ url('/') }}#cara-kerja">Cara Kerja</a>
                <a href="{{ route('signup.pricing') }}">Harga</a>
                <a href="{{ url('/demo') }}">Demo</a>
                <a href="{{ route('login') }}" class="nav-login-mobile">Masuk</a>
            </nav>
            <div class="nav-cta">
                @auth
                    <a href="{{ route(auth()->user()->homeRouteName()) }}" class="btn btn-ghost btn-sm">
                        <x-icon name="dashboard" /> Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">
                        <x-icon name="key" /> Masuk
                    </a>
                @endauth
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary btn-sm">Coba Gratis 14 Hari</a>
                <button class="nav-toggle" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="nav-links">
                    <x-icon name="menu" />
                </button>
            </div>
        </div>
    </header>

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="{{ url('/') }}" class="brand" style="color:var(--ivory)">
                        <span class="mark"><x-icon name="route" /></span> Lajur
                    </a>
                    <p>{{ config('lajur.tagline') }} Kelola booking, armada, driver, BBM, dan laporan dari satu dashboard.</p>
                </div>
                <div>
                    <h4>Produk</h4>
                    <a href="{{ url('/') }}#fitur">Fitur</a>
                    <a href="{{ url('/') }}#cara-kerja">Cara Kerja</a>
                    <a href="{{ route('signup.pricing') }}">Harga</a>
                    <a href="{{ url('/demo') }}">Lihat Demo</a>
                </div>
                <div>
                    <h4>Perusahaan</h4>
                    <a href="{{ route('legal.terms') }}">Syarat &amp; Ketentuan</a>
                    <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a>
                    <a href="https://wa.me/{{ config('lajur.whatsapp') }}" target="_blank" rel="noopener">WhatsApp</a>
                </div>
                <div>
                    <h4>Kontak</h4>
                    <p>{{ config('legal.city') }}, Kalimantan Timur</p>
                    <p><a href="https://wa.me/{{ config('lajur.whatsapp') }}" target="_blank" rel="noopener">Chat WhatsApp</a></p>
                    <p><a href="mailto:{{ config('lajur.email') }}">{{ config('lajur.email') }}</a></p>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; {{ date('Y') }} Lajur oleh RealwinTech. Seluruh hak cipta dilindungi.</span>
                <span style="display:flex;gap:18px;flex-wrap:wrap">
                    <a href="{{ route('legal.terms') }}">Syarat</a>
                    <a href="{{ route('legal.privacy') }}">Privasi</a>
                    <a href="{{ route('login') }}">Masuk</a>
                </span>
            </div>
        </div>
    </footer>

    <script>
        (function(){
            var s=document.getElementById('splash');
            if(!s||document.documentElement.classList.contains('no-splash')){if(s)s.remove();return;}
            var start=Date.now(),MIN=650; // tampil minimal biar terasa disengaja, bukan berkedip
            function hide(){
                setTimeout(function(){
                    s.classList.add('hide');
                    setTimeout(function(){s.remove();},600);
                },Math.max(0,MIN-(Date.now()-start)));
            }
            if(document.readyState==='complete')hide();
            else window.addEventListener('load',hide);
            setTimeout(hide,3500); // jaring pengaman
        })();
    </script>
    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
