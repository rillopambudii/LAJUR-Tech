# Sistem Mileage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Ubah jejak GPS jadi odometer per mobil, prediksi servis berbasis km, dan ringkasan jarak per-booking.

**Architecture:** Tabel agregat harian `car_mileage_daily` diisi `MileageService` yang **recompute penuh & idempoten** dari `vehicle_positions` (filter jitter & teleport). Odometer = baseline manual + jumlah agregat. Servis-km melengkapi pengingat tanggal. Demo lewat seeder.

**Tech Stack:** Laravel 12, PHPUnit.

## Global Constraints

- Recompute penuh tiap sync (idempoten, tak dobel-hitung). `mileage_synced_at` hanya informatif.
- Filter: segmen < 15 m diabaikan (jitter GPS); segmen > 8000 m antar-ping diabaikan (teleport/outlier).
- Odometer absolut = `odometer_baseline_km` + Σ `car_mileage_daily.km`.
- Semua tenant-scoped (BelongsToTenant).
- Servis "soon" = dalam 500 km.

---

### Task 1: Migrasi + model `CarMileageDaily`

**Files:**
- Create: `database/migrations/2026_07_09_000001_add_mileage.php`
- Create: `app/Models/CarMileageDaily.php`

- [ ] **Step 1: Tulis migrasi**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->unsignedInteger('odometer_baseline_km')->default(0)->after('service_due_date');
            $table->timestamp('baseline_at')->nullable()->after('odometer_baseline_km');
            $table->unsignedInteger('service_interval_km')->nullable()->after('baseline_at');
            $table->unsignedInteger('service_last_km')->nullable()->after('service_interval_km');
            $table->timestamp('mileage_synced_at')->nullable()->after('service_last_km');
        });

        Schema::create('car_mileage_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('km')->default(0);
            $table->timestamps();
            $table->unique(['car_id', 'date']);
            $table->index(['tenant_id', 'car_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_mileage_daily');
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['odometer_baseline_km', 'baseline_at', 'service_interval_km', 'service_last_km', 'mileage_synced_at']);
        });
    }
};
```

- [ ] **Step 2: Model `app/Models/CarMileageDaily.php`**

```php
<?php
namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarMileageDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'car_mileage_daily';
    protected $fillable = ['tenant_id', 'car_id', 'date', 'km'];
    protected function casts(): array
    {
        return ['date' => 'date', 'km' => 'integer'];
    }

    /** @return BelongsTo<Car, $this> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
```

- [ ] **Step 3: Migrate & verify**

Run: `php artisan migrate` (dev DB) — Expected: migrated. (Tests use sqlite RefreshDatabase, otomatis.)

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_09_000001_add_mileage.php app/Models/CarMileageDaily.php
git commit -m "feat: mileage schema (car_mileage_daily + odometer/service km cols)"
```

---

### Task 2: `MileageService` (sync GPS → km, idempoten)

**Files:**
- Create: `app/Mileage/MileageService.php`
- Test: `tests/Feature/MileageTest.php` (create)

**Interfaces:**
- Produces: `App\Mileage\MileageService`:
  - `syncCar(Car $car): void` — recompute `car_mileage_daily` untuk mobil dari semua posisinya.
  - `syncAll(): int` — sync semua mobil (tenant aktif), return jumlah mobil.
  - const `MIN_SEGMENT_METERS = 15`, `MAX_SEGMENT_METERS = 8000`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
namespace Tests\Feature;

use App\Mileage\MileageService;
use App\Models\Car;
use App\Models\CarMileageDaily;
use App\Models\Tenant;
use App\Models\VehiclePosition;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MileageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function car(array $o = []): Car
    {
        return Car::create(array_merge([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ], $o));
    }

    private function pos(Car $car, float $lat, float $lng, string $time): void
    {
        VehiclePosition::create(['car_id' => $car->id, 'latitude' => $lat, 'longitude' => $lng, 'speed' => 30, 'course' => 90, 'device_time' => $time]);
    }

    public function test_sync_sums_daily_km_and_filters_noise(): void
    {
        $car = $this->car();
        // ~1.11 km north between two points on 2026-08-11 (0.01 deg lat ~ 1.11 km)
        $this->pos($car, -0.50, 117.15, '2026-08-11 08:00:00');
        $this->pos($car, -0.49, 117.15, '2026-08-11 08:10:00');
        // jitter: ~1 m — excluded
        $this->pos($car, -0.490005, 117.15, '2026-08-11 08:11:00');
        // teleport: > 8 km — excluded
        $this->pos($car, 0.00, 117.15, '2026-08-11 08:20:00');

        (new MileageService())->syncCar($car->fresh());

        $row = CarMileageDaily::where('car_id', $car->id)->where('date', '2026-08-11')->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(1, $row->km, 1); // ~1 km (jitter+teleport excluded)
    }

    public function test_sync_is_idempotent(): void
    {
        $car = $this->car();
        $this->pos($car, -0.50, 117.15, '2026-08-11 08:00:00');
        $this->pos($car, -0.49, 117.15, '2026-08-11 08:10:00');
        $svc = new MileageService();
        $svc->syncCar($car->fresh());
        $svc->syncCar($car->fresh());
        $this->assertSame(1, CarMileageDaily::where('car_id', $car->id)->count());
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter=MileageTest`
Expected: FAIL (class MileageService belum ada).

- [ ] **Step 3: Tulis `app/Mileage/MileageService.php`**

```php
<?php
namespace App\Mileage;

use App\Models\Car;
use App\Models\CarMileageDaily;
use App\Models\VehiclePosition;

class MileageService
{
    public const MIN_SEGMENT_METERS = 15;
    public const MAX_SEGMENT_METERS = 8000;

    /** Recompute daily mileage buckets for one car from all its positions. */
    public function syncCar(Car $car): void
    {
        $positions = $car->positions()
            ->orderBy('device_time')
            ->get(['latitude', 'longitude', 'device_time']);

        $buckets = []; // 'Y-m-d' => meters
        $prev = null;
        foreach ($positions as $p) {
            if ($prev !== null) {
                $d = $this->haversine((float) $prev->latitude, (float) $prev->longitude, (float) $p->latitude, (float) $p->longitude);
                if ($d >= self::MIN_SEGMENT_METERS && $d <= self::MAX_SEGMENT_METERS) {
                    $date = optional($p->device_time)->toDateString();
                    if ($date) {
                        $buckets[$date] = ($buckets[$date] ?? 0) + $d;
                    }
                }
            }
            $prev = $p;
        }

        foreach ($buckets as $date => $meters) {
            CarMileageDaily::updateOrCreate(
                ['car_id' => $car->id, 'date' => $date],
                ['tenant_id' => $car->tenant_id, 'km' => (int) round($meters / 1000)]
            );
        }

        $car->forceFill(['mileage_synced_at' => now()])->save();
    }

    /** Sync all cars of the active tenant. Returns the number synced. */
    public function syncAll(): int
    {
        $cars = Car::query()->get();
        foreach ($cars as $car) {
            $this->syncCar($car);
        }

        return $cars->count();
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(sqrt($a));
    }
}
```

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=MileageTest`
Expected: PASS (2 test).

- [ ] **Step 5: Commit**

```bash
git add app/Mileage/MileageService.php tests/Feature/MileageTest.php
git commit -m "feat: MileageService — idempotent GPS-to-daily-km with noise filtering"
```

---

### Task 3: Helper odometer & servis-km di `Car`

**Files:**
- Modify: `app/Models/Car.php`
- Test: `tests/Feature/MileageTest.php` (tambah metode)

**Interfaces:**
- Produces di `Car`: `odometerKm(): int`, `kmUntilService(): ?int`, `serviceKmStatus(): ?string` (overdue/soon/ok/null), dan `hasDueReminder()` diperluas mencakup km. const `SERVICE_SOON_KM = 500`.

- [ ] **Step 1: Tulis test yang gagal**

```php
    public function test_odometer_and_service_km_status(): void
    {
        $car = $this->car(['odometer_baseline_km' => 100000, 'service_interval_km' => 5000, 'service_last_km' => 104600]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-11', 'km' => 100]);

        $car->refresh();
        $this->assertSame(100100, $car->odometerKm());        // 100000 + 100
        // next service at 104600 + 5000 = 109600; until = 109600 - 100100 = 9500 → ok
        $this->assertSame('ok', $car->serviceKmStatus());

        $car->service_last_km = 95500; $car->save(); // next = 100500; until = 400 → soon
        $this->assertSame('soon', $car->refresh()->serviceKmStatus());
        $this->assertTrue($car->hasDueReminder());
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter="MileageTest::test_odometer_and_service_km_status"`
Expected: FAIL.

- [ ] **Step 3: Tambah ke `app/Models/Car.php`**

Tambahkan konstanta dekat `REMINDER_WINDOW_DAYS`:

```php
    /** Service is "due soon" within this many km of the next service odometer. */
    public const SERVICE_SOON_KM = 500;
```

Tambahkan relasi + method (mis. setelah `positions()`):

```php
    /** @return HasMany<CarMileageDaily, $this> */
    public function mileageDaily(): HasMany
    {
        return $this->hasMany(CarMileageDaily::class);
    }

    /** Absolute odometer = manual baseline + sum of GPS-derived daily km. */
    public function odometerKm(): int
    {
        return (int) $this->odometer_baseline_km + (int) $this->mileageDaily()->sum('km');
    }

    /** Km remaining until the next service (null if service-by-km not configured). */
    public function kmUntilService(): ?int
    {
        if ($this->service_interval_km === null || $this->service_last_km === null) {
            return null;
        }

        return ((int) $this->service_last_km + (int) $this->service_interval_km) - $this->odometerKm();
    }

    /** 'overdue' | 'soon' | 'ok' | null (not configured). */
    public function serviceKmStatus(): ?string
    {
        $until = $this->kmUntilService();
        if ($until === null) {
            return null;
        }

        return $until <= 0 ? 'overdue' : ($until <= self::SERVICE_SOON_KM ? 'soon' : 'ok');
    }
```

Perluas `hasDueReminder()`:

```php
    public function hasDueReminder(): bool
    {
        return in_array($this->taxStatus(), ['overdue', 'soon'], true)
            || in_array($this->serviceStatus(), ['overdue', 'soon'], true)
            || in_array($this->serviceKmStatus(), ['overdue', 'soon'], true);
    }
```

(Pastikan `use Illuminate\Database\Eloquent\Relations\HasMany;` sudah ada — sudah, karena `bookings()` memakainya.)

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter=MileageTest`
Expected: PASS (3 test).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Car.php tests/Feature/MileageTest.php
git commit -m "feat: Car odometer + km-based service status helpers"
```

---

### Task 4: Jarak per-booking + tampil di detail booking & /lacak

**Files:**
- Modify: `app/Models/Booking.php`
- Modify: `resources/views/admin/bookings/show.blade.php`
- Modify: `resources/views/tracking/show.blade.php`
- Test: `tests/Feature/MileageTest.php` (tambah metode)

**Interfaces:**
- Produces: `Booking::distanceKm(): int` — jumlah `car_mileage_daily.km` mobil booking dalam `[start_date, end_date]`. 0 jika tak ada mobil/data.

- [ ] **Step 1: Tulis test yang gagal**

```php
    public function test_booking_distance_km_sums_window(): void
    {
        $car = $this->car();
        $booking = \App\Models\Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 3,
            'price_per_day' => 300000, 'total_price' => 900000, 'status' => 'confirmed',
            'trip_status' => \App\Models\Booking::TRIP_COMPLETED, 'booking_code' => \App\Models\Booking::generateBookingCode(),
        ]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-11', 'km' => 40]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-12', 'km' => 25]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-20', 'km' => 99]); // outside

        $this->assertSame(65, $booking->distanceKm());
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter="MileageTest::test_booking_distance_km_sums_window"`
Expected: FAIL.

- [ ] **Step 3: Tambah `distanceKm` ke `app/Models/Booking.php`**

```php
    /** Total km driven during this booking's rental window (from car_mileage_daily). */
    public function distanceKm(): int
    {
        if ($this->car_id === null) {
            return 0;
        }

        return (int) CarMileageDaily::query()
            ->where('car_id', $this->car_id)
            ->whereBetween('date', [$this->start_date->toDateString(), $this->end_date->toDateString()])
            ->sum('km');
    }
```

(Tambahkan `use App\Models\CarMileageDaily;` bila perlu — model di namespace sama `App\Models`, jadi cukup referensi `CarMileageDaily::`.)

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter="MileageTest::test_booking_distance_km_sums_window"`
Expected: PASS.

- [ ] **Step 5: Tampilkan jarak di detail booking**

Di `resources/views/admin/bookings/show.blade.php`, pada `detail-grid` mobil/booking (cari blok yang menampilkan "Lama Sewa" atau detail booking), tambahkan satu item:

```blade
                    <div class="detail-item"><div class="k">Jarak Tempuh</div><div class="v">{{ $booking->distanceKm() }} km</div></div>
```

(Sisipkan di dalam `detail-grid` yang sudah ada di panel detail booking.)

- [ ] **Step 6: Tampilkan jarak di `/lacak`**

Di `resources/views/tracking/show.blade.php`, dalam `detail-grid` "Detail Pesanan", tambahkan setelah "Lama Sewa":

```blade
                    <div class="detail-item"><div class="k">Jarak Tempuh</div><div class="v">{{ $booking->distanceKm() }} km</div></div>
```

- [ ] **Step 7: Regresi cepat + commit**

Run: `php artisan test --filter=MileageTest`
Expected: PASS (4 test).

```bash
git add app/Models/Booking.php resources/views/admin/bookings/show.blade.php resources/views/tracking/show.blade.php tests/Feature/MileageTest.php
git commit -m "feat: per-booking distance (car_mileage_daily) on booking detail + /lacak"
```

---

### Task 5: Command `mileage:sync` + jadwal harian

**Files:**
- Create: `app/Console/Commands/MileageSync.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/MileageTest.php` (tambah metode)

**Interfaces:**
- Produces: command signature `mileage:sync`.

- [ ] **Step 1: Tulis test yang gagal**

```php
    public function test_mileage_sync_command_runs(): void
    {
        $car = $this->car();
        $this->pos($car, -0.50, 117.15, '2026-08-11 08:00:00');
        $this->pos($car, -0.49, 117.15, '2026-08-11 08:10:00');

        $this->artisan('mileage:sync')->assertExitCode(0);

        $this->assertSame(1, CarMileageDaily::where('car_id', $car->id)->count());
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter="MileageTest::test_mileage_sync_command_runs"`
Expected: FAIL (command belum ada).

- [ ] **Step 3: Buat command `app/Console/Commands/MileageSync.php`**

```php
<?php
namespace App\Console\Commands;

use App\Mileage\MileageService;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

class MileageSync extends Command
{
    protected $signature = 'mileage:sync';
    protected $description = 'Recompute per-car daily mileage from GPS positions (all tenants).';

    public function handle(MileageService $service): int
    {
        $manager = app(TenantManager::class);
        foreach (Tenant::all() as $tenant) {
            $manager->set($tenant);
            $n = $service->syncAll();
            $this->info("Tenant {$tenant->slug}: {$n} mobil disinkron.");
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Daftarkan jadwal harian di `routes/console.php`**

Tambahkan di akhir `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('mileage:sync')->dailyAt('01:00');
```

- [ ] **Step 5: Jalankan, pastikan LULUS**

Run: `php artisan test --filter="MileageTest::test_mileage_sync_command_runs"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/MileageSync.php routes/console.php tests/Feature/MileageTest.php
git commit -m "feat: mileage:sync command + daily schedule"
```

---

### Task 6: Odometer & servis-km di daftar mobil admin

**Files:**
- Modify: `resources/views/admin/cars/index.blade.php`
- Test: `tests/Feature/MileageTest.php` (tambah metode)

**Interfaces:**
- Produces: kolom/badge odometer + "servis dalam X km" di daftar mobil.

- [ ] **Step 1: Tulis test yang gagal**

```php
    public function test_cars_index_shows_odometer(): void
    {
        $owner = \App\Models\User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => \App\Models\User::ROLE_OWNER, 'is_admin' => true,
        ]);
        $car = $this->car(['odometer_baseline_km' => 80000]);
        CarMileageDaily::create(['tenant_id' => $this->tenant->id, 'car_id' => $car->id, 'date' => '2026-08-11', 'km' => 120]);

        $this->actingAs($owner)->get('/admin/cars')->assertOk()->assertSee('80.120');
    }
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `php artisan test --filter="MileageTest::test_cars_index_shows_odometer"`
Expected: FAIL (kemungkinan lulus jika angka kebetulan muncul — pastikan gagal dulu, kalau tidak, artinya UI sudah menampilkan; lanjut).

- [ ] **Step 3: Tampilkan odometer di `resources/views/admin/cars/index.blade.php`**

Cari baris tiap mobil (kartu/row). Tambahkan tampilan odometer, mis. di dekat nama/plat:

```blade
                        <div style="font-size:.82rem;color:var(--petrol-600)">
                            Odometer: {{ number_format($car->odometerKm(), 0, ',', '.') }} km
                            @php $kmStat = $car->serviceKmStatus(); @endphp
                            @if ($kmStat === 'overdue')
                                · <span style="color:var(--danger)">servis lewat</span>
                            @elseif ($kmStat === 'soon')
                                · <span style="color:var(--amber-600)">servis dalam {{ $car->kmUntilService() }} km</span>
                            @endif
                        </div>
```

(Sesuaikan penempatan dengan struktur kartu mobil yang ada.)

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `php artisan test --filter="MileageTest::test_cars_index_shows_odometer"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/cars/index.blade.php tests/Feature/MileageTest.php
git commit -m "feat: show odometer + km-to-service on admin cars list"
```

---

### Task 7: Seeder demo mileage

**Files:**
- Create: `database/seeders/MileageDemoSeeder.php`

**Interfaces:**
- Produces: seeder yang mengisi baseline + `car_mileage_daily` ~14 hari untuk tiap mobil tenant aktif, agar odometer & servis-km bisa dipamerkan. Dijalankan manual: `php artisan db:seed --class=MileageDemoSeeder`.

- [ ] **Step 1: Tulis seeder**

```php
<?php
namespace Database\Seeders;

use App\Models\Car;
use App\Models\CarMileageDaily;
use Illuminate\Database\Seeder;

class MileageDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Car::all() as $i => $car) {
            $baseline = 50000 + ($car->id * 7919) % 80000;   // deterministic-ish
            $interval = 5000;
            $car->forceFill([
                'odometer_baseline_km' => $baseline,
                'baseline_at' => now(),
                'service_interval_km' => $interval,
            ])->save();

            $total = 0;
            for ($d = 13; $d >= 0; $d--) {
                $km = 20 + (($car->id + $d) % 60);
                $total += $km;
                CarMileageDaily::updateOrCreate(
                    ['car_id' => $car->id, 'date' => now()->subDays($d)->toDateString()],
                    ['tenant_id' => $car->tenant_id, 'km' => $km]
                );
            }

            // Put last service so some cars are "due soon".
            $odometer = $baseline + $total;
            $car->forceFill(['service_last_km' => max(0, $odometer - $interval + (($car->id % 2) ? 300 : 3000))])->save();
        }
    }
}
```

- [ ] **Step 2: Smoke test seeder (dev DB)**

Run: `php artisan db:seed --class=MileageDemoSeeder`
Expected: selesai tanpa error; `/admin/cars` menampilkan odometer & beberapa "servis dalam X km".

- [ ] **Step 3: Commit**

```bash
git add database/seeders/MileageDemoSeeder.php
git commit -m "feat: MileageDemoSeeder to demonstrate odometer + service-km"
```

---

### Task 8: Regresi penuh

- [ ] **Step 1:** Run: `php artisan test` — Expected: semua hijau (62 + ~7 baru).
- [ ] **Step 2:** (jika merah) perbaiki & commit.
