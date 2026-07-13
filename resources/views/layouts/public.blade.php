<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $branding->name() === 'Lajur' ? 'Lajur - Rental Mobil Premium Kalimantan Timur' : $branding->name().' - Rental Mobil')</title>
    <meta name="description" content="{{ $branding->name() === 'Lajur' ? 'Lajur: sewa mobil premium di Kalimantan Timur. Armada terawat, harga transparan, proses cepat dan aman.' : $branding->name().': sewa mobil dengan armada terawat, harga transparan, proses cepat dan aman.' }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @if ($branding->accentColor())
        {{-- --accent-override: penanda uji; nilai menimpa var aksen brand --}}
        <style id="accent-override">/* --accent-override */
            :root {
                --amber: {{ $branding->accentColor() }};
                --amber-600: {{ $branding->accentDark() }};
                --amber-glow: {{ $branding->accentGlow() }};
            }
        </style>
    @endif
    @stack('head')
</head>
<body>
    <a href="#main" class="skip-link">Lewati ke konten</a>

    <header class="site-header">
        <div class="container nav">
            <a href="{{ route('home') }}" class="brand" aria-label="{{ $branding->name() }} beranda">
                @if ($branding->logoUrl())
                    <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->name() }}" style="width:38px;height:38px;border-radius:11px;object-fit:cover">
                @else
                    <span class="mark"><x-icon name="route" /></span>
                @endif
                {{ $branding->name() }}
            </a>
            <nav class="nav-links" id="nav-links" aria-label="Navigasi utama">
                <div class="nav-item has-dropdown">
                    <button type="button" class="nav-trigger" aria-haspopup="true" aria-expanded="false">
                        Sewa Mobil <x-icon name="chevron-down" class="nav-chevron" />
                    </button>
                    <div class="nav-dropdown" role="menu">
                        <a href="{{ route('home') }}#sewa" role="menuitem">Sewa Mobil</a>
                        <a href="{{ route('home') }}#cara" role="menuitem">Cara Sewa</a>
                    </div>
                </div>
                <div class="nav-item has-dropdown">
                    <button type="button" class="nav-trigger" aria-haspopup="true" aria-expanded="false">
                        Tentang <x-icon name="chevron-down" class="nav-chevron" />
                    </button>
                    <div class="nav-dropdown" role="menu">
                        <a href="{{ route('home') }}#tentang" role="menuitem">Tentang</a>
                        <a href="{{ route('home') }}#kenapa" role="menuitem">Kenapa Lajur</a>
                        <a href="{{ route('home') }}#testimoni" role="menuitem">Testimoni</a>
                    </div>
                </div>
                <a href="{{ route('home') }}#kontak">Kontak</a>
                <a href="{{ route('tracking.search') }}">Lacak Pesanan</a>
                <a href="{{ route('login') }}" class="nav-login-mobile">Masuk</a>
            </nav>
            <div class="nav-cta">
                <a href="{{ route('signup.pricing') }}" class="btn btn-ghost btn-sm">Daftar</a>
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
                        @if ($branding->logoUrl())
                            <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->name() }}" style="width:38px;height:38px;border-radius:11px;object-fit:cover">
                        @else
                            <span class="mark"><x-icon name="route" /></span>
                        @endif
                        {{ $branding->name() }}
                    </a>
                    <p>Rental mobil premium untuk wilayah Kalimantan Timur. Armada terawat, harga transparan, dan layanan yang bisa Anda percaya.</p>
                </div>
                <div>
                    <h4>Navigasi</h4>
                    <a href="{{ route('home') }}#sewa">Sewa Mobil</a>
                    <a href="{{ route('home') }}#cara">Cara Sewa</a>
                    <a href="{{ route('home') }}#kenapa">Kenapa Lajur</a>
                    <a href="{{ route('home') }}#testimoni">Testimoni</a>
                    <a href="{{ route('tracking.search') }}">Lacak Pesanan</a>
                </div>
                <div>
                    <h4>Kontak</h4>
                    <p>{{ $branding->address() }}</p>
                    <p>{{ $branding->phone() }}</p>
                    <p>{{ $branding->email() }}</p>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; {{ date('Y') }} {{ $branding->name() }}. Seluruh hak cipta dilindungi.</span>
                <a href="{{ route('login') }}">Masuk Admin</a>
            </div>
        </div>
    </footer>

    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
