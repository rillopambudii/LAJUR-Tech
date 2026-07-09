@extends('layouts.public')

@section('title', 'Lacak Pesanan '.$booking->booking_code.' — Lajur')

@php
    // Delivery stages shown in the progress bar, in order. The active/completed
    // point follows $booking->trip_progress (0-100).
    $stages = [
        ['label' => 'Belum Diproses',   'at' => 10],
        ['label' => 'Disiapkan',        'at' => 35],
        ['label' => 'Dalam Perjalanan', 'at' => 70],
        ['label' => 'Tiba',             'at' => 100],
    ];
    $progress = $booking->trip_progress;

    $waCs = '6281200000000'; // nomor CS Lajur
    $waText = "Halo Lajur, saya mau tanya soal pesanan saya dengan kode {$booking->booking_code}.";
    $waUrl = 'https://wa.me/'.$waCs.'?text='.rawurlencode($waText);
@endphp

@section('content')
<section class="section" id="lacak">
    <div class="container" style="max-width:760px">

        <div class="section-head reveal" style="text-align:left;margin-bottom:26px">
            <span class="eyebrow">Lacak Pesanan</span>
            <h1 class="section-title" style="font-size:1.7rem;margin-bottom:6px">Status pesanan kamu</h1>
            <p class="section-sub" style="margin:0">
                Kode booking:
                <strong class="mono" style="color:var(--petrol);letter-spacing:.04em">{{ $booking->booking_code }}</strong>
            </p>
        </div>

        {{-- ===== Progress bar 4 tahap ===== --}}
        <div class="panel reveal" style="margin-bottom:20px">
            <div class="panel-body">
                <div style="position:relative;margin:14px 6px 10px">
                    <div style="position:absolute;top:9px;left:0;right:0;height:4px;background:var(--ivory-200);border-radius:99px"></div>
                    <div style="position:absolute;top:9px;left:0;width:{{ $progress }}%;height:4px;background:var(--amber);border-radius:99px;transition:width .4s ease"></div>
                    <div style="position:relative;display:flex;justify-content:space-between">
                        @foreach ($stages as $stage)
                            @php $reached = $progress >= $stage['at']; @endphp
                            <div style="display:flex;flex-direction:column;align-items:center;flex:1;text-align:center">
                                <span style="width:22px;height:22px;border-radius:99px;border:3px solid {{ $reached ? 'var(--amber)' : 'var(--ivory-200)' }};background:{{ $reached ? 'var(--amber)' : '#fff' }};box-shadow:{{ $reached ? '0 4px 12px var(--amber-glow)' : 'none' }}"></span>
                                <span style="font-size:.76rem;margin-top:8px;font-weight:{{ $reached ? 600 : 500 }};color:{{ $reached ? 'var(--petrol)' : 'rgba(15,27,51,.5)' }}">{{ $stage['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Kartu status utama ===== --}}
        <div class="panel reveal" style="margin-bottom:20px">
            <div class="panel-body" style="text-align:center;padding:28px 20px">
                <span class="eyebrow" style="justify-content:center">Status saat ini</span>
                <div style="font-family:var(--font-display);font-weight:800;font-size:1.9rem;color:var(--petrol);margin:6px 0 4px">
                    {{ $booking->trip_status_label }}
                </div>
                @if ($booking->eta_manual_note)
                    <p style="margin:0;color:var(--petrol-600);font-size:1rem">
                        <x-icon name="clock" style="width:16px;height:16px;vertical-align:-2px" /> Estimasi tiba: {{ $booking->eta_manual_note }}
                    </p>
                @endif
            </div>
        </div>

        {{-- ===== Slot peta (disiapkan untuk Fase 2 — GPS live via Traccar) ===== --}}
        <div class="panel reveal" style="margin-bottom:20px">
            <div class="panel-body">
                @if ($demo)
                    <div data-eta style="text-align:center;margin-bottom:12px;font-weight:600;color:var(--petrol)">
                        <x-icon name="clock" style="width:16px;height:16px;vertical-align:-2px" />
                        Estimasi tiba: <span data-eta-min>—</span> menit
                    </div>
                    <div id="tracking-map" style="height:280px;border-radius:var(--radius);overflow:hidden;background:var(--ivory-200)"></div>
                @elseif ($booking->has_live_gps)
                    {{-- Fase 2: render peta live di sini via public/js/tracking.js,
                         memakai posisi terakhir dari $booking->car->latestPosition
                         (vehicle_positions / Traccar). --}}
                    <div id="tracking-map"
                         data-lat="{{ $booking->car?->latestPosition?->latitude }}"
                         data-lng="{{ $booking->car?->latestPosition?->longitude }}"
                         style="height:280px;border-radius:var(--radius);background:var(--ivory-200)"></div>
                @else
                    <div style="text-align:center;padding:34px 20px;color:rgba(15,27,51,.55)">
                        <x-icon name="pin" style="width:40px;height:40px;margin-bottom:10px;color:var(--amber-600)" />
                        <p style="margin:0;font-weight:600;color:var(--petrol)">Pelacakan langsung belum aktif</p>
                        <p style="margin:4px 0 0;font-size:.9rem">Peta lokasi akan muncul di sini begitu mobil berangkat menuju lokasimu.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== Detail booking ===== --}}
        <div class="panel reveal" style="margin-bottom:20px">
            <div class="panel-head"><h2>Detail Pesanan</h2></div>
            <div class="panel-body">
                <div class="detail-grid">
                    <div class="detail-item"><div class="k">Mobil</div><div class="v">{{ $booking->car_name }}</div></div>
                    <div class="detail-item"><div class="k">Tanggal Mulai</div><div class="v">{{ $booking->start_date->translatedFormat('d M Y') }}</div></div>
                    <div class="detail-item"><div class="k">Tanggal Selesai</div><div class="v">{{ $booking->end_date->translatedFormat('d M Y') }}</div></div>
                    <div class="detail-item"><div class="k">Lama Sewa</div><div class="v">{{ $booking->days }} hari</div></div>
                    <div class="detail-item"><div class="k">Total</div><div class="v mono" style="color:var(--petrol)">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</div></div>
                </div>
            </div>
        </div>

        {{-- ===== Bagikan ke keluarga ===== --}}
        <div style="text-align:center;margin-bottom:10px">
            <button type="button" class="btn btn-primary" data-share
                data-url="{{ route('tracking.watch', $booking->booking_code) }}"
                data-text="Pantau perjalanan saya ({{ $booking->car_name }}) secara langsung:">
                <x-icon name="pin" /> Bagikan ke keluarga
            </button>
        </div>

        {{-- ===== Bantuan ===== --}}
        <div style="text-align:center">
            <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="btn btn-ghost" style="color:#128c7e;border-color:rgba(18,140,126,.3)">
                <x-icon name="whatsapp" /> Butuh bantuan? Hubungi CS
            </a>
        </div>

    </div>
</section>
@endsection

@if ($demo)
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/tracking-demo.js') }}"></script>
<script>
    window.TrackingDemo.trip('tracking-map', {
        routesUrl: @json(asset('js/demo-routes.json')),
        onEta: function (e) {
            document.querySelector('[data-eta-min]').textContent = e.arrived ? '0 — Tiba' : e.minutes;
        }
    });
</script>
@endpush
@endif

@push('scripts')
<script>
    (function () {
        var btn = document.querySelector('[data-share]');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-url'), text = btn.getAttribute('data-text');
            if (navigator.share) {
                navigator.share({ title: 'Pantau Perjalanan', text: text, url: url }).catch(function () {});
            } else {
                window.open('https://wa.me/?text=' + encodeURIComponent(text + ' ' + url), '_blank');
            }
        });
    })();
</script>
@endpush
