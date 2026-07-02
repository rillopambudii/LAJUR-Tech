@extends('layouts.admin')

@section('title', 'Detail Booking')
@section('crumb', 'Manajemen / Booking')
@section('heading', 'Detail Booking #' . $booking->id)

@section('topbar-action')
    <a href="{{ route('admin.bookings.index') }}" class="btn btn-ghost">&larr; Kembali</a>
@endsection

@section('content')
<div class="panel-grid">
    <div>
        <div class="panel">
            <div class="panel-head">
                <h2>Data Penyewa</h2>
                <span class="pill pill-{{ $booking->status }}">{{ $booking->status_label }}</span>
            </div>
            <div class="panel-body">
                <div class="detail-grid">
                    <div class="detail-item"><div class="k">Nama</div><div class="v">{{ $booking->customer_name }}</div></div>
                    <div class="detail-item"><div class="k">Email</div><div class="v"><a href="mailto:{{ $booking->customer_email }}">{{ $booking->customer_email }}</a></div></div>
                    <div class="detail-item"><div class="k">Nomor HP</div><div class="v"><a href="tel:{{ $booking->customer_phone }}">{{ $booking->customer_phone }}</a></div></div>
                    <div class="detail-item"><div class="k">Dibuat</div><div class="v">{{ $booking->created_at->format('d M Y, H:i') }}</div></div>
                </div>
                @if ($booking->notes)
                    <div class="detail-item" style="margin-top:18px">
                        <div class="k">Catatan</div>
                        <div class="v" style="font-weight:400">{{ $booking->notes }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>Rincian Sewa</h2></div>
            <div class="panel-body">
                <div class="detail-grid">
                    <div class="detail-item"><div class="k">Mobil</div><div class="v">{{ $booking->car_name }} @unless($booking->car)<span class="tag">unit dihapus</span>@endunless</div></div>
                    <div class="detail-item"><div class="k">Tanggal Mulai</div><div class="v">{{ $booking->start_date->format('d M Y') }}</div></div>
                    <div class="detail-item"><div class="k">Tanggal Selesai</div><div class="v">{{ $booking->end_date->format('d M Y') }}</div></div>
                    <div class="detail-item"><div class="k">Lama Sewa</div><div class="v">{{ $booking->days }} hari</div></div>
                    <div class="detail-item"><div class="k">Harga / Hari</div><div class="v mono">Rp {{ number_format($booking->price_per_day, 0, ',', '.') }}</div></div>
                    <div class="detail-item"><div class="k">Total</div><div class="v mono" style="color:var(--petrol);font-size:1.25rem">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel preview-card">
        <div class="panel-head"><h2>Ubah Status</h2></div>
        <div class="panel-body">
            <form action="{{ route('admin.bookings.status', $booking) }}" method="POST">
                @csrf @method('PATCH')
                <div class="field">
                    <label for="status">Status Booking</label>
                    <select class="select" id="status" name="status">
                        @foreach (\App\Models\Booking::STATUSES as $s)
                            <option value="{{ $s }}" @selected($booking->status === $s)>{{ \App\Models\Booking::STATUS_LABELS[$s] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><x-icon name="check" /> Perbarui Status</button>
            </form>

            <hr style="border:0;border-top:1px solid var(--ivory-200);margin:20px 0">

            <form action="{{ route('admin.bookings.destroy', $booking) }}" method="POST" data-confirm="Hapus booking ini secara permanen?">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-block" style="color:var(--danger);border-color:rgba(200,69,59,.3)">
                    <x-icon name="trash" /> Hapus Booking
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
