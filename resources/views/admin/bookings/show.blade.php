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
                    @if ($booking->booking_code)
                        <div class="detail-item"><div class="k">Kode Booking</div><div class="v mono" style="color:var(--petrol);letter-spacing:.04em">{{ $booking->booking_code }}</div></div>
                    @endif
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
                    <div class="detail-item"><div class="k">Jarak Tempuh</div><div class="v">{{ number_format($booking->distanceKm(), 0, ',', '.') }} km</div></div>
                    <div class="detail-item"><div class="k">Driver</div><div class="v">{{ $booking->driver?->name ?? '—' }}</div></div>
                    <div class="detail-item"><div class="k">Harga / Hari</div><div class="v mono">Rp {{ number_format($booking->price_per_day, 0, ',', '.') }}</div></div>
                    <div class="detail-item"><div class="k">Total</div><div class="v mono" style="color:var(--petrol);font-size:1.25rem">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</div></div>
                    <div class="detail-item"><div class="k">Pembayaran</div><div class="v">{{ $booking->payment_status_label }}@if($booking->paid_at) <span class="tag">{{ $booking->paid_at->format('d M Y H:i') }}</span>@endif</div></div>
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

            <form action="{{ route('admin.bookings.trip-status', $booking) }}" method="POST">
                @csrf @method('PATCH')
                <div class="field">
                    <label for="trip_status">Status Perjalanan</label>
                    <select class="select" id="trip_status" name="trip_status">
                        @foreach (\App\Models\Booking::TRIP_STATUSES as $ts)
                            <option value="{{ $ts }}" @selected($booking->trip_status === $ts)>{{ \App\Models\Booking::TRIP_STATUS_LABELS[$ts] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="eta_manual_note">Estimasi Tiba (opsional)</label>
                    <input type="text" class="input" id="eta_manual_note" name="eta_manual_note" maxlength="100"
                           value="{{ old('eta_manual_note', $booking->eta_manual_note) }}"
                           placeholder="cth: ±45 menit lagi / sekitar pukul 14.30">
                </div>
                @php $pos = $booking->car?->latestPosition; @endphp
                <p style="font-size:.82rem;color:rgba(0,0,0,.5);margin:0 0 12px">
                    Data GPS:
                    @if ($pos && $pos->device_time)
                        posisi terakhir {{ $pos->device_time->diffForHumans() }}
                    @else
                        belum terhubung
                    @endif
                </p>
                <button type="submit" class="btn btn-primary btn-block"><x-icon name="pin" /> Perbarui Perjalanan</button>
            </form>

            <hr style="border:0;border-top:1px solid var(--ivory-200);margin:20px 0">

            <form action="{{ route('admin.bookings.driver', $booking) }}" method="POST">
                @csrf @method('PATCH')
                <div class="field">
                    <label for="driver_id">Tugaskan Driver</label>
                    <select class="select" id="driver_id" name="driver_id">
                        <option value="">— Tanpa driver —</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected($booking->driver_id === $driver->id)>{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($drivers->isEmpty())
                    <p style="font-size:.82rem;color:rgba(0,0,0,.5);margin:0 0 12px">Belum ada driver. <a href="{{ route('admin.drivers.create') }}">Tambah driver</a> dulu.</p>
                @endif
                <button type="submit" class="btn btn-ghost btn-block"><x-icon name="users" /> Simpan Driver</button>
            </form>

            <hr style="border:0;border-top:1px solid var(--ivory-200);margin:20px 0">

            @php
                $waMessage = "Halo {$booking->customer_name}, berikut invoice {$booking->invoiceNumber()} untuk sewa {$booking->car_name} "
                    . $booking->start_date->translatedFormat('d M') . '–' . $booking->end_date->translatedFormat('d M Y')
                    . ' (' . $booking->days . ' hari). Total: Rp ' . number_format($booking->total_price, 0, ',', '.') . '. Terima kasih.';
            @endphp
            <div style="display:grid;gap:10px">
                <a href="{{ route('admin.bookings.invoice', $booking) }}" target="_blank" class="btn btn-ghost btn-block"><x-icon name="list" /> Lihat / Cetak Invoice</a>
                <a href="{{ $booking->whatsappUrl($waMessage) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-block" style="color:#128c7e;border-color:rgba(18,140,126,.3)"><x-icon name="whatsapp" /> Kirim via WhatsApp</a>
                <form action="{{ route('admin.bookings.email', $booking) }}" method="POST" data-confirm="Kirim invoice ke {{ $booking->customer_email }}?">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-block"><x-icon name="mail" /> Kirim Invoice via Email</button>
                </form>
            </div>

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

@if ($booking->car)
<div class="panel" style="margin-top:20px" data-replay-panel hidden>
    <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <h2>Replay Perjalanan</h2>
        <div style="display:flex;gap:8px;align-items:center">
            <button type="button" class="btn btn-sm" id="replay-play" aria-label="Play">&#9654;</button>
            <select id="replay-speed" class="btn btn-sm" aria-label="Kecepatan">
                <option value="1">1x</option><option value="2">2x</option><option value="4">4x</option><option value="8">8x</option>
            </select>
            <span id="replay-clock" class="mono" style="font-size:.85rem;color:var(--petrol-600)"></span>
        </div>
    </div>
    <div class="panel-body">
        <div id="replay-map" style="height:320px;border-radius:var(--radius);overflow:hidden;background:var(--ivory-200)"></div>
        <input type="range" id="replay-scrubber" min="0" max="0" value="0" style="width:100%;margin-top:12px">
        <p id="replay-summary" style="margin:8px 0 0;font-size:.9rem;color:var(--petrol-600)">Memuat…</p>
    </div>
</div>
<div style="margin-top:12px">
    <button type="button" class="btn btn-ghost btn-block" id="replay-toggle"><x-icon name="route" /> Replay Perjalanan</button>
</div>
@endif
@endsection

@if ($booking->car)
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/booking-replay.js') }}"></script>
<script>
    (function () {
        var started = false;
        document.getElementById('replay-toggle').addEventListener('click', function () {
            var panel = document.querySelector('[data-replay-panel]');
            panel.hidden = !panel.hidden;
            if (!panel.hidden && !started) {
                started = true;
                window.BookingReplay.init({
                    mapEl: 'replay-map',
                    url: @json(route('admin.bookings.replay', $booking)),
                    controls: { playBtn: 'replay-play', speedSel: 'replay-speed', scrubber: 'replay-scrubber', clock: 'replay-clock', summary: 'replay-summary' }
                });
            }
        });
    })();
</script>
@endpush
@endif
