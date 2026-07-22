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
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body>
    @include('partials.splash', ['name' => 'Lajur', 'logo' => null])
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

    <script src="{{ asset('js/img-fallback.js') }}?v={{ filemtime(public_path('js/img-fallback.js')) }}" defer></script>
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
