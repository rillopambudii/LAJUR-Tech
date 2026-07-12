@extends('layouts.public')

@section('title', 'Harga & Paket — Lajur')

@section('content')
<main id="main" class="container" style="padding:48px 0">
    <h1>Pilih Paket Lajur</h1>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-top:24px">
        <div class="card" style="padding:20px;border:1px solid #e2e2e2;border-radius:12px">
            <h2>Coba Gratis</h2>
            <p>14 hari, akses penuh (setara Business)</p>
            <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">Coba Gratis 14 Hari</a>
        </div>

        @foreach ($plans as $plan)
            <div class="card" style="padding:20px;border:1px solid #e2e2e2;border-radius:12px">
                <h2>{{ $plan->name }}</h2>
                <p>Rp {{ number_format($plan->price, 0, ',', '.') }} / bulan</p>
                <ul>
                    @foreach ($plan->features as $feature)
                        <li>{{ $feature->name }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('signup.paid.form', $plan->key) }}" class="btn btn-primary">Pilih {{ $plan->name }}</a>
            </div>
        @endforeach
    </div>
</main>
@endsection
