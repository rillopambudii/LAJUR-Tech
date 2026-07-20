@extends('layouts.platform')

@section('title', 'Lajur — Software Manajemen Rental Mobil')

@section('content')

{{-- ============ HERO ============ --}}
<section class="hero" id="home">
    <div class="container">
        <div class="hero-content">
            <span class="eyebrow hero-eyebrow">{{ $copy->heroEyebrow() }}</span>
            <h1 class="hero-title">
                <span class="hero-title__lead">{{ $copy->heroTitleLead() }}</span>
                <span class="hero-title__reveal">{{ $copy->heroTitleReveal() }}</span>
            </h1>
            <p>{{ $copy->heroSubtitle() }}</p>
            <div class="hero-actions">
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }} <x-icon name="arrow-right" /></a>
                <a href="{{ url('/demo') }}" class="btn btn-light">Lihat Demo</a>
            </div>
        </div>
    </div>
</section>

{{-- ============ PREVIEW PRODUK ============ --}}
<div class="product-band">
    <div class="container">
        {{-- Tanpa "reveal": gambar ini sudah tampil di layar pertama saat load
             (tak perlu di-scroll), jadi animasi fade-in-saat-scroll cuma bikin
             kedip sekilas tiap refresh alih-alih reveal yang mulus. --}}
        <div class="browser">
            <div class="browser-bar"><span></span><span></span><span></span></div>
            <img src="{{ asset('img/product-dashboard.jpg') }}" width="1600" height="955"
                 alt="Dashboard Lajur, kalender ketersediaan armada dalam satu layar" loading="lazy">
        </div>
    </div>
</div>

{{-- ============ TRUST STRIP ============ --}}
<div class="container">
    <p class="trust-lead reveal">{{ $copy->trustLead() }}</p>
    <div class="trust-strip reveal" style="margin-top:14px">
        @foreach ([['gauge', 0], ['dashboard', 1], ['phone', 2], ['whatsapp', 3]] as [$icon, $i])
            <span class="item"><x-icon name="{{ $icon }}" /> {{ $copy->trustItems()[$i] }}</span>
        @endforeach
    </div>
</div>

{{-- ============ PAIN POINT ============ --}}
<section class="section" id="masalah">
    <div class="container">
        <div class="prob-grid">
            <div class="prob-head reveal">
                <span class="eyebrow">{{ $copy->painEyebrow() }}</span>
                <h2 class="section-title">{{ $copy->painTitle() }}</h2>
                <p class="section-sub">{{ $copy->painSubtitle() }}</p>
            </div>
            <div class="prob-list">
                @php $painIcons = ['pin', 'gauge', 'fuel', 'chat', 'list']; @endphp
                @foreach ($copy->painItems() as $i => $item)
                    <div class="prob reveal">
                        <span class="prob-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="prob-ico"><x-icon name="{{ $painIcons[$i] }}" /></div>
                        <div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="prob-close reveal">
            <x-icon name="chevron-down" class="prob-arrow" />
            <p>{{ $copy->painClosing() }}</p>
        </div>
    </div>
</section>

{{-- ============ SEBELUM / SESUDAH ============ --}}
<section class="section" style="background:var(--ivory-200);padding-block:64px">
    <div class="container">
        <div class="ba-grid reveal">
            <div class="ba-before">
                <span class="ba-lbl">Sebelum</span>
                <ul>
                    @php $beforeIcons = ['list', 'chat', 'phone', 'edit']; @endphp
                    @foreach ($copy->beforeItems() as $i => $label)
                        <li><x-icon name="{{ $beforeIcons[$i] }}" /> {{ $label }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="ba-arrow" aria-hidden="true"><x-icon name="arrow-right" /></div>
            <div class="ba-after">
                <span class="ba-lbl">Sesudah</span>
                <div class="ba-brand"><span class="mark"><x-icon name="route" /></span> {{ $copy->afterBrand() }}</div>
                <p>{{ $copy->afterText() }}</p>
            </div>
        </div>
    </div>
</section>

{{-- ============ FITUR (BERKELOMPOK) ============ --}}
<section class="section" id="fitur">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->featuresTitle() }}</h2>
            <p class="section-sub">{{ $copy->featuresSubtitle() }}</p>
        </div>
        <div class="bento">
            @php
                $groupMeta = [
                    ['ico' => 'calendar', 'class' => 'cell-wide', 'itemIcons' => ['check', 'check', 'check', 'check']],
                    ['ico' => 'fuel', 'class' => 'cell-wide cell-dark', 'itemIcons' => ['check', 'check', 'check', 'clock']],
                    ['ico' => 'sparkle', 'class' => 'cell-wide cell-tint', 'itemIcons' => ['check', 'check', 'check']],
                    ['ico' => 'users', 'class' => 'cell-wide', 'itemIcons' => ['check', 'check', 'check']],
                ];
            @endphp
            @foreach ($copy->featureGroups() as $gi => $group)
                <div class="cell {{ $groupMeta[$gi]['class'] }} reveal">
                    <div class="ico"><x-icon name="{{ $groupMeta[$gi]['ico'] }}" /></div>
                    <h3>{{ $group['title'] }}</h3>
                    <ul class="fg-list">
                        @foreach ($group['items'] as $ii => $item)
                            <li>
                                <x-icon name="{{ $groupMeta[$gi]['itemIcons'][$ii] }}" /> {{ $item }}
                                {{-- Item terakhir kelompok Monitoring (GPS) selalu berlabel "segera hadir" — fitur belum berjalan, bukan bagian teks yg diedit. --}}
                                @if ($gi === 1 && $ii === 3)
                                    <span class="pill pill-pending" style="font-size:.64rem;vertical-align:middle">Segera hadir</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
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
                <span class="eyebrow">{{ $copy->spotlightEyebrow() }}</span>
                <h2>{{ $copy->spotlightFuelTitle() }}</h2>
                <p>{{ $copy->spotlightFuelText() }}</p>
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }} <x-icon name="arrow-right" /></a>
            </div>
        </div>
    </div>
</section>

{{-- ============ SOROTAN NAVIGASI DRIVER ============ --}}
<section class="section">
    <div class="container">
        <div class="spotlight spotlight-rev">
            <div class="spotlight-text reveal">
                <span class="eyebrow">{{ $copy->spotlightEyebrow() }}</span>
                <h2>{{ $copy->spotlightDriverTitle() }}</h2>
                <p>{{ $copy->spotlightDriverText() }}</p>
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }} <x-icon name="arrow-right" /></a>
            </div>
            <div class="phone-frame-wrap reveal">
                <div class="phone-frame-col">
                    <div class="phone-frame">
                        <img src="{{ asset('img/product-driver-full.jpg') }}" width="480" height="882"
                             alt="Dashboard driver Lajur: jadwal hari ini dan detail tugas mendatang lengkap dengan tombol Maps" loading="lazy">
                    </div>
                    <span class="phone-frame-cap">Jadwal &amp; tugas mendatang</span>
                </div>
                {{-- Ilustrasi rute — BUKAN screenshot Google Maps asli (tak boleh
                     ditangkap/ditiru utk materi marketing); gaya visual sama dgn
                     mockup peta GPS di section #gps agar konsisten satu halaman. --}}
                <div class="phone-frame-col">
                    <div class="phone-frame maps-demo">
                        <div class="gps-bar">
                            <span class="car-lbl">Rute ke tujuan</span>
                        </div>
                        <div class="maps-demo-map">
                            <svg viewBox="0 0 480 760" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                                <rect width="480" height="760" fill="#E9EDF2"/>
                                <g fill="#DCE2EA">
                                    <rect x="24" y="32" width="160" height="130" rx="6"/><rect x="208" y="32" width="120" height="130" rx="6"/><rect x="352" y="32" width="104" height="130" rx="6"/>
                                    <rect x="24" y="196" width="160" height="160" rx="6"/><rect x="208" y="196" width="120" height="160" rx="6"/><rect x="352" y="196" width="104" height="160" rx="6"/>
                                    <rect x="24" y="390" width="160" height="145" rx="6"/><rect x="208" y="390" width="120" height="145" rx="6"/><rect x="352" y="390" width="104" height="145" rx="6"/>
                                    <rect x="24" y="569" width="160" height="160" rx="6"/><rect x="208" y="569" width="120" height="160" rx="6"/><rect x="352" y="569" width="104" height="160" rx="6"/>
                                </g>
                                <path d="M0 540 C 110 505, 250 590, 480 520" stroke="#BFD4E6" stroke-width="22" fill="none" opacity=".85"/>
                                <path d="M72 700 L72 460 L272 460 L272 250 L420 250 L420 90"
                                      stroke="#E7B24C" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="72" cy="700" r="9" fill="#5A6478"/>
                                <circle cx="420" cy="90" r="11" fill="#1F8A63"/>
                            </svg>
                        </div>
                        <div class="gps-foot">
                            <span>Bandara APT Pranoto</span>
                            <span><b>6</b> km · <b>12</b> menit</span>
                        </div>
                    </div>
                    <span class="phone-frame-cap">Ilustrasi: rute otomatis terbuka</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============ HIGHLIGHT: KELUARGA IKUT MEMANTAU ============ --}}
<section class="section" style="background:var(--ivory-200)">
    <div class="container">
        <div class="section-head reveal" style="max-width:680px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->familyTitle() }}</h2>
            <p class="section-sub">{{ $copy->familySubtitle() }}</p>
        </div>
        <div class="flow reveal">
            @php $familyIcons = ['car', 'chat', 'search', 'pin']; @endphp
            @foreach ($copy->familySteps() as $i => $step)
                <div class="flow-step"><div class="fico"><x-icon name="{{ $familyIcons[$i] }}" /></div><h3>{{ $step['title'] }}</h3><p>{{ $step['text'] }}</p></div>
                @if (! $loop->last)<span class="flow-arr" aria-hidden="true"><x-icon name="arrow-right" /></span>@endif
            @endforeach
        </div>
    </div>
</section>

{{-- ============ SOROTAN ETALASE TENANT ============ --}}
<section class="section" style="background:var(--ivory-200)">
    <div class="container">
        <div class="spotlight">
            <div class="browser reveal">
                <div class="browser-bar"><span></span><span></span><span></span></div>
                <img src="{{ asset('img/product-storefront.jpg') }}" width="1600" height="956"
                     alt="Etalase booking online bermerek milik tenant Lajur, lengkap dengan warna dan logo sendiri" loading="lazy">
            </div>
            <div class="spotlight-text reveal">
                <span class="eyebrow">{{ $copy->spotlightEyebrow() }}</span>
                <h2>{{ $copy->spotlightStorefrontTitle() }}</h2>
                <p>{{ $copy->spotlightStorefrontText() }}</p>
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }} <x-icon name="arrow-right" /></a>
            </div>
        </div>
    </div>
</section>

{{-- ============ PREVIEW GPS (ilustrasi berlabel — fitur dalam pengembangan) ============ --}}
<section class="section gps-band" id="gps">
    <div class="container gps-grid">
        <div class="gps-text reveal">
            <span class="gps-badge"><i></i> {{ $copy->gpsBadge() }}</span>
            <h2>{{ $copy->gpsTitle() }}</h2>
            <p>{{ $copy->gpsText() }}</p>
            <p class="gps-note">{{ $copy->gpsNote() }}</p>
        </div>
        <div class="gps-phone-wrap reveal">
            <div class="gps-phone">
                <div class="gps-bar">
                    <span class="car-lbl">Innova Zenix</span>
                    <span class="live"><i></i> LIVE</span>
                </div>
                <div class="gps-eta">
                    <x-icon name="clock" />
                    <span>Estimasi tiba <b id="gps-eta-clock">15:42</b> · <b id="gps-eta-min">6</b> menit lagi</span>
                </div>
                <div class="gps-map">
                    <svg viewBox="0 0 600 900" id="gps-svg" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                        <rect width="600" height="900" fill="#E9EDF2"/>
                        <g fill="#DCE2EA">
                            <rect x="30" y="40" width="200" height="150" rx="6"/><rect x="260" y="40" width="150" height="150" rx="6"/><rect x="440" y="40" width="130" height="150" rx="6"/>
                            <rect x="30" y="230" width="200" height="190" rx="6"/><rect x="260" y="230" width="150" height="190" rx="6"/><rect x="440" y="230" width="130" height="190" rx="6"/>
                            <rect x="30" y="460" width="200" height="170" rx="6"/><rect x="260" y="460" width="150" height="170" rx="6"/><rect x="440" y="460" width="130" height="170" rx="6"/>
                            <rect x="30" y="670" width="200" height="190" rx="6"/><rect x="260" y="670" width="150" height="190" rx="6"/><rect x="440" y="670" width="130" height="190" rx="6"/>
                        </g>
                        <path d="M0 640 C 140 600, 300 700, 600 620" stroke="#BFD4E6" stroke-width="26" fill="none" opacity=".85"/>
                        <text x="240" y="215" font-size="15" fill="#8A93A3">Jl. Merdeka Raya</text>
                        <text x="240" y="445" font-size="15" fill="#8A93A3">Jl. Sudirman</text>
                        <text x="240" y="655" font-size="15" fill="#8A93A3">Jl. Gatot Subroto</text>
                        <path id="gps-route" d="M 90 830 L 90 560 L 330 560 L 330 300 L 520 300 L 520 110"
                              stroke="#E7B24C" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity=".45"/>
                        <path id="gps-trail" d="" stroke="#E7B24C" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="90" cy="830" r="9" fill="#5A6478"/>
                        <circle cx="520" cy="110" r="11" fill="#1F8A63"/>
                        <g id="gps-car">
                            <circle r="22" fill="#E7B24C" opacity=".28"/>
                            <circle r="13" fill="#E7B24C" stroke="#0F1B33" stroke-width="4"/>
                        </g>
                    </svg>
                </div>
                <div class="gps-foot">
                    <span id="gps-street">Jl. Gatot Subroto</span>
                    <span><b id="gps-speed">48</b> km/j</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============ KENAPA MEMILIH LAJUR ============ --}}
<section class="section">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->whyTitle() }}</h2>
        </div>
        <div class="why-grid">
            @php $whyIcons = ['clock', 'dashboard', 'phone', 'shield', 'gauge', 'whatsapp']; @endphp
            @foreach ($copy->whyItems() as $i => $item)
                <div class="why reveal">
                    <div class="wico"><x-icon name="{{ $whyIcons[$i] }}" /></div>
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ WORKFLOW ============ --}}
<section class="section" id="cara-kerja" style="background:var(--ivory-200)">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->workflowTitle() }}</h2>
        </div>
        <div class="steps">
            @foreach ($copy->workflowSteps() as $i => $step)
                <div class="step reveal">
                    <div class="num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ PLATFORM ECOSYSTEM + FUTURE READY ============ --}}
<section class="section" id="platform">
    <div class="container">
        <div class="section-head reveal" style="max-width:720px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->ecosystemTitleLine1() }}<br>{{ $copy->ecosystemTitleLine2() }}</h2>
            <p class="section-sub">{{ $copy->ecosystemSubtitle() }}</p>
        </div>
        <div class="eco-grid reveal">
            @php
                $ecoMeta = [
                    ['car', false], ['users', false], ['calendar', false], ['home', false],
                    ['fuel', false], ['gauge', false], ['sparkle', false],
                    ['pin', true], ['chip', true],
                ];
            @endphp
            @foreach ($copy->ecosystemItems() as $i => $label)
                <div class="eco-tile {{ $ecoMeta[$i][1] ? 'soon' : '' }}">
                    <div class="eco-ico"><x-icon name="{{ $ecoMeta[$i][0] }}" /></div>
                    <span>{{ $label }}</span>
                    @if ($ecoMeta[$i][1])<em>Segera hadir</em>@endif
                </div>
            @endforeach
        </div>
        <p class="eco-caption reveal">{{ $copy->ecosystemCaption() }}</p>
    </div>
</section>

{{-- ============ HARGA RINGKAS ============ --}}
<section class="section" id="harga" style="background:var(--ivory-200)">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->pricingTitle() }}</h2>
            <p class="section-sub">{{ $copy->pricingSubtitle() }}</p>
        </div>
        <div class="price-teaser">
            @foreach ($plans as $plan)
                <a href="{{ route('signup.pricing') }}" class="pt-card @if ($plan->key === 'business') pt-featured @endif">
                    @if ($plan->key === 'business')<span class="pt-flag">Paling populer</span>@endif
                    <span class="pt-name">{{ $plan->name }}</span>
                    @if ($plan->hasDiscount())
                        <span class="pt-strike"><s>Rp {{ number_format($plan->price, 0, ',', '.') }}</s>
                            @if ($plan->discount_label)<em>{{ $plan->discount_label }}</em>@endif
                        </span>
                    @endif
                    <span class="pt-price">Rp {{ number_format($plan->effectivePrice(), 0, ',', '.') }}<small>/bln</small></span>
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
                <h2>{{ $copy->ctaTitle() }}</h2>
                <p>{{ $copy->ctaText() }}</p>
            </div>
            <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }}</a>
        </div>
        <div class="trust-strip">
            @foreach ([['shield', 0], ['whatsapp', 1], ['settings', 2]] as [$icon, $i])
                <span class="item"><x-icon name="{{ $icon }}" /> {{ $copy->ctaTrustItems()[$i] }}</span>
            @endforeach
        </div>
    </div>
</section>

@push('head')
<style>
    /* Hero page induk tak pakai foto latar seperti etalase → 90vh jadi terlalu
       lapang. Rapatkan agar konten tak tenggelam di ruang kosong. */
    #home.hero { min-height: auto; padding-block: clamp(76px, 12vh, 132px); }

    /* Aurora emas: dua cahaya lembut melayang pelan di belakang headline. */
    #home.hero::before { content: ""; position: absolute; inset: -20%; z-index: 0; pointer-events: none;
        background:
            radial-gradient(560px 340px at 32% 30%, rgba(231,178,76,.16), transparent 68%),
            radial-gradient(640px 420px at 72% 68%, rgba(44,110,143,.22), transparent 70%);
        animation: auroraDrift 16s ease-in-out infinite alternate; }
    @keyframes auroraDrift {
        from { transform: translate3d(-2%, -1%, 0) scale(1); }
        to   { transform: translate3d(2%, 3%, 0) scale(1.06); }
    }
    /* Garis emas menyapu di bawah frasa aksen setelah frasa mendarat. */
    .hero-title__reveal { position: relative; padding-bottom: .08em; }
    .hero-title__reveal::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 3px;
        border-radius: 3px; background: linear-gradient(90deg, var(--amber), rgba(231,178,76,.25));
        transform: scaleX(0); transform-origin: left; animation: underlineSweep .6s cubic-bezier(.2,.7,.2,1) 1.15s forwards; }
    @keyframes underlineSweep { to { transform: scaleX(1); } }

    /* Trust strip di bawah preview produk */
    .trust-lead { text-align: center; margin-top: 30px; color: var(--graphite); font-size: .95rem; }

    /* ---------- Pain point: header kiri sticky + daftar keluhan kanan ---------- */
    .prob-grid { display: grid; grid-template-columns: .9fr 1.1fr; gap: 54px; align-items: start; }
    .prob-head { position: sticky; top: calc(var(--header-h) + 28px); }
    .prob-list { display: flex; flex-direction: column; gap: 16px; }
    .prob { position: relative; display: flex; gap: 18px; align-items: flex-start;
        background: var(--white); border: 1px solid var(--ivory-200); border-left: 3px solid var(--danger);
        border-radius: var(--radius-lg); padding: 22px 26px; box-shadow: var(--shadow-sm);
        transition: translate .22s ease, box-shadow .25s ease; }
    .prob:hover { translate: 6px 0; box-shadow: var(--shadow); }
    .prob-num { position: absolute; top: 10px; right: 18px; font-family: var(--font-mono); font-weight: 700;
        font-size: 2.6rem; line-height: 1; color: rgba(212,72,61,.12); }
    .prob-ico { flex: none; width: 46px; height: 46px; border-radius: 13px; display: grid; place-items: center;
        background: rgba(212,72,61,.1); color: var(--danger); }
    .prob-ico svg { width: 24px; height: 24px; }
    .prob h3 { font-size: 1.08rem; margin-bottom: 6px; }
    .prob p { color: var(--graphite); font-size: .95rem; }
    @media (max-width: 820px) { .prob-grid { grid-template-columns: 1fr; gap: 30px; } .prob-head { position: static; } }
    .prob-close { text-align: center; margin-top: 40px; }
    .prob-close .prob-arrow { width: 30px; height: 30px; color: var(--amber-600); margin: 0 auto 8px; animation: probBounce 1.6s ease-in-out infinite; }
    @keyframes probBounce { 50% { transform: translateY(7px); } }
    .prob-close p { font-family: var(--font-display); font-weight: 700; font-size: 1.35rem; color: var(--ink); }

    /* ---------- Sebelum / Sesudah ---------- */
    .ba-grid { display: grid; grid-template-columns: 1fr auto 1fr; gap: 30px; align-items: stretch; max-width: 900px; margin: 0 auto; }
    .ba-lbl { display: block; font-family: var(--font-mono); font-size: .74rem; font-weight: 700;
        letter-spacing: .14em; text-transform: uppercase; margin-bottom: 14px; color: var(--graphite); }
    .ba-before { background: var(--white); border: 1px dashed var(--graphite-300); border-radius: var(--radius-lg); padding: 26px 28px; }
    .ba-before ul { list-style: none; display: flex; flex-direction: column; gap: 12px; }
    .ba-before li { display: flex; align-items: center; gap: 10px; color: var(--graphite); }
    .ba-before li svg { width: 18px; height: 18px; color: var(--graphite-300); flex: none; }
    .ba-arrow { display: grid; place-items: center; color: var(--amber-600); }
    .ba-arrow svg { width: 34px; height: 34px; }
    .ba-after { background: linear-gradient(150deg, var(--petrol) 0%, var(--petrol-700) 100%);
        border-radius: var(--radius-lg); padding: 26px 28px; color: var(--ivory); box-shadow: var(--shadow); }
    .ba-after .ba-lbl { color: var(--amber); }
    .ba-brand { display: flex; align-items: center; gap: 11px; font-family: var(--font-display); font-weight: 800; font-size: 1.25rem; margin-bottom: 10px; }
    .ba-brand .mark { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; background: var(--amber); color: var(--petrol); }
    .ba-brand .mark svg { width: 22px; height: 22px; }
    .ba-after p { color: rgba(247,248,251,.8); font-size: .96rem; }
    @media (max-width: 760px) { .ba-grid { grid-template-columns: 1fr; } .ba-arrow svg { transform: rotate(90deg); } }

    /* ---------- Fitur: bento grid + spotlight border mengikuti kursor ---------- */
    .bento { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
    .bento .cell { position: relative; padding: 26px; border-radius: var(--radius-lg);
        background: var(--white); border: 1px solid var(--ivory-200); box-shadow: var(--shadow-sm); overflow: hidden; }
    .bento .cell-wide { grid-column: span 2; }
    @media (max-width: 980px) { .bento { grid-template-columns: repeat(2, 1fr); } .bento .cell-wide { grid-column: span 2; } }
    @media (max-width: 640px) { .bento { grid-template-columns: 1fr; } .bento .cell-wide { grid-column: auto; } }
    .bento .ico { width: 50px; height: 50px; border-radius: 14px; display: grid; place-items: center;
        background: var(--amber-glow); color: var(--amber-600); margin-bottom: 16px; }
    .bento .ico svg { width: 26px; height: 26px; }
    .bento h3 { font-size: 1.12rem; margin-bottom: 12px; }
    .bento p { color: var(--graphite); font-size: .95rem; }
    .fg-list { list-style: none; display: flex; flex-direction: column; gap: 9px; }
    .fg-list li { display: flex; align-items: flex-start; gap: 9px; color: var(--graphite); font-size: .95rem; }
    .fg-list li svg { width: 16px; height: 16px; flex: none; margin-top: 4px; color: var(--ok); }
    .bento .cell-dark .fg-list li { color: rgba(247,248,251,.82); }
    .bento .cell-dark .fg-list li svg { color: var(--amber); }
    /* dua sel diberi karakter sendiri agar grid tidak putih semua */
    .bento .cell-dark { background: linear-gradient(150deg, var(--petrol) 0%, var(--petrol-700) 100%); border-color: var(--petrol-600); }
    .bento .cell-dark h3 { color: var(--ivory); }
    .bento .cell-dark p { color: rgba(247,248,251,.78); }
    .bento .cell-dark .ico { background: rgba(231,178,76,.16); color: var(--amber); }
    .bento .cell-tint { background: linear-gradient(150deg, #FBF3E1 0%, var(--white) 62%); }
    /* spotlight: lingkaran cahaya lembut mengikuti posisi kursor (di-set via JS --mx/--my) */
    .bento .cell::before { content: ""; position: absolute; inset: 0; pointer-events: none; opacity: 0;
        background: radial-gradient(240px circle at var(--mx, 50%) var(--my, 50%), rgba(231,178,76,.14), transparent 65%);
        transition: opacity .25s ease; }
    .bento .cell:hover::before { opacity: 1; }
    .bento .cell:hover { border-color: rgba(231,178,76,.5); }

    /* ---------- Alur lacak keluarga ---------- */
    .flow { display: flex; justify-content: center; align-items: stretch; gap: 14px; flex-wrap: wrap; }
    .flow-step { flex: 1 1 200px; max-width: 240px; background: var(--white); border: 1px solid var(--ivory-200);
        border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 22px; text-align: center; }
    .flow-step .fico { width: 46px; height: 46px; border-radius: 13px; display: grid; place-items: center;
        background: var(--amber-glow); color: var(--amber-600); margin: 0 auto 12px; }
    .flow-step .fico svg { width: 24px; height: 24px; }
    .flow-step h3 { font-size: 1rem; margin-bottom: 6px; }
    .flow-step p { color: var(--graphite); font-size: .88rem; }
    .flow-arr { align-self: center; color: var(--amber-600); flex: none; }
    .flow-arr svg { width: 22px; height: 22px; }
    @media (max-width: 900px) { .flow-arr { display: none; } }

    /* ---------- Preview GPS: band gelap + mockup HP dengan peta beranimasi ---------- */
    .gps-band { background: radial-gradient(120% 90% at 50% 0%, var(--petrol-700) 0%, var(--petrol) 62%); color: var(--ivory); }
    .gps-grid { display: grid; grid-template-columns: 1.05fr .95fr; gap: 54px; align-items: center; }
    @media (max-width: 820px) { .gps-grid { grid-template-columns: 1fr; gap: 34px; } }
    .gps-badge { display: inline-flex; align-items: center; gap: 9px; font-family: var(--font-mono); font-weight: 700;
        font-size: .74rem; letter-spacing: .14em; text-transform: uppercase; color: var(--amber);
        border: 1.5px solid rgba(231,178,76,.55); background: rgba(231,178,76,.1);
        padding: 8px 16px; border-radius: var(--radius-pill); margin-bottom: 18px; }
    .gps-badge i { width: 8px; height: 8px; border-radius: 50%; background: var(--amber); animation: gpsPulse 1.8s ease-in-out infinite; }
    @keyframes gpsPulse { 50% { opacity: .35; } }
    .gps-text h2 { font-size: clamp(1.5rem, 3vw, 2.15rem); color: var(--white); margin-bottom: 14px; }
    .gps-text p { color: rgba(247,248,251,.82); font-size: 1.05rem; line-height: 1.7; max-width: 480px; }
    .gps-note { margin-top: 14px; font-size: .88rem !important; color: rgba(247,248,251,.55) !important; }
    .gps-phone-wrap { display: flex; justify-content: center; }
    .gps-phone { width: min(320px, 82vw); border-radius: 30px; background: #0B1424; border: 8px solid #1E2B45;
        overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 40px 80px -20px rgba(0,0,0,.6); }
    .gps-bar, .gps-foot { display: flex; align-items: center; justify-content: space-between; padding: 13px 16px; background: #101C31; }
    .gps-bar .car-lbl { font-family: var(--font-display); font-weight: 700; font-size: .95rem; }
    .gps-bar .live { display: inline-flex; align-items: center; gap: 6px; font-family: var(--font-mono); font-size: .7rem;
        font-weight: 700; color: var(--ok); background: rgba(31,138,99,.16); padding: 4px 10px; border-radius: var(--radius-pill); }
    .gps-bar .live i { width: 7px; height: 7px; border-radius: 50%; background: var(--ok); animation: gpsPulse 1.4s ease-in-out infinite; }
    .gps-eta { display: flex; align-items: center; gap: 8px; padding: 10px 16px;
        background: rgba(231,178,76,.12); border-bottom: 1px solid rgba(231,178,76,.18);
        font-family: var(--font-mono); font-size: .78rem; color: var(--amber); }
    .gps-eta svg { width: 14px; height: 14px; flex: none; }
    .gps-eta b { color: var(--white); }
    /* min-height:0 wajib: tanpa itu rasio SVG mendorong bar bawah keluar dari phone */
    .gps-map { min-height: 0; background: #E9EDF2; aspect-ratio: 3/4; }
    .gps-map svg { display: block; width: 100%; height: 100%; }
    .gps-map text { font-family: var(--font-body); }
    .gps-foot { font-family: var(--font-mono); font-size: .8rem; color: #A9B2C4; }
    .gps-foot b { color: var(--white); }

    /* ---------- Kenapa Lajur ---------- */
    .why-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; max-width: 980px; margin: 0 auto; }
    @media (max-width: 900px) { .why-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 560px) { .why-grid { grid-template-columns: 1fr; } }
    .why { display: flex; flex-direction: column; gap: 4px; background: var(--white); border: 1px solid var(--ivory-200);
        border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 22px 24px;
        transition: translate .22s ease, border-color .22s ease, box-shadow .25s ease; }
    .why:hover { translate: 0 -5px; border-color: rgba(231,178,76,.5); box-shadow: 0 16px 34px -14px var(--amber-glow), var(--shadow); }
    .why .wico { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center;
        background: var(--amber-glow); color: var(--amber-600); margin-bottom: 8px; }
    .why .wico svg { width: 22px; height: 22px; }
    .why h3 { font-size: 1.02rem; }
    .why p { color: var(--graphite); font-size: .9rem; }

    /* ---------- Cara kerja: marka jalan antar langkah bergerak pelan ---------- */
    .steps { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .step:not(:last-child)::after { animation: roadMove 1.1s linear infinite; background-size: 14px 2px; }
    @keyframes roadMove { to { background-position: 14px 0; } }
    .step:hover .num { color: rgba(231,178,76,.34); transition: color .25s ease; }

    /* ---------- Platform ecosystem: rantai vertikal + future ready ---------- */
    /* Grid modul platform: kartu-kartu setara, bukan rantai vertikal — lebih
       cepat dipindai mata & tak menghabiskan tinggi layar. 3x3 tetap (9 kartu
       persis genap) agar dua baris sama panjang, bukan 5+4 yang ragged. */
    .eco-grid { display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 18px; max-width: 760px; margin: 44px auto 0; }
    @media (max-width: 720px) { .eco-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 460px) { .eco-grid { grid-template-columns: 1fr; } }
    .eco-tile { display: flex; flex-direction: column; align-items: center; gap: 10px; text-align: center;
        background: var(--white); border: 1px solid var(--ivory-200); border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm); padding: 24px 14px;
        transition: translate .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .eco-tile:hover { translate: 0 -5px; border-color: rgba(231,178,76,.5); box-shadow: 0 16px 34px -14px var(--amber-glow), var(--shadow); }
    .eco-ico { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center;
        background: var(--amber-glow); color: var(--amber-600); }
    .eco-ico svg { width: 21px; height: 21px; }
    .eco-tile span { font-weight: 700; font-size: .92rem; color: var(--ink); }
    .eco-tile em { font-style: normal; font-size: .66rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .04em; color: var(--amber-600); background: rgba(231,178,76,.14);
        padding: 3px 9px; border-radius: var(--radius-pill); }
    .eco-tile.soon { border-style: dashed; }
    .eco-tile.soon .eco-ico { background: rgba(15,27,51,.05); color: var(--graphite-300); }
    .eco-tile.soon span { color: var(--graphite); }
    .eco-caption { text-align: center; color: var(--graphite); font-size: .88rem; max-width: 520px; margin: 22px auto 0; }

    /* Preview produk mengambang, naik menimpa bawah hero (efek SaaS klasik). */
    .product-band { margin-top: clamp(-56px, -6vw, -40px); position: relative; z-index: 2; padding-bottom: 8px; perspective: 1200px; }
    .browser { border-radius: 14px; overflow: hidden; background: var(--white);
        box-shadow: 0 44px 90px -34px rgba(15,27,51,.55), 0 0 0 1px rgba(15,27,51,.07); }
    .product-band .browser { max-width: 980px; margin: 0 auto;
        transform: rotateX(var(--tx, 0deg)) rotateY(var(--ty, 0deg));
        transition: transform .18s ease-out; will-change: transform; }
    .product-band .browser::after { content: ""; position: absolute; inset: 0; pointer-events: none;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.6); border-radius: inherit; }
    .browser-bar { display: flex; gap: 7px; padding: 12px 16px; background: #E9EDF3; border-bottom: 1px solid #DFE4EC; }
    .browser-bar span { width: 11px; height: 11px; border-radius: 50%; background: #C3CCDA; }
    .browser img { display: block; width: 100%; height: auto; }

    /* Sorotan fitur: gambar + teks berdampingan. */
    .spotlight { display: grid; grid-template-columns: 1.08fr .92fr; gap: 46px; align-items: center; }
    .spotlight-rev { grid-template-columns: .92fr 1.08fr; }
    .spotlight-rev .spotlight-text { order: -1; }
    @media (max-width: 820px) {
        .spotlight, .spotlight-rev { grid-template-columns: 1fr; gap: 30px; }
        .spotlight-rev .spotlight-text { order: 0; }
    }

    .phone-frame-wrap { display: flex; justify-content: center; align-items: flex-start; gap: 22px; flex-wrap: wrap; }
    .phone-frame-col { display: flex; flex-direction: column; align-items: center; gap: 12px; }
    /* aspect-ratio (bukan tinggi gambar) yang menjaga kedua kotak selalu sama
       bentuk — tahan lama walau isi salah satu diganti gambar lain nanti. */
    .phone-frame { width: min(200px, 40vw); aspect-ratio: 480 / 882; border-radius: 26px;
        background: #0B1424; border: 7px solid #1E2B45; overflow: hidden;
        box-shadow: 0 40px 80px -20px rgba(0,0,0,.6); display: flex; flex-direction: column; }
    .phone-frame img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .phone-frame-cap { font-size: .82rem; font-weight: 600; color: var(--graphite); text-align: center; }
    .maps-demo .gps-bar { justify-content: center; }
    .maps-demo-map { flex: 1; min-height: 0; }
    .maps-demo-map svg { display: block; width: 100%; height: 100%; }
    /* Kotak lebih sempit dari mockup GPS asal (200px vs 280px) — "Bandara APT
       Pranoto" tak muat sebaris dgn jarak/waktu, jadi ditumpuk bukan disejajarkan. */
    .maps-demo .gps-foot { flex-direction: column; align-items: flex-start; gap: 4px; }
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
    @media (prefers-reduced-motion:reduce){
        .pt-featured::after,.cta-band::before,#home.hero::before,.step:not(:last-child)::after,.prob-close .prob-arrow{animation:none}
        .hero-title__reveal::after{animation:none;transform:scaleX(1)}
        .product-band .browser{transform:none}
    }
    .pt-flag{position:absolute;top:-11px;left:22px;background:var(--amber);color:#231703;font-family:var(--font-mono);
        font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:4px 11px;border-radius:var(--radius-pill)}
    .pt-name{font-family:var(--font-display);font-weight:700;font-size:1.1rem}
    .pt-price{font-family:var(--font-mono);font-weight:700;font-size:1.5rem;color:var(--ink)}
    .pt-price small{font-size:.8rem;color:var(--graphite);font-weight:500}
    .pt-note{font-size:.86rem;color:var(--graphite)}
    .pt-strike{font-size:.9rem;color:var(--graphite)}
    .pt-strike em{font-style:normal;background:rgba(231,178,76,.18);color:var(--amber-600);font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:6px;margin-left:4px}
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';
    var fine = window.matchMedia('(pointer: fine)').matches;
    var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Animasi peta GPS: mobil menyusuri rute, loop pelan (hanya saat terlihat).
       Tetap jalan di layar sentuh; hanya reduced-motion yang membuatnya diam. */
    var route = document.getElementById('gps-route');
    if (route) {
        var trail = document.getElementById('gps-trail');
        var carDot = document.getElementById('gps-car');
        var streetEl = document.getElementById('gps-street');
        var speedEl = document.getElementById('gps-speed');
        var etaClockEl = document.getElementById('gps-eta-clock');
        var etaMinEl = document.getElementById('gps-eta-min');
        var total = route.getTotalLength();
        var dAttr = route.getAttribute('d');
        var legs = [[0, 'Jl. Gatot Subroto', 48], [.3, 'Jl. Sudirman', 61], [.55, 'Jl. Merdeka Raya', 55], [.8, 'Jl. Merdeka Raya', 37]];
        var TRIP_MIN = 18; // lama tempuh total ilustrasi, tak dari data nyata
        var pad2 = function (n) { return (n < 10 ? '0' : '') + n; };
        var setT = function (t) {
            var p = route.getPointAtLength(total * t);
            carDot.setAttribute('transform', 'translate(' + p.x + ' ' + p.y + ')');
            trail.setAttribute('stroke-dasharray', (total * t) + ' ' + total);
            trail.setAttribute('d', dAttr);
            var leg = legs[0];
            legs.forEach(function (l) { if (t >= l[0]) leg = l; });
            streetEl.textContent = leg[1];
            speedEl.textContent = leg[2];
            var remaining = Math.max(1, Math.round(TRIP_MIN * (1 - t)));
            etaMinEl.textContent = remaining;
            var arrival = new Date(Date.now() + remaining * 60000);
            etaClockEl.textContent = pad2(arrival.getHours()) + ':' + pad2(arrival.getMinutes());
        };
        setT(.55); // pose diam yang informatif (reduced-motion / sebelum terlihat)
        if (!still) {
            var raf = null, start = null, DUR = 16000, HOLD = 2000;
            var tick = function (ts) {
                if (start === null) start = ts;
                var el = (ts - start) % (DUR + HOLD);
                setT(Math.min(el / DUR, 1));
                raf = requestAnimationFrame(tick);
            };
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting && raf === null) { start = null; raf = requestAnimationFrame(tick); }
                    else if (!e.isIntersecting && raf !== null) { cancelAnimationFrame(raf); raf = null; }
                });
            }, { threshold: .25 });
            io.observe(document.getElementById('gps'));
        }
    }

    if (!fine || still) return; // sentuh/reduced-motion: semua efek kursor mati

    /* Tilt 3D preview produk mengikuti kursor */
    var band = document.querySelector('.product-band');
    var browser = band && band.querySelector('.browser');
    if (browser) {
        band.addEventListener('pointermove', function (e) {
            var r = browser.getBoundingClientRect();
            var x = (e.clientX - r.left) / r.width - .5;
            var y = (e.clientY - r.top) / r.height - .5;
            browser.style.setProperty('--ty', (x * 5) + 'deg');
            browser.style.setProperty('--tx', (y * -5) + 'deg');
        });
        band.addEventListener('pointerleave', function () {
            browser.style.setProperty('--tx', '0deg');
            browser.style.setProperty('--ty', '0deg');
        });
    }

    /* Spotlight bento: simpan posisi kursor sebagai --mx/--my per sel */
    document.querySelectorAll('.bento .cell').forEach(function (cell) {
        cell.addEventListener('pointermove', function (e) {
            var r = cell.getBoundingClientRect();
            cell.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            cell.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
    });

    /* CTA magnetik: tombol utama menarik pelan ke arah kursor */
    document.querySelectorAll('.hero-actions .btn, .cta-band .btn').forEach(function (btn) {
        btn.addEventListener('pointermove', function (e) {
            var r = btn.getBoundingClientRect();
            var x = (e.clientX - r.left) / r.width - .5;
            var y = (e.clientY - r.top) / r.height - .5;
            btn.style.transform = 'translate(' + (x * 6) + 'px,' + (y * 5) + 'px)';
        });
        btn.addEventListener('pointerleave', function () { btn.style.transform = ''; });
    });
})();
</script>
@endpush
@endsection
