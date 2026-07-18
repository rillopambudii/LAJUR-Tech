@extends('layouts.admin')

@section('title', 'Langganan')
@section('crumb', 'Akun')
@section('heading', 'Langganan')

@section('content')
<div class="panel">
    <div class="panel-head">
        <h2>Plan Saat Ini</h2>
        <span class="tag">{{ ucfirst($tenant->plan) }} · {{ $tenant->subscription_status }}</span>
    </div>
    <div class="panel-body">
        @if ($tenant->subscription_status === 'trial')
            <p>Masa trial Anda berakhir pada <strong>{{ $tenant->trial_ends_at?->format('d M Y') }}</strong>.</p>
        @elseif ($tenant->subscription_ends_at)
            <p>Langganan Anda aktif hingga <strong>{{ $tenant->subscription_ends_at->format('d M Y') }}</strong>.</p>
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
                <span class="amount">Rp {{ number_format($plan->price, 0, ',', '.') }}</span>
                <span class="per">/ bulan</span>
            </div>
            <div class="plan-foot">
                @if ($tenant->plan === $plan->key && $tenant->subscription_status === 'active')
                    <button type="button" class="btn btn-ghost btn-block" disabled>Plan Aktif Anda</button>
                @else
                    <form method="POST" action="{{ route('admin.subscription.store', $plan->key) }}">
                        @csrf
                        <button type="submit" class="btn @if ($plan->key === 'pro') btn-primary @else btn-ghost @endif btn-block">
                            Pilih {{ $plan->name }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
