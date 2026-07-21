@extends('layouts.driver')

@section('title', 'Jadwal Tugas')

@php
    $statusColors = ['pending' => 'pill-pending', 'confirmed' => 'pill-confirmed', 'completed' => 'pill-completed', 'cancelled' => 'pill-cancelled'];
    $today = \Illuminate\Support\Carbon::today();
    $ongoing = $upcoming->filter(fn ($b) => $b->start_date->lte($today) && $b->end_date->gte($today));
@endphp

@section('content')
    <div class="drv-hero">
        <div>
            <span class="eyebrow">{{ $today->translatedFormat('l, d F Y') }}</span>
            <h1 class="drv-title">Jadwal Tugas Saya</h1>
        </div>
        <div class="drv-stats">
            <div class="drv-stat"><span class="n">{{ $ongoing->count() }}</span><span class="l">Berjalan</span></div>
            <div class="drv-stat"><span class="n">{{ $upcoming->count() - $ongoing->count() }}</span><span class="l">Mendatang</span></div>
        </div>
    </div>

    <h2 class="drv-section-title">Tugas Mendatang</h2>
    @forelse ($upcoming as $b)
        @php $now = $b->start_date->lte($today) && $b->end_date->gte($today); @endphp
        <article class="drv-card {{ $now ? 'now' : '' }}">
            <div class="drv-date" aria-hidden="true">
                <span class="d">{{ $b->start_date->format('d') }}</span>
                <span class="m">{{ $b->start_date->translatedFormat('M') }}</span>
            </div>
            <div class="drv-info">
                <div class="drv-car">{{ $b->car_name }}
                    @if ($now)<span class="drv-now-badge">Sedang berjalan</span>@endif
                </div>
                <div class="drv-row"><x-icon name="calendar" /> {{ $b->start_date->translatedFormat('d M') }} – {{ $b->end_date->translatedFormat('d M Y') }} · {{ $b->start_date->diffInDays($b->end_date) + 1 }} hari</div>
                <div class="drv-row"><x-icon name="users" /> {{ $b->customer_name }}</div>
                @if ($b->destination)
                    <div class="drv-row"><x-icon name="pin" /> {{ $b->destination }}</div>
                @endif
            </div>
            <div class="drv-side">
                <span class="pill {{ $statusColors[$b->status] ?? '' }}">{{ $b->status_label }}</span>
                <div class="drv-actions">
                    @if ($b->customer_phone)
                        <a class="btn btn-primary btn-sm" href="https://wa.me/{{ \App\Tenancy\Branding::waNumber($b->customer_phone) }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> WhatsApp</a>
                        <a class="btn btn-ghost btn-sm" href="tel:{{ $b->customer_phone }}"><x-icon name="phone" /> Telepon</a>
                    @endif
                    @if ($b->mapsUrl())
                        <a class="btn btn-ghost btn-sm" href="{{ $b->mapsUrl() }}" target="_blank" rel="noopener"><x-icon name="pin" /> Maps</a>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="drv-empty">
            <x-icon name="route" />
            <p>Belum ada tugas mendatang.<br>Santai dulu — jadwal baru akan muncul di sini.</p>
        </div>
    @endforelse

    @if ($past->isNotEmpty())
        <h2 class="drv-section-title">Riwayat Terakhir</h2>
        @foreach ($past as $b)
            <article class="drv-card past">
                <div class="drv-date" aria-hidden="true">
                    <span class="d">{{ $b->start_date->format('d') }}</span>
                    <span class="m">{{ $b->start_date->translatedFormat('M') }}</span>
                </div>
                <div class="drv-info">
                    <div class="drv-car">{{ $b->car_name }}</div>
                    <div class="drv-row"><x-icon name="users" /> {{ $b->customer_name }} · {{ $b->start_date->translatedFormat('d M') }} – {{ $b->end_date->translatedFormat('d M Y') }}</div>
                </div>
                <div class="drv-side">
                    <span class="pill {{ $statusColors[$b->status] ?? '' }}">{{ $b->status_label }}</span>
                    @if ($b->driverReview && $b->driverReview->status === 'published')
                        <span class="pill" style="background:var(--amber-glow);color:var(--amber-600)">
                            <x-icon name="star" style="width:13px;height:13px" /> {{ number_format($b->driverReview->rating_overall, 1) }}
                        </span>
                    @endif
                </div>
            </article>
        @endforeach
    @endif
@endsection
