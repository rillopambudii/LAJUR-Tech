@extends('layouts.public')

@section('title', 'Status Pembayaran — Lajur')

@push('head')
<style>
    .pay-wrap{max-width:560px;margin:60px auto;padding:0 20px;text-align:center}
    .pay-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:18px;padding:40px 32px;box-shadow:0 10px 40px rgba(0,0,0,.06)}
    .pay-emoji{font-size:3rem;line-height:1;margin-bottom:12px}
    .pay-title{font-family:'Sora',sans-serif;font-weight:800;font-size:1.4rem;margin:0 0 8px}
    .pay-msg{color:rgba(0,0,0,.6);line-height:1.6;margin:0 0 22px}
    .pay-detail{text-align:left;border-top:1px solid rgba(0,0,0,.08);padding-top:18px;margin-top:6px;font-size:.92rem}
    .pay-detail div{display:flex;justify-content:space-between;padding:5px 0}
    .pay-detail .k{color:rgba(0,0,0,.55)}
    .pay-badge{display:inline-block;padding:4px 14px;border-radius:999px;font-weight:700;font-size:.85rem}
    .pay-badge.paid{background:#dcfce7;color:#166534}.pay-badge.pending{background:#fef3c7;color:#92400e}
    .pay-badge.failed,.pay-badge.expired,.pay-badge.unpaid{background:#fee2e2;color:#991b1b}
</style>
@endpush

@section('content')
<div class="pay-wrap">
    <div class="pay-card">
        @php $status = $booking?->payment_status ?? 'unpaid'; @endphp
        @if ($status === 'paid')
            <div class="pay-emoji">✅</div>
            <h1 class="pay-title">Pembayaran Berhasil</h1>
            <p class="pay-msg">Terima kasih! Booking Anda sudah kami konfirmasi. Detail pesanan telah kami catat.</p>
        @elseif ($status === 'pending')
            <div class="pay-emoji">⏳</div>
            <h1 class="pay-title">Menunggu Pembayaran</h1>
            <p class="pay-msg">Pembayaran Anda sedang diproses. Status akan otomatis diperbarui begitu pembayaran kami terima.</p>
        @elseif (! $booking)
            <div class="pay-emoji">🔎</div>
            <h1 class="pay-title">Transaksi Tidak Ditemukan</h1>
            <p class="pay-msg">Kami tidak menemukan data transaksi ini. Jika Anda merasa sudah membayar, silakan hubungi kami.</p>
        @else
            <div class="pay-emoji">⚠️</div>
            <h1 class="pay-title">Pembayaran Belum Selesai</h1>
            <p class="pay-msg">Pembayaran belum berhasil diselesaikan. Anda dapat mencoba melakukan booking kembali.</p>
        @endif

        @if ($booking)
            <div class="pay-detail">
                <div><span class="k">Mobil</span><span>{{ $booking->car_name }}</span></div>
                <div><span class="k">Periode</span><span>{{ $booking->start_date->format('d M') }} – {{ $booking->end_date->format('d M Y') }}</span></div>
                <div><span class="k">Total</span><span>Rp {{ number_format($booking->total_price, 0, ',', '.') }}</span></div>
                <div><span class="k">Status</span><span class="pay-badge {{ $status }}">{{ $booking->payment_status_label }}</span></div>
                @if ($booking->booking_code)
                    <div><span class="k">Kode Booking</span><span class="mono" style="font-weight:700;letter-spacing:.04em">{{ $booking->booking_code }}</span></div>
                @endif
            </div>

            @if ($booking->booking_code)
                <p class="pay-msg" style="margin:18px 0 0;font-size:.88rem">Simpan kode booking di atas untuk melacak status pesananmu kapan saja.</p>
            @endif
        @endif

        <p style="margin-top:24px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            @if ($booking && $booking->booking_code)
                <a href="{{ route('tracking.show', $booking->booking_code) }}" class="btn btn-primary"><x-icon name="pin" /> Lacak Pesanan</a>
                <a href="{{ route('home') }}" class="btn btn-ghost">Kembali ke Beranda</a>
            @else
                <a href="{{ route('home') }}" class="btn btn-primary">Kembali ke Beranda</a>
            @endif
        </p>
    </div>
</div>
@endsection
