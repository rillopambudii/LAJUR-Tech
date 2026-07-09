# Trip Replay (Admin) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Admin bisa memutar ulang rute yang ditempuh mobil selama satu booking di halaman detail booking, dengan kontrol play/pause + scrubber + ringkasan.

**Architecture:** Endpoint JSON baru mengembalikan titik GPS mobil booking dalam jendela masa sewa (reuse pola `TrackingController@history`), difabrikasi deterministik saat `TRACKING_DEMO` & data kosong. Frontend `booking-replay.js` (Leaflet yang sudah di-vendor) menganimasikan marker.

**Tech Stack:** Laravel 12, vanilla JS, Leaflet (`public/vendor/leaflet/`), PHPUnit.

## Global Constraints

- Peta pakai Leaflet (sudah di-vendor di `public/vendor/leaflet/`).
- Nol migrasi. GPS dibaca dari `vehicle_positions` via `$booking->car->positions()`.
- Tenant isolation lewat implicit route-model binding (`Booking $booking`) — cross-tenant otomatis 404.
- Jendela = `start_date 00:00` s/d `end_date 23:59`.
- Fabrikasi demo deterministik (seed dari `booking->id`) — sama tiap diputar; tak pernah ditulis DB.
- Push Leaflet CSS ke stack `head`, script ke stack `scripts` (pola layout yang ada).

---

### Task 1: Endpoint replay + fabrikasi demo

**Files:**
- Modify: `app/Http/Controllers/Admin/BookingController.php`
- Modify: `routes/web.php` (grup admin, dekat route bookings)
- Test: `tests/Feature/TripReplayTest.php` (create)

**Interfaces:**
- Produces: `GET /admin/bookings/{booking}/replay` (name `admin.bookings.replay`) → JSON `{ "car": <string>, "points": [ { "lat":float,"lng":float,"speed":int,"time":iso8601 }, ... ] }`, titik urut waktu.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripReplayTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function owner(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    private function car(): Car
    {
        return Car::create([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ]);
    }

    private function booking(Car $car, array $o = []): Booking
    {
        return Booking::create(array_merge([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 2,
            'price_per_day' => 300000, 'total_price' => 600000, 'status' => 'confirmed',
            'trip_status' => Booking::TRIP_COMPLETED, 'booking_code' => Booking::generateBookingCode(),
        ], $o));
    }

    public function test_replay_returns_points_in_window(): void
    {
        config()->set('services.tracking.demo', false);
        $car = $this->car();
        $b = $this->booking($car);
        VehiclePosition::create(['car_id' => $car->id, 'latitude' => -0.5, 'longitude' => 117.15, 'speed' => 20, 'course' => 90, 'device_time' => '2026-08-11 08:00:00']);
        VehiclePosition::create(['car_id' => $car->id, 'latitude' => -0.49, 'longitude' => 117.16, 'speed' => 30, 'course' => 90, 'device_time' => '2026-08-11 09:00:00']);
        // Outside the rental window — must be excluded.
        VehiclePosition::create(['car_id' => $car->id, 'latitude' => 0.0, 'longitude' => 100.0, 'speed' => 0, 'course' => 0, 'device_time' => '2026-09-01 09:00:00']);

        $res = $this->actingAs($this->owner())->getJson("/admin/bookings/{$b->id}/replay");
        $res->assertOk();
        $this->assertCount(2, $res->json('points'));
        $this->assertSame(-0.5, $res->json('points.0.lat'));
    }

    public function test_replay_fabricates_when_demo_and_empty(): void
    {
        config()->set('services.tracking.demo', true);
        $b = $this->booking($this->car());

        $res = $this->actingAs($this->owner())->getJson("/admin/bookings/{$b->id}/replay");
        $res->assertOk();
        $this->assertNotEmpty($res->json('points'));
        // Deterministic: same booking → same first point on a second call.
        $res2 = $this->actingAs($this->owner())->getJson("/admin/bookings/{$b->id}/replay");
        $this->assertSame($res->json('points.0'), $res2->json('points.0'));
    }

    public function test_replay_empty_when_demo_off_and_no_data(): void
    {
        config()->set('services.tracking.demo', false);
        $b = $this->booking($this->car());

        $res = $this->actingAs($this->owner())->getJson("/admin/bookings/{$b->id}/replay");
        $res->assertOk();
        $this->assertSame([], $res->json('points'));
    }

    public function test_replay_cross_tenant_404(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        app(TenantManager::class)->set($other);
        $otherCar = $this->car();
        $otherBooking = $this->booking($otherCar);
        app(TenantManager::class)->set($this->tenant);

        $this->actingAs($this->owner())->getJson("/admin/bookings/{$otherBooking->id}/replay")
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=TripReplayTest`
Expected: FAIL (route/method belum ada → 404 pada test pertama).

- [ ] **Step 3: Tambah route**

Di `routes/web.php`, dalam grup admin, tepat setelah baris route `bookings/{booking}/trip-status`, tambahkan:

```php
        Route::get('bookings/{booking}/replay', [AdminBookingController::class, 'replay'])->name('bookings.replay');
```

- [ ] **Step 4: Tambah method `replay` + fabrikasi di `Admin\BookingController`**

Tambahkan `use Illuminate\Http\JsonResponse;` dan `use Illuminate\Support\Collection;` di atas, lalu tambahkan method:

```php
    /** GPS track for a booking's rental window (Trip Replay). */
    public function replay(Booking $booking): JsonResponse
    {
        $from = $booking->start_date->copy()->startOfDay();
        $to = $booking->end_date->copy()->endOfDay();

        $points = collect();
        if ($booking->car) {
            $points = $booking->car->positions()
                ->whereBetween('device_time', [$from, $to])
                ->orderBy('device_time')
                ->get(['latitude', 'longitude', 'speed', 'device_time'])
                ->map(fn ($p) => [
                    'lat' => (float) $p->latitude,
                    'lng' => (float) $p->longitude,
                    'speed' => (int) $p->speed,
                    'time' => optional($p->device_time)->toIso8601String(),
                ]);
        }

        if ($points->isEmpty() && config('services.tracking.demo')) {
            $points = $this->fabricateReplay($booking, $from, $to);
        }

        return response()->json(['car' => $booking->car_name, 'points' => $points->values()]);
    }

    /**
     * Fabricate a deterministic demo route for one booking (seeded by id), with
     * timestamps spread across the rental window. Never persisted.
     */
    private function fabricateReplay(Booking $booking, $from, $to): Collection
    {
        $centerLat = -0.502106;
        $centerLng = 117.153709;
        $n = 40;
        $seed = (int) $booking->id;
        $lat = $centerLat + (($seed % 100) / 100 - 0.5) * 0.05;
        $lng = $centerLng + ((intdiv($seed, 100) % 100) / 100 - 0.5) * 0.05;
        $span = max(1, $to->getTimestamp() - $from->getTimestamp());

        $points = collect();
        for ($i = 0; $i < $n; $i++) {
            $lat += sin($i / 3 + $seed) * 0.0016;
            $lng += cos($i / 4 + $seed) * 0.0016;
            $t = $from->copy()->addSeconds((int) ($span * $i / ($n - 1)));
            $points->push([
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'speed' => 20 + (($seed + $i) % 40),
                'time' => $t->toIso8601String(),
            ]);
        }

        return $points;
    }
```

- [ ] **Step 5: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=TripReplayTest`
Expected: PASS (4 test).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/BookingController.php routes/web.php tests/Feature/TripReplayTest.php
git commit -m "feat: trip replay endpoint for admin bookings (window + demo fabrication)"
```

---

### Task 2: Engine `booking-replay.js`

**Files:**
- Create: `public/js/booking-replay.js`

**Interfaces:**
- Consumes: global `L` (Leaflet).
- Produces: `window.BookingReplay.init(opts)` dengan `opts.mapEl` (id peta), `opts.url` (endpoint replay), `opts.controls` (objek berisi id/selector elemen: `playBtn, speedSel, scrubber, clock, summary`). Ambil data, gambar polyline + marker, dan hubungkan kontrol play/pause/scrubber.

- [ ] **Step 1: Tulis `public/js/booking-replay.js`**

```js
/* Trip Replay engine (client-side). Requires Leaflet global `L`. */
(function () {
  function haversine(a, b) {
    var R = 6371000, toRad = function (d) { return d * Math.PI / 180; };
    var dLat = toRad(b[0] - a[0]), dLng = toRad(b[1] - a[1]);
    var s = Math.sin(dLat / 2) * Math.sin(dLat / 2)
      + Math.cos(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.sqrt(s));
  }
  function icon() {
    return L.icon({
      iconUrl: '/vendor/leaflet/images/marker-icon.png',
      iconRetinaUrl: '/vendor/leaflet/images/marker-icon-2x.png',
      shadowUrl: '/vendor/leaflet/images/marker-shadow.png',
      iconSize: [25, 41], iconAnchor: [12, 41]
    });
  }
  function fmtTime(iso) { try { return new Date(iso).toLocaleString('id-ID'); } catch (e) { return iso || ''; } }

  window.BookingReplay = {
    init: function (opts) {
      var map = L.map(opts.mapEl);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
      var c = opts.controls, playBtn = document.getElementById(c.playBtn), speedSel = document.getElementById(c.speedSel),
          scrubber = document.getElementById(c.scrubber), clock = document.getElementById(c.clock), summary = document.getElementById(c.summary);

      fetch(opts.url, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (data) {
        var pts = data.points || [];
        if (!pts.length) { summary.textContent = 'Tidak ada data GPS untuk perjalanan ini.'; return; }
        var latlngs = pts.map(function (p) { return [p.lat, p.lng]; });
        L.polyline(latlngs, { color: '#0f1b33', weight: 3, opacity: 0.35 }).addTo(map);
        var marker = L.marker(latlngs[0], { icon: icon() }).addTo(map);
        map.fitBounds(L.latLngBounds(latlngs));

        // summary
        var dist = 0; for (var k = 0; k < latlngs.length - 1; k++) dist += haversine(latlngs[k], latlngs[k + 1]);
        var speeds = pts.map(function (p) { return p.speed || 0; });
        var maxS = Math.max.apply(null, speeds), avgS = Math.round(speeds.reduce(function (a, b) { return a + b; }, 0) / speeds.length);
        summary.innerHTML = 'Jarak: <strong>' + (dist / 1000).toFixed(1) + ' km</strong> · '
          + 'Kecepatan maks/rata: <strong>' + maxS + '/' + avgS + ' km/j</strong> · '
          + 'Mulai: ' + fmtTime(pts[0].time) + ' → Selesai: ' + fmtTime(pts[pts.length - 1].time);

        scrubber.max = String(pts.length - 1);
        scrubber.value = '0';
        var idx = 0, playing = false, timer = null, speed = 1;

        function show(i) {
          idx = i; marker.setLatLng(latlngs[i]); scrubber.value = String(i);
          clock.textContent = fmtTime(pts[i].time) + ' · ' + (pts[i].speed || 0) + ' km/j';
        }
        function tick() {
          if (idx >= pts.length - 1) { stop(); return; }
          show(idx + 1);
        }
        function play() { if (playing) return; playing = true; playBtn.textContent = '⏸'; timer = setInterval(tick, 700 / speed); }
        function stop() { playing = false; playBtn.textContent = '▶'; if (timer) { clearInterval(timer); timer = null; } }

        playBtn.addEventListener('click', function () { playing ? stop() : play(); });
        scrubber.addEventListener('input', function () { stop(); show(parseInt(scrubber.value, 10)); });
        speedSel.addEventListener('change', function () { speed = parseFloat(speedSel.value) || 1; if (playing) { stop(); play(); } });
        show(0);
      }).catch(function () { summary.textContent = 'Gagal memuat data replay.'; });
    }
  };
})();
```

- [ ] **Step 2: Smoke-check sintaks**

Run: `node --check public/js/booking-replay.js`
Expected: exit 0.

- [ ] **Step 3: Commit**

```bash
git add public/js/booking-replay.js
git commit -m "feat: client-side trip replay engine (playback + scrubber + summary)"
```

---

### Task 3: Tombol & panel replay di `admin/bookings/show`

**Files:**
- Modify: `resources/views/admin/bookings/show.blade.php`
- Test: `tests/Feature/TripReplayTest.php` (tambah metode)

**Interfaces:**
- Consumes: `window.BookingReplay.init`, route `admin.bookings.replay`.
- Produces: tombol "Replay Perjalanan" + panel peta/kontrol (muncul hanya jika `$booking->car`).

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/TripReplayTest.php`:

```php
    public function test_show_page_has_replay_when_car_present(): void
    {
        $b = $this->booking($this->car());
        $res = $this->actingAs($this->owner())->get("/admin/bookings/{$b->id}");
        $res->assertOk();
        $res->assertSee('booking-replay.js', false);
        $res->assertSee('Replay Perjalanan', false);
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=TripReplayTest::test_show_page_has_replay_when_car_present`
Expected: FAIL.

- [ ] **Step 3: Tambah panel di blade**

Di `resources/views/admin/bookings/show.blade.php`, sebelum `@endsection`, sisipkan panel (di dalam kolom utama; jika ragu, taruh tepat sebelum penutup `</div>\n</div>\n@endsection` sebagai blok mandiri):

```blade
    @if ($booking->car)
    <div class="panel" style="margin-top:20px" data-replay-panel hidden>
        <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center">
            <h2>Replay Perjalanan</h2>
            <div style="display:flex;gap:8px;align-items:center">
                <button type="button" class="btn btn-sm" id="replay-play" aria-label="Play">▶</button>
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
```

Lalu setelah `@endsection` tambahkan:

```blade
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
```

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=TripReplayTest`
Expected: PASS (5 test).

- [ ] **Step 5: Verifikasi manual**

`TRACKING_DEMO=true`, buka `/admin/bookings/{id}` sebuah booking yang punya mobil → klik "Replay Perjalanan" → peta muncul, klik ▶ → marker jalan menyusuri polyline, scrubber & jam bergerak, ringkasan terisi.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/bookings/show.blade.php tests/Feature/TripReplayTest.php
git commit -m "feat: Replay Perjalanan panel on admin booking detail"
```

---

### Task 4: Regresi

- [ ] **Step 1: Jalankan seluruh suite**

Run: `php artisan test`
Expected: semua hijau (54 + 5 baru = 59).

- [ ] **Step 2: (jika ada yang merah) perbaiki, lalu commit**
