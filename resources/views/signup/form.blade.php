@extends('layouts.public')

@section('title', 'Daftar — Lajur')

@section('content')
<main id="main" class="container" style="padding:48px 0;max-width:480px">
    <h1>
        @if ($mode === 'trial')
            Coba Gratis 14 Hari
        @else
            Daftar Paket {{ $plan->name }} (Rp {{ number_format($plan->price, 0, ',', '.') }}/bulan)
        @endif
    </h1>

    <form method="POST" action="{{ $mode === 'trial' ? route('signup.trial.store') : route('signup.paid.store', $plan->key) }}">
        @csrf
        <label>Nama Bisnis <input type="text" name="business_name" value="{{ old('business_name') }}" required></label>
        <label>Slug <input type="text" name="slug" value="{{ old('slug') }}" required placeholder="mis. rental-saya"></label>
        <label>Nama Pemilik <input type="text" name="owner_name" value="{{ old('owner_name') }}" required></label>
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>Kata Sandi <input type="password" name="password" required minlength="8"></label>

        <button type="submit" class="btn btn-primary">
            @if ($mode === 'trial')
                Mulai Trial
            @else
                Lanjut ke Pembayaran
            @endif
        </button>
    </form>
</main>
@endsection
