# Share Trip ke Keluarga Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Customer bisa membagikan link pantau perjalanan ke keluarga; link membuka "family view" read-only ramping (peta live + status + ETA + mobil/plat/driver) TANPA harga.

**Architecture:** Route publik baru `/pantau/{code}` (reuse `booking_code`, nol migrasi) merender view stripped. Peta live memakai `tracking-demo.js` (dibuat di fitur #1) saat `TRACKING_DEMO`. Tombol share di `/lacak` pakai Web Share API + fallback wa.me.

**Tech Stack:** Laravel 12, Blade, Leaflet demo engine, PHPUnit.

## Global Constraints

- Reuse `booking_code`; nol migrasi. Route publik, tenant-scoped via global scope (default tenant).
- Family view TIDAK menampilkan harga/total/email — hanya nama depan.
- Peta demo digerbangi `TRACKING_DEMO`; reuse `window.TrackingDemo.trip`.
- Push Leaflet CSS ke stack `head`, script ke stack `scripts`.

---

### Task 1: Route + method `watch` + family view

**Files:**
- Modify: `app/Http/Controllers/TrackingController.php`
- Modify: `routes/web.php` (blok order tracking)
- Create: `resources/views/tracking/watch.blade.php`
- Test: `tests/Feature/ShareTripTest.php` (create)

**Interfaces:**
- Produces: `GET /pantau/{bookingCode}` (name `tracking.watch`) → view `tracking.watch` dengan `booking` + `demo`. Kode tak dikenal → redirect ke `tracking.search` (mirror `show()`).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareTripTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function booking(array $o = []): Booking
    {
        $car = Car::create([
            'name' => 'Avanza', 'plate_number' => 'KT 1 AB', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 7,
            'price_per_day' => 300000, 'is_available' => true,
        ]);
        return Booking::create(array_merge([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi Santoso', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 2,
            'price_per_day' => 300000, 'total_price' => 654321, 'status' => 'confirmed',
            'trip_status' => Booking::TRIP_ON_THE_WAY, 'booking_code' => Booking::generateBookingCode(),
        ], $o));
    }

    public function test_watch_shows_status_without_price(): void
    {
        $b = $this->booking();
        $res = $this->get('/pantau/'.$b->booking_code);
        $res->assertOk();
        $res->assertSee('Budi');                 // first name / warm title
        $res->assertSee($b->trip_status_label);  // status
        $res->assertSee('Avanza');               // car
        $res->assertDontSee('654.321');          // no price
        $res->assertDontSee('Total');            // no financial label
    }

    public function test_watch_unknown_code_redirects(): void
    {
        $this->get('/pantau/LJR-NOPE00')->assertRedirect(route('tracking.search'));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=ShareTripTest`
Expected: FAIL (route belum ada).

- [ ] **Step 3: Tambah route** (di `routes/web.php`, blok order tracking, setelah route `tracking.show`):

```php
Route::get('/pantau/{bookingCode}', [PublicTrackingController::class, 'watch'])->name('tracking.watch');
```

- [ ] **Step 4: Tambah method `watch`** di `app/Http/Controllers/TrackingController.php` (setelah `show()`):

```php
    public function watch(string $bookingCode): View|RedirectResponse
    {
        $booking = Booking::query()
            ->with('car.latestPosition', 'driver')
            ->where('booking_code', strtoupper($bookingCode))
            ->first();

        if ($booking === null) {
            return redirect()
                ->route('tracking.search')
                ->with('tracking_error', 'Kode booking tidak ditemukan. Coba cek kembali kodenya.');
        }

        return view('tracking.watch', [
            'booking' => $booking,
            'demo' => (bool) config('services.tracking.demo'),
        ]);
    }
```

- [ ] **Step 5: Buat view `resources/views/tracking/watch.blade.php`**

```blade
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
                <div class="detail-item"><div class="k">Pengemudi</div><div class="v">{{ $booking->driver?->name ?? 'Belum ditentukan' }}</div></div>
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
```

- [ ] **Step 6: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=ShareTripTest`
Expected: PASS (2 test).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/TrackingController.php routes/web.php resources/views/tracking/watch.blade.php tests/Feature/ShareTripTest.php
git commit -m "feat: family view /pantau/{code} (live map + status, no price)"
```

---

### Task 2: Tombol "Bagikan ke keluarga" di `/lacak`

**Files:**
- Modify: `resources/views/tracking/show.blade.php`
- Test: `tests/Feature/ShareTripTest.php` (tambah metode)

**Interfaces:**
- Consumes: route `tracking.watch`.
- Produces: tombol share (Web Share API + fallback wa.me) yang menyalin/kirim link `/pantau/{code}`.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
    public function test_lacak_has_share_button(): void
    {
        $b = $this->booking();
        $res = $this->get('/lacak/'.$b->booking_code);
        $res->assertOk();
        $res->assertSee('Bagikan ke keluarga');
        $res->assertSee('/pantau/'.$b->booking_code, false);
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter="ShareTripTest::test_lacak_has_share_button"`
Expected: FAIL.

- [ ] **Step 3: Tambah tombol share di `tracking/show.blade.php`**

Di blok "Bantuan" (dekat tombol CS), tambahkan di atas tombol CS:

```blade
        <div style="text-align:center;margin-bottom:10px">
            <button type="button" class="btn btn-primary" data-share
                data-url="{{ route('tracking.watch', $booking->booking_code) }}"
                data-text="Pantau perjalanan saya ({{ $booking->car_name }}) secara langsung:">
                <x-icon name="pin" /> Bagikan ke keluarga
            </button>
        </div>
```

Dan di akhir file (setelah blok `@endif` demo yang sudah ada, atau setelah `@endsection` jika belum ada push), tambahkan script share:

```blade
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
```

CATATAN: pastikan `layouts.public` punya `@stack('scripts')` (sudah, dari fitur #1). Boleh ada dua blok `@push('scripts')` di satu view — Laravel menggabungnya.

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=ShareTripTest`
Expected: PASS (3 test).

- [ ] **Step 5: Verifikasi manual**

`TRACKING_DEMO=true`, buka `/lacak/{code}` → klik "Bagikan ke keluarga" (di desktop membuka wa.me; di HP memunculkan share sheet). Buka `/pantau/{code}` → family view tampil: peta demo mobil bergerak + ETA turun, status, mobil/plat/driver, TANPA harga.

- [ ] **Step 6: Commit**

```bash
git add resources/views/tracking/show.blade.php tests/Feature/ShareTripTest.php
git commit -m "feat: share-to-family button on /lacak (Web Share + wa.me fallback)"
```

---

### Task 3: Regresi

- [ ] **Step 1:** Run: `php artisan test` — Expected: semua hijau (59 + 3 = 62).
- [ ] **Step 2:** (jika merah) perbaiki & commit.
