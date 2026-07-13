@extends('layouts.admin')

@section('title', 'Status Langganan')
@section('crumb', 'Akun')
@section('heading', 'Status Langganan')

@section('content')
<div class="panel" style="max-width:560px">
    <div class="panel-body">
        @if ($tenant->pending_plan)
            <h2>Menunggu Konfirmasi Pembayaran</h2>
            <p>Pembayaran Anda sedang diproses. Halaman ini akan menampilkan status terbaru, silakan refresh dalam beberapa saat.</p>
            <a href="{{ route('admin.subscription.finish') }}" class="btn btn-ghost">Refresh Status</a>
        @else
            <h2>Langganan Aktif</h2>
            <p>Plan {{ ucfirst($tenant->plan) }} Anda sudah aktif.</p>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Ke Dashboard</a>
        @endif
    </div>
</div>
@endsection
