<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Lajur — Rental Mobil Premium Kalimantan Timur')</title>
    <meta name="description" content="Lajur — sewa mobil premium di Kalimantan Timur. Armada terawat, harga transparan, proses cepat dan aman.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body>
    <a href="#main" class="skip-link">Lewati ke konten</a>

    <header class="site-header">
        <div class="container nav">
            <a href="{{ route('home') }}" class="brand" aria-label="Lajur beranda">
                <span class="mark"><x-icon name="route" /></span>
                Lajur
            </a>
            <nav class="nav-links" id="nav-links" aria-label="Navigasi utama">
                <a href="{{ route('home') }}#sewa">Sewa Mobil</a>
                <a href="{{ route('home') }}#cara">Cara Sewa</a>
                <a href="{{ route('home') }}#kenapa">Kenapa Lajur</a>
                <a href="{{ route('home') }}#testimoni">Testimoni</a>
                <a href="{{ route('home') }}#tentang">Tentang</a>
                <a href="{{ route('home') }}#kontak">Kontak</a>
                <a href="{{ route('login') }}" class="nav-login-mobile">Masuk</a>
            </nav>
            <div class="nav-cta">
                <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">
                    <x-icon name="key" /> Masuk
                </a>
                <a href="{{ route('home') }}#sewa" class="btn btn-primary btn-sm">Sewa Sekarang</a>
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
                    <a href="{{ route('home') }}" class="brand" style="color:var(--ivory)">
                        <span class="mark"><x-icon name="route" /></span> Lajur
                    </a>
                    <p>Rental mobil premium untuk wilayah Kalimantan Timur. Armada terawat, harga transparan, dan layanan yang bisa Anda percaya.</p>
                </div>
                <div>
                    <h4>Navigasi</h4>
                    <a href="{{ route('home') }}#sewa">Sewa Mobil</a>
                    <a href="{{ route('home') }}#cara">Cara Sewa</a>
                    <a href="{{ route('home') }}#kenapa">Kenapa Lajur</a>
                    <a href="{{ route('home') }}#testimoni">Testimoni</a>
                </div>
                <div>
                    <h4>Kontak</h4>
                    <p>Samarinda, Kalimantan Timur</p>
                    <p>+62 812-0000-0000</p>
                    <p>halo@lajur.id</p>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; {{ date('Y') }} Lajur. Seluruh hak cipta dilindungi.</span>
                <a href="{{ route('login') }}">Masuk Admin</a>
            </div>
        </div>
    </footer>

    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
