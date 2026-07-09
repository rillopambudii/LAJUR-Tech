# Demo Tracking + ETA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tampilkan tracking peta + ETA yang meyakinkan di `/admin/tracking` dan `/lacak/{code}` menggunakan simulasi client-side deterministik (Leaflet + rute precomputed), tanpa Maps API berbayar atau Traccar.

**Architecture:** Simulasi 100% di browser, terisolasi dari pipeline produksi. Digerbangi flag `TRACKING_DEMO`. Saat demo aktif, halaman memuat Leaflet (di-vendor lokal) + `tracking-demo.js` yang menganimasikan marker menyusuri polyline dari `demo-routes.json`, dengan ETA yang dihitung dari sisa jarak rute. Saat demo mati, halaman tetap seperti sekarang (Google Maps di admin; slot kosong di customer).

**Tech Stack:** Laravel 12 (Blade), vanilla JS, Leaflet + OpenStreetMap tiles, PHPUnit (sqlite) untuk bagian server-rendered.

## Global Constraints

- Peta demo pakai **Leaflet + OSM**, bukan Google Maps. Google Maps hanya jalur non-demo.
- **Nol tulis DB, nol migrasi, nol panggilan eksternal saat runtime demo** (OSRM hanya dipakai sekali saat generate rute, offline).
- Demo digerbangi config `services.tracking.demo` (`TRACKING_DEMO` di `.env`) yang **sudah ada** — jangan bikin flag baru.
- GPS asli tetap per-mobil di `vehicle_positions`; kode demo ini tidak menyentuhnya.
- Leaflet di-vendor ke `public/vendor/leaflet/` (jangan andalkan CDN library) agar demo tak gagal karena CDN down. Tiles OSM tetap butuh internet.
- Ikuti pola JS vanilla yang ada di `resources/views/admin/tracking.blade.php` (config via `window.*`, `@push('scripts')`).

---

### Task 1: Vendor Leaflet lokal

**Files:**
- Create: `public/vendor/leaflet/leaflet.js`
- Create: `public/vendor/leaflet/leaflet.css`
- Create: `public/vendor/leaflet/images/marker-icon.png`, `marker-icon-2x.png`, `marker-shadow.png`

**Interfaces:**
- Produces: aset Leaflet 1.9.x yang bisa dimuat via `<link>`/`<script>` dari path `/vendor/leaflet/...`. Global `L` tersedia setelah script dimuat.

- [ ] **Step 1: Unduh Leaflet 1.9.4 ke folder vendor**

```bash
cd "D:/fix project/Travel/Travel"
mkdir -p public/vendor/leaflet/images
curl -L -o public/vendor/leaflet/leaflet.js  https://unpkg.com/leaflet@1.9.4/dist/leaflet.js
curl -L -o public/vendor/leaflet/leaflet.css https://unpkg.com/leaflet@1.9.4/dist/leaflet.css
curl -L -o public/vendor/leaflet/images/marker-icon.png    https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png
curl -L -o public/vendor/leaflet/images/marker-icon-2x.png https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png
curl -L -o public/vendor/leaflet/images/marker-shadow.png  https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png
```

- [ ] **Step 2: Verifikasi file terunduh & valid**

Run: `ls -la public/vendor/leaflet public/vendor/leaflet/images && head -c 40 public/vendor/leaflet/leaflet.js`
Expected: 3 file di root + 3 PNG di images; `leaflet.js` diawali komentar/banner Leaflet (mis. berisi kata `Leaflet`).

- [ ] **Step 3: Commit**

```bash
git add public/vendor/leaflet
git commit -m "chore: vendor Leaflet 1.9.4 for demo tracking map"
```

---

### Task 2: Rute demo (precomputed) sebagai aset JSON

**Files:**
- Create: `public/js/demo-routes.json`

**Interfaces:**
- Produces: JSON `{ "center": [lat,lng], "destination": [lat,lng], "routes": [ { "id": <int>, "name": <string>, "plate": <string>, "points": [[lat,lng], ...] } ] }`. `points` ≥ 2 dan berurutan sepanjang jalan. Rute pertama (`id:1`) dipakai skenario customer dan berakhir di `destination`.

- [ ] **Step 1: Buat file rute demo Samarinda**

Tulis `public/js/demo-routes.json` dengan isi berikut (koordinat menyusuri jalan utama Samarinda; boleh diperhalus nanti via OSRM — lihat Step 2):

```json
{
  "center": [-0.502106, 117.153709],
  "destination": [-0.477500, 117.148000],
  "routes": [
    { "id": 1, "name": "Avanza — B 1234 CD", "plate": "KT 1234 AB",
      "points": [[-0.531000,117.139000],[-0.525000,117.141500],[-0.518000,117.143800],[-0.510000,117.145200],[-0.502000,117.146500],[-0.494000,117.147200],[-0.486000,117.147700],[-0.477500,117.148000]] },
    { "id": 2, "name": "Innova — B 5678 EF", "plate": "KT 5678 CD",
      "points": [[-0.495000,117.120000],[-0.497000,117.128000],[-0.499000,117.136000],[-0.501000,117.144000],[-0.503000,117.152000],[-0.505000,117.160000],[-0.503000,117.152000],[-0.501000,117.144000]] },
    { "id": 3, "name": "Xenia — B 9012 GH", "plate": "KT 9012 EF",
      "points": [[-0.520000,117.165000],[-0.514000,117.160000],[-0.508000,117.156000],[-0.502000,117.153000],[-0.496000,117.150000],[-0.502000,117.153000],[-0.508000,117.156000],[-0.514000,117.160000]] },
    { "id": 4, "name": "Ertiga — B 3456 IJ", "plate": "KT 3456 GH",
      "points": [[-0.480000,117.130000],[-0.486000,117.135000],[-0.492000,117.140000],[-0.498000,117.145000],[-0.492000,117.140000],[-0.486000,117.135000]] }
  ]
}
```

- [ ] **Step 2 (opsional, akurasi jalan): regenerate `points` via OSRM sekali**

Jika ingin polyline yang benar-benar mengikuti jalan, ambil sekali dari OSRM publik (butuh internet, dilakukan saat ngoding — bukan saat demo). Contoh untuk rute 1 (OSRM pakai urutan lng,lat):

```bash
curl -s "https://router.project-osrm.org/route/v1/driving/117.139000,-0.531000;117.148000,-0.477500?overview=full&geometries=geojson" \
  | python -c "import sys,json;print(json.load(sys.stdin)['routes'][0]['geometry']['coordinates'])"
```

Ambil koordinat hasilnya, balik jadi `[lat,lng]`, tempel ke `points` rute 1. (Langkah ini opsional — koordinat Step 1 sudah cukup untuk demo.)

- [ ] **Step 3: Verifikasi JSON valid**

Run: `python -c "import json;d=json.load(open('public/js/demo-routes.json'));print(len(d['routes']),'routes; route1 pts',len(d['routes'][0]['points']))"`
Expected: `4 routes; route1 pts 8`

- [ ] **Step 4: Commit**

```bash
git add public/js/demo-routes.json
git commit -m "feat: add precomputed Samarinda demo routes for tracking demo"
```

---

### Task 3: Engine simulasi client-side `tracking-demo.js`

**Files:**
- Create: `public/js/tracking-demo.js`

**Interfaces:**
- Consumes: global `L` (Leaflet), `demo-routes.json` via fetch.
- Produces: global `window.TrackingDemo` dengan dua entry point:
  - `TrackingDemo.fleet(el, opts)` — render banyak mobil bergerak (loop) di elemen peta `el`. `opts.routesUrl` (string), `opts.onUnits(list)` optional callback tiap tick dengan `[{id,name,plate,speed}]`.
  - `TrackingDemo.trip(el, opts)` — render satu mobil (rute `id:1`) mendekati `destination`; `opts.onEta({minutes, progress, arrived})` dipanggil tiap tick (`progress` 0–100). Berhenti di ujung dengan `arrived:true`.
  - Keduanya mengembalikan `{ stop() }`.
- Konstanta internal: kecepatan asумsi `SPEED_KMH = 30`, langkah `STEP_METERS = 60` per tick, tick `TICK_MS = 1000`. Haversine untuk jarak.

- [ ] **Step 1: Tulis `public/js/tracking-demo.js`**

```js
/* Demo tracking simulator (client-side only). Requires Leaflet global `L`.
   No network except OSM tiles + fetching the local routes JSON. */
(function () {
  var SPEED_KMH = 30, STEP_METERS = 60, TICK_MS = 1000;

  function haversine(a, b) { // [lat,lng] -> meters
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
  function baseMap(el, center) {
    var map = L.map(el, { zoomControl: true, attributionControl: true }).setView(center, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '© OpenStreetMap'
    }).addTo(map);
    return map;
  }
  // A mover walks a polyline `pts` in STEP_METERS increments; loops if `loop`.
  function mover(pts, loop) {
    var i = 0, frac = 0; // between pts[i] and pts[i+1]
    var segLen = function (k) { return haversine(pts[k], pts[k + 1]); };
    return {
      done: false,
      pos: function () {
        var a = pts[i], b = pts[i + 1] || pts[i];
        return [a[0] + (b[0] - a[0]) * frac, a[1] + (b[1] - a[1]) * frac];
      },
      remainingMeters: function () {
        var m = segLen(i) * (1 - frac);
        for (var k = i + 1; k < pts.length - 1; k++) m += segLen(k);
        return m;
      },
      progress: function () {
        var total = 0, passed = 0;
        for (var k = 0; k < pts.length - 1; k++) { var L2 = segLen(k); total += L2; if (k < i) passed += L2; else if (k === i) passed += L2 * frac; }
        return total ? Math.min(100, Math.round(passed / total * 100)) : 100;
      },
      step: function () {
        if (i >= pts.length - 1) { if (loop) { i = 0; frac = 0; } else { this.done = true; } return; }
        var need = STEP_METERS, len = segLen(i) || 0.0001;
        frac += need / len;
        while (frac >= 1 && i < pts.length - 1) { frac -= 1; i++; if (i < pts.length - 1) { len = segLen(i) || 0.0001; } }
        if (i >= pts.length - 1) { frac = 0; if (!loop) this.done = true; }
      }
    };
  }
  function etaMinutes(meters) { return Math.max(0, Math.round(meters / 1000 / SPEED_KMH * 60)); }

  function load(url) { return fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }); }

  window.TrackingDemo = {
    fleet: function (el, opts) {
      var timer, map, markers = {};
      load(opts.routesUrl).then(function (data) {
        map = baseMap(el, data.center);
        var movers = data.routes.map(function (r) { return { r: r, m: mover(r.points, true) }; });
        movers.forEach(function (o) { markers[o.r.id] = L.marker(o.m.pos(), { icon: icon() }).addTo(map).bindPopup(o.r.name + '<br>' + o.r.plate); });
        map.setView(data.center, 12);
        timer = setInterval(function () {
          var units = movers.map(function (o) {
            o.m.step(); var p = o.m.pos(); markers[o.r.id].setLatLng(p);
            return { id: o.r.id, name: o.r.name, plate: o.r.plate, speed: SPEED_KMH };
          });
          if (opts.onUnits) opts.onUnits(units);
        }, TICK_MS);
      });
      return { stop: function () { clearInterval(timer); } };
    },
    trip: function (el, opts) {
      var timer, map;
      load(opts.routesUrl).then(function (data) {
        map = baseMap(el, data.center);
        var r = data.routes[0], m = mover(r.points, false);
        var marker = L.marker(m.pos(), { icon: icon() }).addTo(map).bindPopup(r.name);
        var dest = L.circleMarker(data.destination, { radius: 8, color: '#E7B24C', fillOpacity: 0.9 }).addTo(map).bindPopup('Tujuan kamu');
        L.polyline(r.points, { color: '#0f1b33', weight: 3, opacity: 0.25 }).addTo(map);
        map.fitBounds(L.latLngBounds(r.points));
        timer = setInterval(function () {
          m.step(); marker.setLatLng(m.pos());
          var arrived = m.done, mins = etaMinutes(m.remainingMeters());
          if (opts.onEta) opts.onEta({ minutes: mins, progress: arrived ? 100 : m.progress(), arrived: arrived });
          if (arrived) clearInterval(timer);
        }, TICK_MS);
      });
      return { stop: function () { clearInterval(timer); } };
    }
  };
})();
```

- [ ] **Step 2: Smoke-check sintaks JS**

Run: `node --check public/js/tracking-demo.js`
Expected: tanpa output (exit 0) — sintaks valid.

- [ ] **Step 3: Commit**

```bash
git add public/js/tracking-demo.js
git commit -m "feat: client-side demo tracking engine (fleet + trip + ETA)"
```

---

### Task 4: Admin `/admin/tracking` — pakai Leaflet demo saat TRACKING_DEMO

**Files:**
- Modify: `resources/views/admin/tracking.blade.php`
- Test: `tests/Feature/TrackingDemoViewTest.php` (create)

**Interfaces:**
- Consumes: `$demo` (bool, sudah dikirim `Admin\TrackingController@index`), `window.TrackingDemo.fleet`.
- Produces: saat `$demo` true, halaman memuat Leaflet + `tracking-demo.js` dan memanggil `TrackingDemo.fleet('#track-map', {...})`, TIDAK memuat Google Maps.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TrackingDemoViewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $tenant = Tenant::factory()->create();
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    }

    public function test_admin_tracking_uses_leaflet_demo_when_demo_on(): void
    {
        config(['services.tracking.demo' => true, 'services.google.maps_key' => null]);
        $res = $this->actingAs($this->admin())->get('/admin/tracking');
        $res->assertOk();
        $res->assertSee('tracking-demo.js', false);
        $res->assertSee('/vendor/leaflet/leaflet.js', false);
        $res->assertDontSee('maps.googleapis.com', false);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter=TrackingDemoViewTest::test_admin_tracking_uses_leaflet_demo_when_demo_on`
Expected: FAIL (halaman belum memuat Leaflet; saat ini butuh maps_key & pakai Google).

- [ ] **Step 3: Ubah blade agar mendukung jalur demo Leaflet**

Ganti guard `@if (! $mapsKey)` di bagian atas agar demo tidak butuh Google key, dan tambah blok script demo. Struktur baru `resources/views/admin/tracking.blade.php`:

```blade
@extends('layouts.admin')

@section('title', 'Pelacakan Unit')
@section('crumb', 'Armada')
@section('heading', 'Pelacakan Unit')

@section('content')
@if (! $mapsKey && ! $demo)
    <div class="alert alert-error" role="alert">
        <x-icon name="alert" />
        <span>Peta belum aktif. Setel <code>GOOGLE_MAPS_API_KEY</code> di <code>.env</code>, atau nyalakan <code>TRACKING_DEMO=true</code> untuk mode demo.</span>
    </div>
@else
    <div class="track-wrap">
        <aside class="track-panel">
            <div class="track-mode">
                <x-icon name="pin" /> <span>Unit Live</span>
                @if ($demo)<span class="track-demo">Mode Demo</span>@endif
            </div>
            <div class="track-units" data-units><p class="track-empty">Memuat…</p></div>
        </aside>
        <div class="track-map" id="track-map"></div>
    </div>
@endif
@endsection

@if ($demo)
@push('styles')<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">@endpush
@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/tracking-demo.js') }}"></script>
<script>
  window.TrackingDemo.fleet('track-map', {
    routesUrl: @json(asset('js/demo-routes.json')),
    onUnits: function (units) {
      var list = document.querySelector('[data-units]');
      list.innerHTML = units.map(function (u) {
        return '<button type="button" class="track-unit"><span class="dot moving"></span>'
          + u.name + '<small>' + u.speed + ' km/j</small></button>';
      }).join('');
    }
  });
</script>
@endpush
@elseif ($mapsKey)
@push('scripts')
{{-- (blok Google Maps yang lama TETAP di sini, tidak diubah) --}}
@endpush
@endif
```

CATATAN implementer: pindahkan seluruh blok `<script>…initTrackingMap…</script>` + `<script async src="…googleapis…">` yang lama ke dalam cabang `@elseif ($mapsKey)` persis apa adanya (jangan diubah isinya). Pastikan `layouts.admin` punya `@stack('styles')` di `<head>`; jika belum, tambahkan.

- [ ] **Step 4: Pastikan `@stack('styles')` ada di layout**

Run: `grep -n "@stack('styles')\|@stack('scripts')" resources/views/layouts/admin.blade.php`
Expected: `@stack('scripts')` ada. Jika `@stack('styles')` belum ada, tambahkan satu baris `@stack('styles')` sebelum `</head>` di `resources/views/layouts/admin.blade.php`.

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=TrackingDemoViewTest::test_admin_tracking_uses_leaflet_demo_when_demo_on`
Expected: PASS.

- [ ] **Step 6: Verifikasi manual di browser**

Nyalakan `TRACKING_DEMO=true`, `php artisan config:clear`, buka `/admin/tracking`. Expected: peta OSM tampil, ~4 marker bergerak, panel kiri me-list unit. (Butuh internet untuk tiles.)

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/tracking.blade.php resources/views/layouts/admin.blade.php tests/Feature/TrackingDemoViewTest.php
git commit -m "feat: Leaflet demo fleet map on /admin/tracking when TRACKING_DEMO"
```

---

### Task 5: Customer `/lacak/{code}` — mobil mendekat + ETA countdown

**Files:**
- Modify: `resources/views/tracking/show.blade.php`
- Modify: `app/Http/Controllers/TrackingController.php:17-33` (kirim flag `demo` ke view)
- Test: `tests/Feature/TrackingDemoViewTest.php` (tambah metode)

**Interfaces:**
- Consumes: `window.TrackingDemo.trip`, view var baru `$demo` (bool).
- Produces: saat `$demo` true, slot `#tracking-map` diisi Leaflet trip + kartu ETA (`[data-eta]`) yang hitung mundur + progress bar sinkron marker.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/TrackingDemoViewTest.php`:

```php
    public function test_customer_lacak_shows_demo_map_and_eta_when_demo_on(): void
    {
        config(['services.tracking.demo' => true]);
        $tenant = Tenant::factory()->create();
        $booking = \App\Models\Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_code' => 'LJR-TEST99',
        ]);
        $res = $this->get('/lacak/'.$booking->booking_code);
        $res->assertOk();
        $res->assertSee('tracking-demo.js', false);
        $res->assertSee('data-eta', false);
    }
```

(Jika `Booking` belum punya factory, buat minimal `database/factories/BookingFactory.php` dengan field wajib: tenant_id, car_name, customer_name, customer_email, customer_phone, start_date, end_date, days, price_per_day, total_price, status, trip_status, booking_code.)

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter=TrackingDemoViewTest::test_customer_lacak_shows_demo_map_and_eta_when_demo_on`
Expected: FAIL (view belum memuat demo script / `data-eta`).

- [ ] **Step 3: Kirim flag `demo` dari controller**

Di `app/Http/Controllers/TrackingController.php`, method `show()`, ubah return view menambah `'demo'`:

```php
        return view('tracking.show', [
            'booking' => $booking,
            'demo' => (bool) config('services.tracking.demo'),
        ]);
```

- [ ] **Step 4: Tambah blok demo di `tracking/show.blade.php`**

Ganti blok slot peta (baris ~68-87, `{{-- Slot peta --}}`) agar saat `$demo` true render container demo + kartu ETA:

```blade
        {{-- ===== Slot peta ===== --}}
        <div class="panel reveal" style="margin-bottom:20px">
            <div class="panel-body">
                @if ($demo)
                    <div data-eta style="text-align:center;margin-bottom:12px;font-weight:600;color:var(--petrol)">
                        <x-icon name="clock" style="width:16px;height:16px;vertical-align:-2px" />
                        Estimasi tiba: <span data-eta-min>—</span> menit
                    </div>
                    <div id="tracking-map" style="height:280px;border-radius:var(--radius);overflow:hidden;background:var(--ivory-200)"></div>
                @elseif ($booking->has_live_gps)
                    {{-- (blok has_live_gps yang lama tetap di sini) --}}
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
```

Lalu tambahkan sebelum `@endsection` (atau di push scripts jika `layouts.public` mendukung `@stack`):

```blade
@if ($demo)
@push('styles')<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">@endpush
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
```

CATATAN implementer: cek `resources/views/layouts/public.blade.php` punya `@stack('scripts')` dan `@stack('styles')`. Jika belum, tambahkan (`@stack('styles')` sebelum `</head>`, `@stack('scripts')` sebelum `</body>`).

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=TrackingDemoViewTest`
Expected: PASS (kedua test).

- [ ] **Step 6: Verifikasi manual**

`TRACKING_DEMO=true`, buka `/lacak/{kode booking manapun}`. Expected: peta OSM, satu marker mobil bergerak menuju titik tujuan (lingkaran amber), angka ETA turun tiap detik, berhenti "Tiba".

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/TrackingController.php resources/views/tracking/show.blade.php resources/views/layouts/public.blade.php tests/Feature/TrackingDemoViewTest.php database/factories/BookingFactory.php
git commit -m "feat: customer demo trip map + ETA countdown on /lacak when TRACKING_DEMO"
```

---

### Task 6: Regression + dokumentasi flag

**Files:**
- Modify: `docs/TRACCAR-SETUP.md` (atau `.env.example` jika ada) — dokumentasikan `TRACKING_DEMO`.

- [ ] **Step 1: Jalankan seluruh test suite**

Run: `php artisan test`
Expected: semua hijau (suite lama 52 + test baru). Jika ada yang merah karena `services.tracking.demo` bocor antar-test, pastikan test demo memakai `config([...])` per-test (sudah), dan tidak mengubah `.env`.

- [ ] **Step 2: Catat flag di dokumentasi**

Tambahkan satu paragraf di `docs/TRACCAR-SETUP.md`: `TRACKING_DEMO=true` mengaktifkan simulasi peta client-side (Leaflet + rute precomputed) di `/admin/tracking` dan `/lacak/{code}` tanpa Google Maps / Traccar; setel `false` untuk produksi.

- [ ] **Step 3: Commit**

```bash
git add docs/TRACCAR-SETUP.md
git commit -m "docs: document TRACKING_DEMO client-side demo mode"
```

---

## Catatan verifikasi manual (karena animasi JS tak punya harness test)
Bagian server-rendered (blade memuat aset yang benar, gating config) ditutup PHPUnit. Gerakan marker + countdown ETA diverifikasi manual di browser (Task 4 Step 6, Task 5 Step 6) — ini keterbatasan yang diterima untuk fitur demo client-side.
