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

@push('head')
<style>
    .trk-detail .trk-ic{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;flex:none;
        border-radius:10px;background:var(--ivory-200);color:var(--petrol)}
    .trk-detail .trk-ic svg{width:18px;height:18px}
    .trk-detail .trk-ic-lg{width:46px;height:46px;border-radius:14px;background:var(--petrol);color:#fff}
    .trk-detail .trk-ic-lg svg{width:24px;height:24px}
    .trk-detail .trk-ic-amber{background:var(--amber);color:#fff;box-shadow:0 4px 12px var(--amber-glow)}

    .trk-hero{display:flex;align-items:center;gap:14px;padding-bottom:16px;margin-bottom:6px;
        border-bottom:1px solid var(--ivory-200)}
    .trk-hero-v{font-family:var(--font-display);font-weight:800;font-size:1.25rem;color:var(--petrol);line-height:1.25}

    .trk-k{font-size:.78rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:rgba(15,27,51,.5)}
    .trk-rows{display:flex;flex-direction:column}
    .trk-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--ivory-200)}
    .trk-row .trk-k{flex:1}
    .trk-v{font-weight:600;color:var(--petrol);text-align:right}
    .trk-sep{color:var(--amber-600);margin:0 2px}

    .trk-total{display:flex;align-items:center;gap:12px;margin-top:16px;padding:14px 16px;border-radius:var(--radius);
        background:linear-gradient(135deg,rgba(255,255,255,.9),var(--ivory-200));border:1px solid var(--ivory-200)}
    .trk-total-k{flex:1;font-weight:600;color:var(--petrol-600)}
    .trk-total-v{font-weight:800;font-size:1.2rem;color:var(--petrol)}

    @media (max-width:520px){
        .trk-row{flex-wrap:wrap}
        .trk-row .trk-k{flex:1 0 auto}
        .trk-v{width:100%;text-align:left;padding-left:46px}
    }
</style>
@endpush

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
                        <x-icon name="clock" style="display:inline-block;width:16px;height:16px;vertical-align:-2px" /> Estimasi tiba: {{ $booking->eta_manual_note }}
                    </p>
                @endif
            </div>
        </div>

        {{-- ===== Slot peta (disiapkan untuk Fase 2 — GPS live via Traccar) ===== --}}
        <div class="panel reveal" style="margin-bottom:20px">
            <div class="panel-body">
                @if ($demo)
                    <div data-eta style="text-align:center;margin-bottom:12px;font-weight:600;color:var(--petrol)">
                        <x-icon name="clock" style="display:inline-block;width:16px;height:16px;vertical-align:-2px" />
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
        <div class="panel reveal trk-detail" style="margin-bottom:20px">
            <div class="panel-head"><h2>Detail Pesanan</h2></div>
            <div class="panel-body">

                {{-- Mobil: baris utama, lebih besar dari yang lain --}}
                <div class="trk-hero">
                    <span class="trk-ic trk-ic-lg"><x-icon name="car" /></span>
                    <div>
                        <div class="trk-k">Mobil</div>
                        <div class="trk-hero-v">{{ $booking->car_name }}</div>
                    </div>
                </div>

                <div class="trk-rows">
                    <div class="trk-row">
                        <span class="trk-ic"><x-icon name="calendar" /></span>
                        <span class="trk-k">Periode Sewa</span>
                        <span class="trk-v">
                            {{ $booking->start_date->translatedFormat('d M Y') }}
                            <span class="trk-sep">→</span>
                            {{ $booking->end_date->translatedFormat('d M Y') }}
                        </span>
                    </div>
                    <div class="trk-row">
                        <span class="trk-ic"><x-icon name="clock" /></span>
                        <span class="trk-k">Lama Sewa</span>
                        <span class="trk-v">{{ $booking->days }} hari</span>
                    </div>
                    <div class="trk-row">
                        <span class="trk-ic"><x-icon name="route" /></span>
                        <span class="trk-k">Jarak Tempuh</span>
                        <span class="trk-v">{{ number_format($booking->distanceKm(), 0, ',', '.') }} km</span>
                    </div>
                </div>

                <div class="trk-total">
                    <span class="trk-ic trk-ic-amber"><x-icon name="wallet" /></span>
                    <span class="trk-total-k">Total Pembayaran</span>
                    <span class="trk-total-v mono">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</span>
                </div>

            </div>
        </div>

        {{-- ===== Ulasan (hanya kalau booking selesai) ===== --}}
        @if ($booking->status === 'completed')
            @if (session('review_success'))
                <div class="alert alert-success" role="status"><x-icon name="check" /> <span>{{ session('review_success') }}</span></div>
            @endif
            @if (session('review_error'))
                <div class="alert alert-error" role="alert"><x-icon name="alert" /> <span>{{ session('review_error') }}</span></div>
            @endif
            @if (session('testimonial_success'))
                <div class="alert alert-success" role="status"><x-icon name="check" /> <span>{{ session('testimonial_success') }}</span></div>
            @endif
            @if (session('testimonial_error'))
                <div class="alert alert-error" role="alert"><x-icon name="alert" /> <span>{{ session('testimonial_error') }}</span></div>
            @endif

            @if ($booking->driver)
                <div class="panel reveal" style="margin-bottom:20px">
                    <div class="panel-head">
                        <h2>Ulasan untuk {{ $booking->driver->name }}</h2>
                        <a href="{{ route('driver.public-profile', $booking->driver_id) }}" class="tag">Lihat Profil Driver</a>
                    </div>
                    <div class="panel-body">
                        @if ($driverReview)
                            <p style="color:var(--graphite)">
                                @if ($driverReview->status === 'pending')
                                    Ulasan Anda sedang ditinjau. Terima kasih sudah meluangkan waktu!
                                @else
                                    Terima kasih atas ulasan Anda untuk driver ini.
                                @endif
                            </p>
                        @else
                            <p style="margin-bottom:14px;color:var(--graphite)">Bagaimana pengalaman Anda dengan driver ini?</p>
                            <form method="POST" action="{{ route('driver-review.store', $booking->booking_code) }}">
                                @csrf
                                <div class="form-row">
                                    <div class="field">
                                        <label>Ketepatan Waktu</label>
                                        <x-star-input name="rating_punctuality" required />
                                        @error('rating_punctuality')<span class="field-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="field">
                                        <label>Kebersihan & Kondisi Mobil</label>
                                        <x-star-input name="rating_cleanliness" required />
                                        @error('rating_cleanliness')<span class="field-error">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="field">
                                        <label>Keramahan & Sikap</label>
                                        <x-star-input name="rating_friendliness" required />
                                        @error('rating_friendliness')<span class="field-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="field">
                                        <label>Keamanan Berkendara</label>
                                        <x-star-input name="rating_safety" required />
                                        @error('rating_safety')<span class="field-error">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="comment">Komentar (opsional)</label>
                                    <textarea class="input" id="comment" name="comment" rows="3" maxlength="500"></textarea>
                                    @error('comment')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                                <button type="submit" class="btn btn-primary"><x-icon name="star" /> Kirim Ulasan Driver</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <div class="panel reveal" style="margin-bottom:20px">
                <div class="panel-head"><h2>Ulasan untuk {{ $booking->tenant?->name ?? 'Kami' }}</h2></div>
                <div class="panel-body">
                    @if ($businessReview)
                        <p style="color:var(--graphite)">
                            @if (! $businessReview->is_published)
                                Ulasan Anda sedang ditinjau tim kami. Terima kasih!
                            @else
                                Terima kasih atas ulasan Anda.
                            @endif
                        </p>
                    @else
                        <p style="margin-bottom:14px;color:var(--graphite)">Ceritakan pengalaman sewa Anda secara keseluruhan.</p>
                        <form method="POST" action="{{ route('testimonial.store', $booking->booking_code) }}">
                            @csrf
                            <div class="field">
                                <label>Rating</label>
                                <x-star-input name="rating" required />
                                @error('rating')<span class="field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="quote">Ulasan Anda</label>
                                <textarea class="input" id="quote" name="quote" rows="3" maxlength="2000" required></textarea>
                                @error('quote')<span class="field-error">{{ $message }}</span>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary"><x-icon name="star" /> Kirim Ulasan</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

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
