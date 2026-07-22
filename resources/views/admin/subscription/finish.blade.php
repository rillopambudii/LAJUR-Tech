@extends('layouts.admin')

@section('title', 'Status Langganan')
@section('crumb', 'Akun')
@section('heading', 'Status Langganan')

@push('head')
<style>
    .sub-done { max-width: 520px; margin: 8px auto 0; text-align: center; position: relative; overflow: hidden; }
    .sub-done .panel-body { padding: 44px 30px 38px; }

    /* --- Lingkaran centang yang menggambar dirinya --- */
    .sub-check { width: 96px; height: 96px; margin: 0 auto 22px; display: block; }
    .sub-check circle {
        stroke: var(--ok); stroke-width: 3; fill: none;
        stroke-dasharray: 176; stroke-dashoffset: 176;
        animation: subRing .6s cubic-bezier(.65, 0, .45, 1) forwards;
    }
    .sub-check path {
        stroke: var(--ok); stroke-width: 4; fill: none; stroke-linecap: round; stroke-linejoin: round;
        stroke-dasharray: 48; stroke-dashoffset: 48;
        animation: subTick .35s cubic-bezier(.65, 0, .45, 1) .55s forwards;
    }
    .sub-check .halo {
        fill: var(--ok); stroke: none; opacity: 0; transform-origin: center;
        animation: subHalo 1s ease-out .5s;
    }
    @keyframes subRing { to { stroke-dashoffset: 0; } }
    @keyframes subTick { to { stroke-dashoffset: 0; } }
    @keyframes subHalo { 0% { opacity: .28; transform: scale(.6); } 100% { opacity: 0; transform: scale(1.5); } }

    .sub-done h2 { font-size: 1.6rem; margin-bottom: 6px; animation: subUp .5s ease .7s both; }
    .sub-done .sub-lead { color: var(--graphite); animation: subUp .5s ease .78s both; }
    .sub-meta { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin: 22px 0 26px;
        animation: subUp .5s ease .86s both; }
    .sub-meta .chip { display: inline-flex; align-items: center; gap: 7px; background: var(--ivory);
        border: 1px solid var(--ivory-200); border-radius: var(--radius-pill); padding: 9px 16px; font-size: .88rem; }
    .sub-meta .chip svg { width: 15px; height: 15px; color: var(--amber-600); }
    .sub-meta .chip b { font-weight: 700; }
    .sub-done .btn { animation: subUp .5s ease .94s both; }
    @keyframes subUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

    /* --- Konfeti: potongan kecil jatuh sekali, murni CSS (tanpa pustaka) --- */
    .sub-confetti { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
    .sub-confetti i {
        position: absolute; top: -14px; width: 8px; height: 14px; opacity: 0; border-radius: 2px;
        animation: subFall 2.4s ease-in forwards;
    }
    @keyframes subFall {
        0% { opacity: 0; transform: translateY(0) rotate(0deg); }
        12% { opacity: 1; }
        100% { opacity: 0; transform: translateY(420px) rotate(560deg); }
    }

    /* Hormati pengguna yang mematikan animasi: tampilkan hasil akhirnya saja. */
    @media (prefers-reduced-motion: reduce) {
        .sub-check circle, .sub-check path { animation: none; stroke-dashoffset: 0; }
        .sub-check .halo { display: none; }
        .sub-done h2, .sub-done .sub-lead, .sub-meta, .sub-done .btn { animation: none; opacity: 1; transform: none; }
        .sub-confetti { display: none; }
    }
</style>
@endpush

@section('content')
@if ($tenant->pending_plan)
    <div class="panel" style="max-width:560px">
        <div class="panel-body">
            <h2>Menunggu Konfirmasi Pembayaran</h2>
            <p>Pembayaran Anda sedang diproses. Halaman ini akan menampilkan status terbaru, silakan refresh dalam beberapa saat.</p>
            <a href="{{ route('admin.subscription.finish') }}" class="btn btn-ghost">Refresh Status</a>
        </div>
    </div>
@else
    <div class="panel sub-done">
        {{-- Konfeti dirender dari Blade, bukan JS, supaya tetap muncul walau skrip gagal dimuat. --}}
        <div class="sub-confetti" aria-hidden="true">
            @php $warna = ['var(--amber)', 'var(--ok)', 'var(--petrol)', 'var(--amber-600)']; @endphp
            @for ($i = 0; $i < 18; $i++)
                <i style="left:{{ 3 + $i * 5.4 }}%;
                          background:{{ $warna[$i % 4] }};
                          animation-delay:{{ number_format(0.15 + ($i % 6) * 0.13, 2) }}s;
                          animation-duration:{{ number_format(2.0 + ($i % 5) * 0.22, 2) }}s"></i>
            @endfor
        </div>

        <div class="panel-body">
            <svg class="sub-check" viewBox="0 0 100 100" role="img" aria-label="Pembayaran berhasil">
                <circle class="halo" cx="50" cy="50" r="30" />
                <circle cx="50" cy="50" r="28" />
                <path d="M34 51 L45 62 L67 40" />
            </svg>

            <h2>Langganan Aktif!</h2>
            <p class="sub-lead">Pembayaran Anda berhasil. Semua fitur paket sudah bisa dipakai sekarang.</p>

            <div class="sub-meta">
                <span class="chip"><x-icon name="wallet" /> Paket <b>{{ ucfirst($tenant->plan) }}</b></span>
                @if ($tenant->subscription_ends_at)
                    <span class="chip"><x-icon name="calendar" /> Aktif sampai <b>{{ $tenant->subscription_ends_at->translatedFormat('d F Y') }}</b></span>
                @endif
            </div>

            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">
                Mulai Kelola Armada <x-icon name="arrow-right" />
            </a>
        </div>
    </div>
@endif
@endsection
