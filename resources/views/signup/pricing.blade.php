@extends('layouts.public')

@section('title', 'Harga & Paket — Lajur')

@section('content')
<section class="section" style="padding-top:56px">
    <div class="container">
        <div class="section-head reveal" style="max-width:680px;margin-inline:auto;text-align:center">
            <span class="eyebrow" style="justify-content:center">Untuk Pemilik Usaha Rental</span>
            <h1 class="section-title">Kelola bisnis rental Anda, tanpa ribet</h1>
            <p class="section-sub">Booking, armada, driver, hingga laporan keuangan — semua dalam satu dashboard. Mulai gratis 14 hari, tanpa kartu kredit.</p>
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
                    <li><span class="tick"><x-icon name="check" /></span> Akses penuh semua fitur</li>
                    <li><span class="tick"><x-icon name="check" /></span> Tanpa kartu kredit</li>
                    <li><span class="tick"><x-icon name="check" /></span> Bisa upgrade kapan saja</li>
                </ul>
                <div class="plan-foot">
                    <a href="{{ route('signup.trial.form') }}" class="btn btn-primary btn-block">Coba Gratis 14 Hari</a>
                </div>
            </div>

            @foreach ($plans as $plan)
                @php $featured = $plan->key === 'pro'; @endphp
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
                        <span class="amount">Rp {{ number_format($plan->price, 0, ',', '.') }}</span>
                        <span class="per">/ bulan</span>
                    </div>
                    <ul class="about-points">
                        <li><span class="tick"><x-icon name="check" /></span> Booking &amp; kalender armada</li>
                        <li><span class="tick"><x-icon name="check" /></span> Kelola driver &amp; laporan</li>
                        @foreach ($plan->features as $feature)
                            <li><span class="tick"><x-icon name="check" /></span> {{ $feature->name }}</li>
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
