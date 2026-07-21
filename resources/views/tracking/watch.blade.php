@extends('layouts.public')

@section('title', 'Pantau Perjalanan — Lajur')

@php
    $firstName = explode(' ', trim($booking->customer_name))[0] ?? 'Perjalanan';
    $waCs = '6281200000000';
    $waText = "Halo Lajur, saya keluarga penumpang, mau tanya soal perjalanan {$booking->car_name}.";
    $waUrl = 'https://wa.me/'.$waCs.'?text='.rawurlencode($waText);
    $stages = [ ['label'=>'Belum Diproses','at'=>10], ['label'=>'Disiapkan','at'=>35], ['label'=>'Dalam Perjalanan','at'=>70], ['label'=>'Tiba','at'=>100] ];
    $progress = $booking->trip_progress;
@endphp

@section('content')
<section class="section" id="pantau">
    <div class="container" style="max-width:640px">
        <div class="section-head reveal" style="text-align:left;margin-bottom:22px">
            <span class="eyebrow">Pantau Langsung</span>
            <h1 class="section-title" style="font-size:1.6rem;margin-bottom:4px">Perjalanan {{ $firstName }}</h1>
            <p class="section-sub" style="margin:0">Kamu memantau perjalanan ini demi keselamatan bersama.</p>
        </div>

        {{-- progress --}}
        <div class="panel reveal" style="margin-bottom:20px"><div class="panel-body">
            <div style="position:relative;margin:14px 6px 10px">
                <div style="position:absolute;top:9px;left:0;right:0;height:4px;background:var(--ivory-200);border-radius:99px"></div>
                <div style="position:absolute;top:9px;left:0;width:{{ $progress }}%;height:4px;background:var(--amber);border-radius:99px"></div>
                <div style="position:relative;display:flex;justify-content:space-between">
                    @foreach ($stages as $stage)
                        @php $reached = $progress >= $stage['at']; @endphp
                        <div style="display:flex;flex-direction:column;align-items:center;flex:1;text-align:center">
                            <span style="width:20px;height:20px;border-radius:99px;border:3px solid {{ $reached ? 'var(--amber)' : 'var(--ivory-200)' }};background:{{ $reached ? 'var(--amber)' : '#fff' }}"></span>
                            <span style="font-size:.72rem;margin-top:8px;color:{{ $reached ? 'var(--petrol)' : 'rgba(15,27,51,.5)' }}">{{ $stage['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div></div>

        {{-- status + eta --}}
        <div class="panel reveal" style="margin-bottom:20px"><div class="panel-body" style="text-align:center;padding:24px 20px">
            <span class="eyebrow" style="justify-content:center">Status saat ini</span>
            <div style="font-family:var(--font-display);font-weight:800;font-size:1.7rem;color:var(--petrol);margin:6px 0 4px">{{ $booking->trip_status_label }}</div>
            @if ($demo)
                <p data-eta style="margin:0;color:var(--petrol-600)"><x-icon name="clock" style="width:16px;height:16px;vertical-align:-2px" /> Estimasi tiba: <span data-eta-min>—</span> menit</p>
            @elseif ($booking->eta_manual_note)
                <p style="margin:0;color:var(--petrol-600)"><x-icon name="clock" style="width:16px;height:16px;vertical-align:-2px" /> Estimasi tiba: {{ $booking->eta_manual_note }}</p>
            @endif
        </div></div>

        {{-- map --}}
        <div class="panel reveal" style="margin-bottom:20px"><div class="panel-body">
            @if ($demo)
                <div id="tracking-map" style="height:260px;border-radius:var(--radius);overflow:hidden;background:var(--ivory-200)"></div>
            @else
                <div style="text-align:center;padding:30px 20px;color:rgba(15,27,51,.55)">
                    <x-icon name="pin" style="width:38px;height:38px;margin-bottom:10px;color:var(--amber-600)" />
                    <p style="margin:0;font-weight:600;color:var(--petrol)">Lokasi langsung belum aktif</p>
                    <p style="margin:4px 0 0;font-size:.9rem">Peta akan muncul saat mobil dalam perjalanan.</p>
                </div>
            @endif
        </div></div>

        {{-- car / driver (no price) --}}
        <div class="panel reveal" style="margin-bottom:20px">
            <div class="panel-head"><h2>Kendaraan</h2></div>
            <div class="panel-body"><div class="detail-grid">
                <div class="detail-item"><div class="k">Mobil</div><div class="v">{{ $booking->car_name }}</div></div>
                <div class="detail-item"><div class="k">Plat</div><div class="v">{{ $booking->car?->plate_number ?? '—' }}</div></div>
                <div class="detail-item">
                    <div class="k">Pengemudi</div>
                    <div class="v">
                        @if ($booking->driver)
                            <a href="{{ route('driver.public-profile', $booking->driver_id) }}">{{ $booking->driver->name }}</a>
                        @else
                            Belum ditentukan
                        @endif
                    </div>
                </div>
            </div></div>
        </div>

        <div style="text-align:center">
            <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="btn btn-ghost" style="color:#128c7e;border-color:rgba(18,140,126,.3)"><x-icon name="whatsapp" /> Hubungi CS</a>
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
        onEta: function (e) { var el = document.querySelector('[data-eta-min]'); if (el) el.textContent = e.arrived ? '0 — Tiba' : e.minutes; }
    });
</script>
@endpush
@endif
