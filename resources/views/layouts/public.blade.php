<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $branding->metaTitle())</title>
    <meta name="description" content="{{ $branding->metaDescription() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&family=Playfair+Display:wght@600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @if ($branding->accentColor() || $branding->hasPersonalization())
        {{-- --accent-override: penanda uji; nilai menimpa var aksen & gaya brand --}}
        <style id="accent-override">/* --accent-override */
            :root {
                @if ($branding->accentColor())
                    --amber: {{ $branding->accentColor() }};
                    --amber-600: {{ $branding->accentDark() }};
                    --amber-glow: {{ $branding->accentGlow() }};
                @endif
                @if ($branding->hasPersonalization())
                    --font-display: {!! $branding->fontDisplay() !!};
                    --font-body: {!! $branding->fontBody() !!};
                    --radius-sm: {!! $branding->radiusSm() !!};
                    --radius: {!! $branding->radius() !!};
                    --radius-lg: {!! $branding->radiusLg() !!};
                    --radius-pill: {!! $branding->radiusPill() !!};
                @endif
            }
            @if ($branding->hasPersonalization())
                .section { padding-block: {!! $branding->sectionSpacing() !!}; }
            @endif
        </style>
    @endif
    @stack('head')
</head>
<body data-effect="{{ $branding->sectionEffect() }}">
    {{-- Basis link etalase: url('/') untuk tenant, url('/demo') saat mode demo.
         Default aman untuk halaman yang tak menyetelnya (tracking, payment). --}}
    @php($storeBase ??= url('/'))
    @php($navIsCentral = \App\Tenancy\Domain::isCentral(request()->getHost()))
    @if ($branding->splashEnabled())
        @include('partials.splash', ['name' => $branding->name(), 'logo' => $branding->logoUrl()])
    @endif
    <a href="#main" class="skip-link">Lewati ke konten</a>

    <header class="site-header">
        <div class="container nav">
            <a href="{{ $storeBase }}" class="brand" aria-label="{{ $branding->name() }} beranda">
                @if ($branding->logoUrl())
                    <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->name() }}" style="height:38px;width:auto;max-width:160px;object-fit:contain">
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
                        <a href="{{ $storeBase }}#sewa" role="menuitem">Sewa Mobil</a>
                        <a href="{{ $storeBase }}#cara" role="menuitem">Cara Sewa</a>
                    </div>
                </div>
                <div class="nav-item has-dropdown">
                    <button type="button" class="nav-trigger" aria-haspopup="true" aria-expanded="false">
                        Tentang <x-icon name="chevron-down" class="nav-chevron" />
                    </button>
                    <div class="nav-dropdown" role="menu">
                        <a href="{{ $storeBase }}#tentang" role="menuitem">Tentang</a>
                        <a href="{{ $storeBase }}#kenapa" role="menuitem">Kenapa Kami</a>
                        <a href="{{ $storeBase }}#testimoni" role="menuitem">Testimoni</a>
                    </div>
                </div>
                <a href="{{ $storeBase }}#kontak">Kontak</a>
                <a href="{{ route('tracking.search') }}">Lacak Pesanan</a>
                @auth
                    <a href="{{ route(auth()->user()->homeRouteName()) }}" class="nav-login-mobile">Dashboard</a>
                @elseif ($navIsCentral)
                    <a href="{{ route('login') }}" class="nav-login-mobile">Masuk</a>
                @endauth
            </nav>
            <div class="nav-cta">
                @auth
                    {{-- Tenant sudah punya akun — "Daftar" tidak relevan, arahkan
                         langsung ke dashboard mereka (termasuk untuk lanjut berlangganan). --}}
                    <a href="{{ route(auth()->user()->homeRouteName()) }}" class="btn btn-dark btn-sm">
                        <x-icon name="dashboard" /> Dashboard
                    </a>
                @elseif ($navIsCentral)
                    {{-- Daftar/Masuk = jualan Lajur — hanya di domain pusat (/demo).
                         Customer di etalase tenant cukup lihat "Sewa Sekarang";
                         pintu owner tetap ada via "Masuk Admin" di footer. --}}
                    <a href="{{ route('signup.pricing') }}" class="btn btn-ghost btn-sm">Daftar</a>
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">
                        <x-icon name="key" /> Masuk
                    </a>
                @endauth
                <a href="{{ $storeBase }}#sewa" class="btn btn-primary btn-sm">Sewa Sekarang</a>
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
                    <a href="{{ $storeBase }}" class="brand" style="color:var(--ivory)">
                        @if ($branding->logoUrl())
                            <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->name() }}" style="height:38px;width:auto;max-width:160px;object-fit:contain">
                        @else
                            <span class="mark"><x-icon name="route" /></span>
                        @endif
                        {{ $branding->name() }}
                    </a>
                    <p>Rental mobil premium untuk wilayah Kalimantan Timur. Armada terawat, harga transparan, dan layanan yang bisa Anda percaya.</p>
                    @if ($branding->instagram() || $branding->facebook() || $branding->tiktok() || $branding->whatsappUrl())
                        <div class="footer-social">
                            @if ($branding->whatsappUrl())
                                <a href="{{ $branding->whatsappUrl() }}" target="_blank" rel="noopener" aria-label="WhatsApp"><x-icon name="whatsapp" /></a>
                            @endif
                            @if ($branding->instagram())
                                <a href="{{ $branding->instagram() }}" target="_blank" rel="noopener" aria-label="Instagram"><x-icon name="instagram" /></a>
                            @endif
                            @if ($branding->facebook())
                                <a href="{{ $branding->facebook() }}" target="_blank" rel="noopener" aria-label="Facebook"><x-icon name="facebook" /></a>
                            @endif
                            @if ($branding->tiktok())
                                <a href="{{ $branding->tiktok() }}" target="_blank" rel="noopener" aria-label="TikTok"><x-icon name="tiktok" /></a>
                            @endif
                        </div>
                    @endif
                </div>
                <div>
                    <h4>Navigasi</h4>
                    <a href="{{ $storeBase }}#sewa">Sewa Mobil</a>
                    <a href="{{ $storeBase }}#cara">Cara Sewa</a>
                    <a href="{{ $storeBase }}#kenapa">Kenapa Kami</a>
                    <a href="{{ $storeBase }}#testimoni">Testimoni</a>
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
                <span>&copy; {{ date('Y') }} {{ $branding->name() }}. Seluruh hak cipta dilindungi.
                    <span style="display:block;margin-top:4px;opacity:.65">Situs ini didukung oleh <a href="https://lajur.id" target="_blank" rel="noopener">Lajur</a>, produk dari Realwintech.</span>
                </span>
                <span style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">
                    <a href="{{ route('legal.terms') }}">Syarat &amp; Ketentuan</a>
                    <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a>
                    <a href="{{ route('login') }}" class="footer-admin-btn">Masuk Admin</a>
                </span>
            </div>
        </div>
    </footer>

    @if ($branding->whatsappUrl())
        <a href="{{ $branding->whatsappUrl() }}" class="wa-float" target="_blank" rel="noopener" aria-label="Chat WhatsApp {{ $branding->name() }}">
            <x-icon name="whatsapp" />
        </a>
    @endif

    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
