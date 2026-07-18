@extends('layouts.platform')

@section('title', 'Lajur — Software Manajemen Rental Mobil')

@section('content')

{{-- ============ HERO ============ --}}
<section class="hero" id="home">
    <div class="container">
        <div class="hero-content">
            <span class="eyebrow hero-eyebrow">Untuk pemilik usaha rental mobil</span>
            <h1 class="hero-title">
                <span class="hero-title__lead">Kelola bisnis rental Anda,</span>
                <span class="hero-title__reveal">tanpa buku catatan.</span>
            </h1>
            <p>Booking, armada, driver, BBM, sampai laporan keuangan — semua dalam satu dashboard.
                Berhenti menebak ke mana uang dan mobil Anda pergi.</p>
            <div class="hero-actions">
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">Coba Gratis 14 Hari <x-icon name="arrow-right" /></a>
                <a href="{{ url('/demo') }}" class="btn btn-light">Lihat Demo</a>
            </div>
        </div>
    </div>
</section>

{{-- ============ PREVIEW PRODUK ============ --}}
<div class="product-band">
    <div class="container">
        <div class="browser reveal">
            <div class="browser-bar"><span></span><span></span><span></span></div>
            <img src="{{ asset('img/product-dashboard.jpg') }}" width="1600" height="955"
                 alt="Dashboard Lajur — kalender ketersediaan armada dalam satu layar" loading="lazy">
        </div>
    </div>
</div>

{{-- ============ MASALAH ============ --}}
<section class="section">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <span class="eyebrow" style="justify-content:center">Kenapa Lajur</span>
            <h2 class="section-title">Rental jalan, tapi uangnya bocor di mana-mana</h2>
            <p class="section-sub">Tiga hal yang paling sering bikin pemilik rental rugi diam-diam.</p>
        </div>
        <div class="features">
            @foreach ([
                ['fuel', 'Solar &amp; bensin bocor', 'Sopir isi 50 ribu, lapor 100 ribu. Sebulan hilang jutaan tanpa jejak.'],
                ['calendar', 'Jadwal tabrakan', 'Dua orang, mobil sama, hari sama. Catatan di buku, mudah lupa siapa yang mana.'],
                ['pin', 'Mobil entah di mana', 'Disewa ke luar kota tanpa izin, dipakai narik sendiri. Anda tak tahu sampai telat.'],
            ] as $f)
                <div class="feature reveal">
                    <div class="ico"><x-icon name="{{ $f[0] }}" /></div>
                    <h3>{!! $f[1] !!}</h3>
                    <p>{{ $f[2] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ FITUR ============ --}}
<section class="section" id="fitur" style="background:var(--ivory-200)">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <span class="eyebrow" style="justify-content:center">Fitur</span>
            <h2 class="section-title">Semua yang Anda butuh untuk kelola rental</h2>
            <p class="section-sub">Satu dashboard, dari terima booking sampai laporan bulanan.</p>
        </div>
        <div class="features">
            @foreach ([
                ['fuel', 'BBM anti-kebocoran', 'Catat tiap pengisian. Lajur hitung sendiri konsumsinya dan tandai yang tak wajar — isi melebihi tangki, boros mendadak.', null],
                ['calendar', 'Kalender anti-tabrakan', 'Semua mobil dan tanggal dalam satu layar. Sistem menolak booking yang bentrok otomatis.', null],
                ['users', 'Booking &amp; driver', 'Kelola pesanan penyewa dan penugasan driver. Tiap booking punya kode unik untuk dilacak.', null],
                ['gauge', 'Laporan otomatis', 'Pendapatan, utilisasi armada, biaya per km — terhitung sendiri. Export ke PDF/Excel.', null],
                ['sparkle', 'Asisten AI', 'Tanya "pendapatan bulan ini berapa?" — dijawab langsung dari data bisnis Anda.', null],
                ['pin', 'Pelacakan GPS', 'Pantau posisi tiap unit langsung di peta. Bisa digunakan ketika alat GPS telah dipasang di unit.', 'Segera hadir'],
            ] as $f)
                <div class="feature reveal">
                    <div class="ico"><x-icon name="{{ $f[0] }}" /></div>
                    <h3>{!! $f[1] !!} @if ($f[3])<span class="pill pill-pending" style="font-size:.66rem;vertical-align:middle">{{ $f[3] }}</span>@endif</h3>
                    <p>{{ $f[2] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ SOROTAN BBM ============ --}}
<section class="section">
    <div class="container">
        <div class="spotlight">
            <div class="browser reveal">
                <div class="browser-bar"><span></span><span></span><span></span></div>
                <img src="{{ asset('img/product-bbm.jpg') }}" width="1600" height="955"
                     alt="Halaman BBM Lajur menandai pengisian yang tak wajar" loading="lazy">
            </div>
            <div class="spotlight-text reveal">
                <span class="eyebrow">Fitur unggulan</span>
                <h2>BBM yang bocor, langsung ketahuan</h2>
                <p>Sopir isi 60 liter ke tangki yang cuma muat 45? Konsumsi tiba-tiba boros dua kali
                    lipat? Lajur hitung sendiri dari tiap catatan pengisian dan menandainya merah —
                    Anda tak perlu memeriksa satu per satu.</p>
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">Coba Gratis 14 Hari <x-icon name="arrow-right" /></a>
            </div>
        </div>
    </div>
</section>

{{-- ============ CARA KERJA ============ --}}
<section class="section" id="cara-kerja">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <span class="eyebrow" style="justify-content:center">Cara kerja</span>
            <h2 class="section-title">Mulai dalam 3 langkah</h2>
        </div>
        <div class="steps">
            @foreach ([
                ['Daftar &amp; coba gratis', 'Buat akun dalam semenit, tanpa kartu kredit. Langsung dapat akses penuh 14 hari.'],
                ['Isi armada &amp; data', 'Masukkan mobil, kapasitas tangki, dan tarif. Kami bantu di setiap langkah.'],
                ['Kelola &amp; pantau', 'Terima booking, catat BBM, lihat laporan. Semua rapi di satu tempat.'],
            ] as $i => $step)
                <div class="step reveal">
                    <div class="num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
                    <h3>{!! $step[0] !!}</h3>
                    <p>{{ $step[1] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ HARGA RINGKAS ============ --}}
<section class="section" id="harga" style="background:var(--ivory-200)">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <span class="eyebrow" style="justify-content:center">Harga</span>
            <h2 class="section-title">Harga jujur, tanpa kejutan</h2>
            <p class="section-sub">Semua paket bisa dicoba gratis 14 hari dulu. Bayar bulanan, berhenti kapan saja.</p>
        </div>
        <div class="price-teaser">
            @foreach ($plans as $plan)
                <a href="{{ route('signup.pricing') }}" class="pt-card @if ($plan->key === 'business') pt-featured @endif">
                    @if ($plan->key === 'business')<span class="pt-flag">Paling populer</span>@endif
                    <span class="pt-name">{{ $plan->name }}</span>
                    <span class="pt-price">Rp {{ number_format($plan->price, 0, ',', '.') }}<small>/bln</small></span>
                    <span class="pt-note">{{ $plan->features->count() }} fitur premium</span>
                </a>
            @endforeach
        </div>
        <div style="text-align:center;margin-top:28px">
            <a href="{{ route('signup.pricing') }}" class="btn btn-ghost">Lihat detail paket <x-icon name="arrow-right" /></a>
        </div>
    </div>
</section>

{{-- ============ AJAKAN ============ --}}
<section class="section">
    <div class="container">
        <div class="cta-band reveal">
            <div>
                <h2>Berhenti menebak. Mulai kelola.</h2>
                <p>Coba semua fitur Lajur gratis 14 hari. Tanpa kartu kredit, tanpa risiko.</p>
            </div>
            <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">Coba Gratis 14 Hari</a>
        </div>
        <div class="trust-strip">
            <span class="item"><x-icon name="shield" /> Data bisnis aman &amp; terenkripsi</span>
            <span class="item"><x-icon name="whatsapp" /> Dukungan cepat via WhatsApp</span>
            <span class="item"><x-icon name="settings" /> Upgrade atau turun paket kapan saja</span>
        </div>
    </div>
</section>

@push('head')
<style>
    /* Hero page induk tak pakai foto latar seperti etalase → 90vh jadi terlalu
       lapang. Rapatkan agar konten tak tenggelam di ruang kosong. */
    #home.hero { min-height: auto; padding-block: clamp(76px, 12vh, 132px); }

    /* Preview produk mengambang, naik menimpa bawah hero (efek SaaS klasik). */
    .product-band { margin-top: clamp(-56px, -6vw, -40px); position: relative; z-index: 2; padding-bottom: 8px; }
    .browser { border-radius: 14px; overflow: hidden; background: var(--white);
        box-shadow: 0 44px 90px -34px rgba(15,27,51,.55), 0 0 0 1px rgba(15,27,51,.07); }
    .product-band .browser { max-width: 980px; margin: 0 auto; }
    .browser-bar { display: flex; gap: 7px; padding: 12px 16px; background: #E9EDF3; border-bottom: 1px solid #DFE4EC; }
    .browser-bar span { width: 11px; height: 11px; border-radius: 50%; background: #C3CCDA; }
    .browser img { display: block; width: 100%; height: auto; }

    /* Sorotan fitur: gambar + teks berdampingan. */
    .spotlight { display: grid; grid-template-columns: 1.08fr .92fr; gap: 46px; align-items: center; }
    @media (max-width: 820px) { .spotlight { grid-template-columns: 1fr; gap: 30px; } }
    .spotlight-text h2 { font-size: clamp(1.5rem, 3vw, 2.15rem); line-height: 1.15; margin: 10px 0 14px; }
    .spotlight-text p { color: var(--graphite); font-size: 1.05rem; line-height: 1.72; margin-bottom: 24px; }

    /* Teaser harga di page induk — ringkas, mengalir ke /daftar. */
    .price-teaser{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;max-width:820px;margin:0 auto}
    @media(max-width:640px){.price-teaser{grid-template-columns:1fr}}
    .pt-card{position:relative;display:flex;flex-direction:column;gap:6px;background:var(--white);
        border:1px solid var(--ivory-200);border-radius:var(--radius-lg);padding:24px 22px;text-decoration:none;
        color:var(--ink);transition:transform .18s,border-color .18s,box-shadow .18s}
    .pt-card:hover{transform:translateY(-4px);border-color:var(--amber);box-shadow:var(--shadow-lg)}
    .pt-featured{border-color:var(--amber);box-shadow:0 0 0 1px var(--amber),0 14px 34px -14px var(--amber-glow)}
    /* Sheen dianimasikan lewat background-position (bukan transform) agar tetap terpotong
       radius card tanpa overflow:hidden — overflow akan memenggal flag "Paling populer". */
    .pt-featured::after{content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;
        background:linear-gradient(115deg,transparent 35%,rgba(255,255,255,.55) 48%,rgba(231,178,76,.28) 52%,transparent 65%);
        background-size:260% 100%;background-position:190% 0;animation:pt-shine 3.6s ease-in-out infinite}
    @keyframes pt-shine{0%{background-position:190% 0}55%,100%{background-position:-90% 0}}

    /* CTA band: kilau emas menyapu pelan di atas petrol. */
    .cta-band{position:relative;overflow:hidden;border:1px solid rgba(231,178,76,.3)}
    .cta-band::before{content:"";position:absolute;inset:0;pointer-events:none;
        background:linear-gradient(115deg,transparent 30%,rgba(231,178,76,.16) 45%,rgba(255,255,255,.14) 50%,rgba(231,178,76,.16) 55%,transparent 70%);
        background-size:260% 100%;background-position:190% 0;animation:pt-shine 5.2s ease-in-out infinite}
    .cta-band>*{position:relative}
    @media (prefers-reduced-motion:reduce){.pt-featured::after,.cta-band::before{animation:none}}
    .pt-flag{position:absolute;top:-11px;left:22px;background:var(--amber);color:#231703;font-family:var(--font-mono);
        font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:4px 11px;border-radius:var(--radius-pill)}
    .pt-name{font-family:var(--font-display);font-weight:700;font-size:1.1rem}
    .pt-price{font-family:var(--font-mono);font-weight:700;font-size:1.5rem;color:var(--ink)}
    .pt-price small{font-size:.8rem;color:var(--graphite);font-weight:500}
    .pt-note{font-size:.86rem;color:var(--graphite)}
</style>
@endpush
@endsection
