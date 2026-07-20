@extends('layouts.platform')

@section('title', 'Harga & Paket - Lajur')

@section('content')
<section class="section" style="padding-top:56px">
    <div class="container">
        <div class="section-head reveal" style="max-width:680px;margin-inline:auto;text-align:center">
            <span class="eyebrow" style="justify-content:center">Untuk Pemilik Usaha Rental</span>
            <h1 class="section-title">Kelola bisnis rental Anda, tanpa ribet</h1>
            <p class="section-sub">Booking, armada, driver, hingga laporan keuangan, semua dalam satu dashboard. Mulai gratis 14 hari, tanpa kartu kredit.</p>
        </div>

        <div class="pricing-grid reveal" style="margin-top:44px">
            <div class="plan-card is-trial">
                <span class="plan-badge">Gratis 14 Hari</span>
                <div>
                    <h2 class="plan-name">Coba Gratis</h2>
                    <p class="plan-desc">Rasakan semua fitur setara paket Business, tanpa risiko.</p>
                </div>
                <div class="plan-price">
                    <span class="amount">Rp 0</span>
                    <span class="per">/ 14 hari</span>
                </div>
                <ul class="about-points">
                    <li><span class="tick"><x-icon name="check" /></span> <span class="pt-txt">BBM anti-kebocoran &amp; Asisten AI</span></li>
                    <li><span class="tick"><x-icon name="check" /></span> <span class="pt-txt">Tanpa kartu kredit</span></li>
                    <li><span class="tick"><x-icon name="check" /></span> <span class="pt-txt">Pelacakan GPS <span style="display:inline-block;background:rgba(231,178,76,.18);color:var(--amber-600);font-size:.68rem;font-weight:700;padding:2px 9px;border-radius:6px;white-space:nowrap;vertical-align:1px">segera hadir</span><small class="pt-sub">bisa digunakan ketika alat GPS telah dipasang</small></span></li>
                </ul>
                <div class="plan-foot">
                    <a href="{{ route('signup.trial.form') }}" class="btn btn-primary btn-block">Coba Gratis 14 Hari</a>
                </div>
            </div>

            @foreach ($plans as $plan)
                @php $featured = $plan->key === 'business'; @endphp
                <div class="plan-card @if ($featured) is-featured @endif">
                    @if ($featured)
                        <span class="plan-badge">Paling Populer</span>
                    @endif
                    <div>
                        <h2 class="plan-name">{{ $plan->name }}</h2>
                        <p class="plan-desc">
                            @if ($plan->key === 'basic')
                                Cocok untuk usaha rental yang baru mulai.
                            @elseif ($plan->key === 'pro')
                                Untuk armada yang berkembang & butuh kontrol lebih.
                            @else
                                Fitur paling lengkap untuk operasional skala besar.
                            @endif
                        </p>
                    </div>
                    <div class="plan-price">
                        @if ($plan->hasDiscount())
                            <span style="display:block;font-size:.95rem;color:var(--graphite)">
                                <s>Rp {{ number_format($plan->price, 0, ',', '.') }}</s>
                                @if ($plan->discount_label)
                                    <span style="background:rgba(231,178,76,.18);color:var(--amber-600);font-size:.7rem;font-weight:700;padding:2px 9px;border-radius:6px;vertical-align:1px">{{ $plan->discount_label }}</span>
                                @endif
                            </span>
                        @endif
                        <span class="amount">Rp {{ number_format($plan->effectivePrice(), 0, ',', '.') }}</span>
                        <span class="per">/ bulan</span>
                    </div>
                    <ul class="about-points">
                        <li><span class="tick"><x-icon name="check" /></span> <span class="pt-txt">Booking &amp; kalender armada</span></li>
                        <li><span class="tick"><x-icon name="check" /></span> <span class="pt-txt">Kelola driver &amp; laporan</span></li>
                        @foreach ($plan->features as $feature)
                            <li>
                                <span class="tick"><x-icon name="check" /></span>
                                <span class="pt-txt">{{ $feature->name }}@if ($feature->key === \App\Models\Feature::GPS_TRACKING) <span style="display:inline-block;background:rgba(231,178,76,.18);color:var(--amber-600);font-size:.68rem;font-weight:700;padding:2px 9px;border-radius:6px;white-space:nowrap;vertical-align:1px">segera hadir</span><small class="pt-sub">bisa digunakan ketika alat GPS telah dipasang</small>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="plan-foot">
                        <a href="{{ route('signup.paid.form', $plan->key) }}" class="btn @if ($featured) btn-primary @else btn-ghost @endif btn-block">
                            Pilih {{ $plan->name }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="trust-strip reveal">
            <span class="item"><x-icon name="shield" /> Data bisnis Anda aman &amp; terenkripsi</span>
            <span class="item"><x-icon name="chat" /> Dukungan cepat via WhatsApp</span>
            <span class="item"><x-icon name="settings" /> Upgrade atau turun paket kapan saja</span>
        </div>
    </div>
</section>
@endsection
