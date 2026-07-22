@extends('layouts.admin')

@section('title', 'Langganan')
@section('crumb', 'Akun')
@section('heading', 'Langganan')

@section('content')
@if (session('locked'))
    <div class="panel" style="border-left:4px solid var(--danger);margin-bottom:20px">
        <div class="panel-body"><strong>{{ session('locked') }}</strong></div>
    </div>
@endif
<div class="panel">
    <div class="panel-head">
        <h2>Plan Saat Ini</h2>
        <span class="tag">{{ ucfirst($tenant->plan) }} · {{ $tenant->subscription_status }}</span>
    </div>
    <div class="panel-body">
        @if (in_array($tenant->subscription_status, ['suspended', 'pending_payment', 'cancelled'], true))
            <p>Akun Anda <strong>terkunci</strong>. Pilih paket di bawah dan selesaikan pembayaran untuk mengaktifkannya kembali.</p>
        @elseif ($tenant->subscription_status === 'trial')
            <p>Masa trial Anda berakhir pada <strong>{{ $tenant->trial_ends_at?->format('d M Y') }}</strong>.</p>
        @elseif ($tenant->subscription_ends_at)
            <p>Langganan Anda aktif hingga <strong>{{ $tenant->subscription_ends_at->format('d M Y') }}</strong>.</p>
        @elseif ($tenant->subscription_status === 'active')
            <p>Langganan <strong>{{ ucfirst($tenant->plan) }}</strong> Anda sedang aktif.</p>
        @else
            <p>Anda saat ini menggunakan plan Basic (gratis).</p>
        @endif
    </div>
</div>

<div class="pricing-grid" style="margin-top:24px">
    @foreach ($plans as $plan)
        <div class="plan-card @if ($plan->key === 'business') is-featured @endif">
            @if ($plan->key === 'business')
                <span class="plan-badge">Paling Populer</span>
            @endif
            <h2 class="plan-name">{{ $plan->name }}</h2>
            <div class="plan-price">
                @if ($plan->hasDiscount())
                    <span style="display:block;font-size:.9rem;color:var(--graphite)">
                        <s>Rp {{ number_format($plan->price, 0, ',', '.') }}</s>
                        @if ($plan->discount_label)
                            <span style="background:rgba(231,178,76,.18);color:var(--amber-600);font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:6px;vertical-align:1px">{{ $plan->discount_label }}</span>
                        @endif
                    </span>
                @endif
                <span class="amount">Rp {{ number_format($plan->effectivePrice(), 0, ',', '.') }}</span>
                <span class="per">/ bulan</span>
            </div>
            <div class="plan-foot">
                @if ($tenant->plan === $plan->key && $tenant->subscription_status === 'active')
                    <button type="button" class="btn btn-ghost btn-block" disabled>Plan Aktif Anda</button>
                @else
                    <form method="POST" action="{{ route('admin.subscription.store', $plan->key) }}">
                        @csrf
                        {{-- Kartu Business berlatar navy (is-featured). Tombol ghost di situ
                             berteks navy juga, jadi TAK TERLIHAT sama sekali — tombol "Pilih
                             Business" seolah hilang. Dibuat amber seperti halaman /daftar,
                             yang memang sudah memakai tombol utama untuk kartu unggulan. --}}
                        <button type="submit" class="btn @if (in_array($plan->key, ['pro', 'business'], true)) btn-primary @else btn-ghost @endif btn-block">
                            Pilih {{ $plan->name }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
