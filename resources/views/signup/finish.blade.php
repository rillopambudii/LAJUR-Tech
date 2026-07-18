@extends('layouts.platform')

@section('title', 'Status Pembayaran - Lajur')

@section('content')
<div class="container" style="padding:48px 0;max-width:480px">
    @if (! $tenant)
        <h1>Transaksi tidak ditemukan</h1>
        <p>Silakan <a href="{{ route('signup.pricing') }}">coba daftar lagi</a>.</p>
    @elseif ($tenant->subscription_status === 'active')
        <h1>Pembayaran Berhasil</h1>
        <p>Paket {{ $tenant->plan }} untuk {{ $tenant->name }} sudah aktif.</p>
        <a href="{{ route('login') }}" class="btn btn-primary">Login Sekarang</a>
    @else
        <h1>Menunggu Konfirmasi Pembayaran</h1>
        <p>Pembayaran Anda sedang diproses. Halaman ini akan menampilkan status terbaru, silakan refresh dalam beberapa saat.</p>
        <a href="{{ route('signup.finish') }}?order_id={{ $tenant->payment_ref }}" class="btn btn-ghost">Refresh Status</a>
    @endif
</div>
@endsection
