# Rating & Ulasan Driver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Customer yang sudah menyelesaikan booking bisa memberi rating 4-kriteria untuk driver (tampil di profil publik driver) DAN testimoni bisnis (tampil di landing page setelah admin approve) — dari satu halaman Lacak Pesanan yang sudah ada, tanpa login.

**Architecture:** Tabel baru `driver_reviews` (model `DriverReview`, pola tenant-scoping identik `Testimonial`) untuk ulasan driver. Tabel `testimonials` yang sudah ada dapat kolom `booking_id` nullable-unik sebagai sumber baru — moderasinya reuse 100% form edit Testimoni admin yang sudah ada (`is_published` checkbox), tidak ada mekanisme baru untuk jalur ini. Dua form submit publik (throttled, tanpa login) muncul di halaman `/lacak/{kode}` yang sudah ada, gerbang keduanya: `booking->status === 'completed'`.

**Tech Stack:** Laravel 11 (Eloquent, Blade), PHPUnit (`php artisan test`), CSS murni untuk star-picker (tanpa JS/dependency baru).

## Global Constraints

- Satu ulasan driver per booking (`driver_reviews.booking_id` UNIQUE). Satu testimoni bisnis per booking (`testimonials.booking_id` UNIQUE, nullable — baris testimoni manual lama tetap `null`).
- Ulasan driver punya 4 rating wajib (Ketepatan Waktu, Kebersihan & Kondisi Mobil, Keramahan & Sikap, Keamanan Berkendara), masing-masing integer 1-5, plus `rating_overall` = rata-rata 4 itu (desimal 1 angka di belakang koma), dihitung saat create.
- Testimoni bisnis dari customer: `rating` tunggal 1-5, `quote` wajib, `is_published = false` saat dibuat (moderasi lewat form edit Testimoni yang SUDAH ADA — TIDAK ADA controller/panel admin baru untuk jalur ini).
- Ulasan driver bergerbang: booking `completed` DAN punya `driver_id`. Testimoni bisnis bergerbang: booking `completed` saja (tidak butuh driver).
- Balasan admin HANYA untuk ulasan driver (kolom `admin_reply`/`replied_at`), bukan untuk testimoni bisnis, dan bukan dari driver.
- Nama customer di profil publik driver disamarkan: kata pertama penuh + inisial kata terakhir + titik (`"Budi Santoso"` → `"Budi S."`; satu kata saja → tampil apa adanya).
- Rute publik `/pengemudi/{driver}` 404 kalau `$driver->role !== User::ROLE_DRIVER` (cegah intip akun lain).
- Sebelum mengklaim task selesai, jalankan `php artisan test` dan pastikan hijau. Baseline sebelum plan ini: 222 passed (lihat `git log` untuk commit acuan).
- Jangan `git push`. Commit lokal per task selesai.

---

### Task 1: Migration + model `DriverReview`

**Files:**
- Create: `database/migrations/2026_07_21_010000_create_driver_reviews_table.php`
- Create: `app/Models/DriverReview.php`
- Modify: `app/Models/Booking.php` (tambah relasi `driverReview()`)
- Modify: `app/Models/User.php` (tambah relasi `driverReviews()`)
- Test: `tests/Unit/DriverReviewTest.php`

**Interfaces:**
- Produces: `DriverReview::create([...])`, `DriverReview::scopePublished()`, `DriverReview->maskedCustomerName(): string`, `Booking->driverReview(): HasOne`, `User->driverReviews(): HasMany`. Dipakai Task 4 (submission), Task 6 (profil publik), Task 7 (moderasi admin), Task 9 (dashboard driver).

- [ ] **Step 1: Tulis migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating_punctuality');
            $table->unsignedTinyInteger('rating_cleanliness');
            $table->unsignedTinyInteger('rating_friendliness');
            $table->unsignedTinyInteger('rating_safety');
            $table->decimal('rating_overall', 2, 1);
            $table->text('comment')->nullable();
            $table->string('status')->default('pending'); // pending | published | rejected
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_reviews');
    }
};
```

- [ ] **Step 2: Jalankan migration**

Run: `php artisan migrate`
Expected: `2026_07_21_010000_create_driver_reviews_table ... DONE`

- [ ] **Step 3: Tulis test model (gagal dulu)**

```php
<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Car;
use App\Models\DriverReview;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(string $customerName = 'Budi Santoso'): Booking
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        $driver = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Driver Uji', 'email' => 'drv-'.uniqid().'@lajur.id',
            'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false,
        ]);
        $car = Car::create([
            'name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000,
        ]);

        return Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $car->name,
            'customer_name' => $customerName, 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
        ]);
    }

    public function test_relations_and_masked_name(): void
    {
        $booking = $this->makeBooking('Budi Santoso');

        $review = DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $booking->driver_id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 4, 'rating_friendliness' => 5,
            'rating_safety' => 4, 'rating_overall' => 4.5, 'status' => 'published',
        ]);

        $this->assertSame($booking->id, $review->booking->id);
        $this->assertSame($booking->driver_id, $review->driver->id);
        $this->assertTrue($booking->fresh()->driverReview->is($review));
        $this->assertTrue($review->driver->driverReviews->contains($review));
        $this->assertSame('Budi S.', $review->maskedCustomerName());
    }

    public function test_masked_name_with_single_word(): void
    {
        $booking = $this->makeBooking('Sari');
        $review = DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $booking->driver_id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5,
            'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'published',
        ]);

        $this->assertSame('Sari', $review->maskedCustomerName());
    }

    public function test_published_scope_excludes_pending_and_rejected(): void
    {
        $b1 = $this->makeBooking('A');
        $b2 = $this->makeBooking('B');
        DriverReview::create(['booking_id' => $b1->id, 'driver_id' => $b1->driver_id, 'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'published']);
        DriverReview::create(['booking_id' => $b2->id, 'driver_id' => $b2->driver_id, 'rating_punctuality' => 3, 'rating_cleanliness' => 3, 'rating_friendliness' => 3, 'rating_safety' => 3, 'rating_overall' => 3.0, 'status' => 'pending']);

        $this->assertSame(1, DriverReview::published()->count());
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/DriverReviewTest.php`
Expected: FAIL — `Class "App\Models\DriverReview" not found`

- [ ] **Step 5: Tulis model**

```php
<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverReview extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'driver_id',
        'rating_punctuality',
        'rating_cleanliness',
        'rating_friendliness',
        'rating_safety',
        'rating_overall',
        'comment',
        'status',
        'admin_reply',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'rating_punctuality' => 'integer',
            'rating_cleanliness' => 'integer',
            'rating_friendliness' => 'integer',
            'rating_safety' => 'integer',
            'rating_overall' => 'float',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Nama customer disamarkan untuk tampil di profil publik driver. */
    public function maskedCustomerName(): string
    {
        $name = trim((string) $this->booking?->customer_name);
        $words = preg_split('/\s+/', $name);

        if (count($words) < 2) {
            return $name;
        }

        $last = array_pop($words);

        return implode(' ', $words).' '.mb_strtoupper(mb_substr($last, 0, 1)).'.';
    }
}
```

- [ ] **Step 6: Tambah relasi di `Booking` dan `User`**

Modify `app/Models/Booking.php` — tambahkan setelah method `driver()`:

```php
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<DriverReview, $this>
     */
    public function driverReview(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DriverReview::class);
    }
```

Modify `app/Models/User.php` — tambahkan setelah method `driverBookings()`:

```php
    /**
     * @return HasMany<DriverReview, $this>
     */
    public function driverReviews(): HasMany
    {
        return $this->hasMany(DriverReview::class, 'driver_id');
    }
```

(`HasMany` sudah di-import di `User.php` dari method `driverBookings()` yang sudah ada — tidak perlu import baru. `Booking.php` belum meng-import `HasOne`; import inline seperti di atas atau tambahkan `use Illuminate\Database\Eloquent\Relations\HasOne;` di bagian atas file dan pakai `HasOne` langsung, pilih salah satu — pola file ini pakai import di atas, jadi tambahkan barisnya di bagian `use` bersama `BelongsTo` yang sudah ada, lalu tulis method sebagai `public function driverReview(): HasOne`.)

- [ ] **Step 7: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Unit/DriverReviewTest.php`
Expected: `Tests: 3 passed`

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_21_010000_create_driver_reviews_table.php app/Models/DriverReview.php app/Models/Booking.php app/Models/User.php tests/Unit/DriverReviewTest.php
git commit -m "feat: tabel & model DriverReview + relasi Booking/User"
```

---

### Task 2: Kolom `booking_id` di `testimonials` + badge admin

**Files:**
- Create: `database/migrations/2026_07_21_010100_add_booking_id_to_testimonials_table.php`
- Modify: `app/Models/Testimonial.php` (tambah `booking_id` ke fillable + relasi `booking()`)
- Modify: `resources/views/admin/testimonials/index.blade.php` (badge "Dari Customer")
- Test: `tests/Unit/TestimonialBookingSourceTest.php`

**Interfaces:**
- Produces: `Testimonial::create(['booking_id' => ..., ...])`, `Testimonial->booking(): BelongsTo`. Dipakai Task 4 (submission jalur B).

- [ ] **Step 1: Tulis migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->unique()->after('sort_order')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
```

- [ ] **Step 2: Jalankan migration**

Run: `php artisan migrate`
Expected: `2026_07_21_010100_add_booking_id_to_testimonials_table ... DONE`

- [ ] **Step 3: Tulis test (gagal dulu)**

```php
<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialBookingSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_testimonial_can_link_to_a_booking(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);
        $booking = Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Rina',
            'customer_email' => 'r@x.id', 'customer_phone' => '0811', 'start_date' => '2026-09-01',
            'end_date' => '2026-09-03', 'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000,
            'status' => 'completed',
        ]);

        $testimonial = Testimonial::create([
            'name' => 'Rina', 'rating' => 5, 'quote' => 'Mantap!', 'is_published' => false,
            'booking_id' => $booking->id,
        ]);

        $this->assertSame($booking->id, $testimonial->booking->id);
    }

    public function test_manual_testimonial_without_booking_still_works(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        $testimonial = Testimonial::create(['name' => 'Owner Manual', 'rating' => 5, 'quote' => 'Testimoni manual', 'is_published' => true]);

        $this->assertNull($testimonial->booking_id);
        $this->assertNull($testimonial->booking);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/TestimonialBookingSourceTest.php`
Expected: FAIL — `booking_id` tidak ada di `$fillable` (mass assignment silently dropped, assertion `assertSame($booking->id, ...)` gagal karena null)

- [ ] **Step 5: Modifikasi model**

`app/Models/Testimonial.php` — tambah `'booking_id'` ke `$fillable`, dan tambah method baru setelah `hasLocalAvatar()`:

```php
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Booking, $this>
     */
    public function booking(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
```

- [ ] **Step 6: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Unit/TestimonialBookingSourceTest.php`
Expected: `Tests: 2 passed`

- [ ] **Step 7: Tambah badge di panel admin Testimoni**

Modify `resources/views/admin/testimonials/index.blade.php` — cari baris berikut:

```blade
                        <td><span class="pill {{ $t->is_published ? 'pill-yes' : 'pill-no' }}">{{ $t->is_published ? 'Terbit' : 'Draft' }}</span></td>
```

Ganti jadi (tambah badge sumber sebelum pill status):

```blade
                        <td>
                            @if ($t->booking_id)
                                <span class="tag" style="margin-right:6px">Dari Customer</span>
                            @endif
                            <span class="pill {{ $t->is_published ? 'pill-yes' : 'pill-no' }}">{{ $t->is_published ? 'Terbit' : 'Draft' }}</span>
                        </td>
```

- [ ] **Step 8: Jalankan full suite, pastikan tak ada regresi**

Run: `php artisan test`
Expected: semua hijau (baseline + test baru)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_21_010100_add_booking_id_to_testimonials_table.php app/Models/Testimonial.php resources/views/admin/testimonials/index.blade.php tests/Unit/TestimonialBookingSourceTest.php
git commit -m "feat: testimonials.booking_id — sumber testimoni dari customer"
```

---

### Task 3: Komponen `<x-star-input>` (pemilih bintang, CSS murni)

**Files:**
- Create: `resources/views/components/star-input.blade.php`
- Modify: `public/css/app.css` (tambah `.star-input`)

**Interfaces:**
- Produces: `<x-star-input name="rating_punctuality" required />` — dipakai Task 5 (form ulasan di halaman Lacak Pesanan, 5×: 4 kriteria driver + 1 rating testimoni).

- [ ] **Step 1: Tulis komponen**

```blade
@props(['name', 'required' => false])
<div class="star-input" role="radiogroup" aria-label="Beri rating">
    @for ($i = 5; $i >= 1; $i--)
        <input type="radio" name="{{ $name }}" id="{{ $name }}-{{ $i }}" value="{{ $i }}" @if ($required) required @endif>
        <label for="{{ $name }}-{{ $i }}" aria-label="{{ $i }} bintang"><x-icon name="star" /></label>
    @endfor
</div>
```

- [ ] **Step 2: Tambah CSS** — buka `public/css/app.css`, cari blok `.testi .who .role { ... }` (akhir styling testimoni marquee), tambahkan setelahnya:

```css
/* ---------- Star input (pemilih rating, CSS murni tanpa JS) ---------- */
/* DOM 5→1 + row-reverse: trik standar agar :checked/hover men-highlight
   bintang di sebelah kiri lewat selector ~ (hanya menjangkau sibling SETELAHNYA di DOM). */
.star-input { display: inline-flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
.star-input input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
.star-input label { cursor: pointer; color: var(--ivory-200); transition: color .12s ease; }
.star-input label svg { width: 28px; height: 28px; }
.star-input input:checked ~ label { color: var(--amber); }
.star-input input:not(:checked) ~ label:hover,
.star-input input:not(:checked) ~ label:hover ~ label { color: var(--amber); }
.star-input input:focus-visible + label { outline: 2px solid var(--amber); outline-offset: 2px; border-radius: 4px; }
```

- [ ] **Step 3: Verifikasi render tanpa error** — komponen ini tidak punya test tersendiri (murni tampilan, tanpa logika); diverifikasi lewat test Task 5 yang mem-render halaman berisi komponen ini via `assertOk()`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/star-input.blade.php public/css/app.css
git commit -m "feat: komponen x-star-input (pemilih rating CSS murni)"
```

---

### Task 4: Controller submit ulasan driver & testimoni bisnis (publik)

**Files:**
- Create: `app/Http/Controllers/DriverReviewController.php`
- Create: `app/Http/Controllers/PublicTestimonialController.php`
- Modify: `routes/web.php` (2 route POST baru + import 2 controller, di dekat grup "Order tracking" yang sudah ada, baris ~61)
- Test: `tests/Feature/DriverReviewSubmissionTest.php`
- Test: `tests/Feature/PublicTestimonialSubmissionTest.php`

**Interfaces:**
- Consumes: `App\Models\DriverReview` (Task 1), `App\Models\Testimonial` dengan `booking_id` (Task 2).
- Produces: route `driver-review.store` (POST `/lacak/{bookingCode}/ulasan-driver`), route `testimonial.store` (POST `/lacak/{bookingCode}/ulasan-bisnis`). Dipakai Task 5 (form di halaman Lacak Pesanan).

- [ ] **Step 1: Tulis test ulasan driver (gagal dulu)**

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\DriverReview;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(string $status = 'completed', bool $withDriver = true): Booking
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        $driverId = null;
        if ($withDriver) {
            $driver = User::create([
                'tenant_id' => $tenant->id, 'name' => 'Driver Uji', 'email' => 'drv-'.uniqid().'@lajur.id',
                'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false,
            ]);
            $driverId = $driver->id;
        }
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);

        return Booking::create([
            'car_id' => $car->id, 'driver_id' => $driverId, 'car_name' => $car->name,
            'customer_name' => 'Budi Santoso', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => $status,
            'booking_code' => Booking::generateBookingCode(),
        ]);
    }

    public function test_customer_can_submit_driver_review_for_completed_booking(): void
    {
        $booking = $this->makeBooking();

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", [
            'rating_punctuality' => 5, 'rating_cleanliness' => 4, 'rating_friendliness' => 5,
            'rating_safety' => 4, 'comment' => 'Sopir ramah dan tepat waktu.',
        ])->assertRedirect(route('tracking.show', $booking->booking_code));

        $review = DriverReview::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('pending', $review->status);
        $this->assertSame(4.5, $review->rating_overall);
    }

    public function test_cannot_submit_twice_for_the_same_booking(): void
    {
        $booking = $this->makeBooking();
        $payload = ['rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5];

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", $payload);
        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", $payload);

        $this->assertSame(1, DriverReview::where('booking_id', $booking->id)->count());
    }

    public function test_cannot_submit_for_booking_not_completed(): void
    {
        $booking = $this->makeBooking('confirmed');

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", [
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5,
        ]);

        $this->assertSame(0, DriverReview::where('booking_id', $booking->id)->count());
    }

    public function test_cannot_submit_when_booking_has_no_driver(): void
    {
        $booking = $this->makeBooking('completed', withDriver: false);

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", [
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5,
        ]);

        $this->assertSame(0, DriverReview::query()->count());
    }

    public function test_invalid_rating_value_is_rejected(): void
    {
        $booking = $this->makeBooking();

        $this->post("/lacak/{$booking->booking_code}/ulasan-driver", [
            'rating_punctuality' => 6, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5,
        ])->assertSessionHasErrors('rating_punctuality');

        $this->assertSame(0, DriverReview::query()->count());
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/DriverReviewSubmissionTest.php`
Expected: FAIL — route `driver-review.store` tidak ada (404)

- [ ] **Step 3: Tulis controller ulasan driver**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\DriverReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverReviewController extends Controller
{
    public function store(Request $request, string $bookingCode): RedirectResponse
    {
        $booking = Booking::where('booking_code', strtoupper($bookingCode))->first();

        if ($booking === null) {
            return redirect()->route('tracking.search')->with('tracking_error', 'Kode booking tidak ditemukan.');
        }

        if ($booking->status !== 'completed' || $booking->driver_id === null) {
            abort(404);
        }

        if (DriverReview::where('booking_id', $booking->id)->exists()) {
            return redirect()->route('tracking.show', $bookingCode)
                ->with('review_error', 'Anda sudah memberi ulasan untuk driver pada booking ini.');
        }

        $data = $request->validate([
            'rating_punctuality' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_cleanliness' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_friendliness' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_safety' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ], [], [
            'rating_punctuality' => 'ketepatan waktu',
            'rating_cleanliness' => 'kebersihan mobil',
            'rating_friendliness' => 'keramahan',
            'rating_safety' => 'keamanan berkendara',
        ]);

        $overall = round(array_sum([
            $data['rating_punctuality'], $data['rating_cleanliness'],
            $data['rating_friendliness'], $data['rating_safety'],
        ]) / 4, 1);

        try {
            DriverReview::create([
                'booking_id' => $booking->id,
                'driver_id' => $booking->driver_id,
                'rating_punctuality' => $data['rating_punctuality'],
                'rating_cleanliness' => $data['rating_cleanliness'],
                'rating_friendliness' => $data['rating_friendliness'],
                'rating_safety' => $data['rating_safety'],
                'rating_overall' => $overall,
                'comment' => $data['comment'] ?? null,
                'status' => 'pending',
            ]);
        } catch (\Illuminate\Database\QueryException) {
            // Kemungkinan kecil dua submit nyaris bersamaan lolos pengecekan exists() di atas —
            // constraint UNIQUE di kolom booking_id jadi jaring pengaman terakhir.
            return redirect()->route('tracking.show', $bookingCode)
                ->with('review_error', 'Anda sudah memberi ulasan untuk driver pada booking ini.');
        }

        return redirect()->route('tracking.show', $bookingCode)
            ->with('review_success', 'Terima kasih! Ulasan driver Anda sedang ditinjau.');
    }
}
```

- [ ] **Step 4: Tambah route** — buka `routes/web.php`, tambah import di bagian atas (dekat import `use App\Http\Controllers\TrackingController as PublicTrackingController;`):

```php
use App\Http\Controllers\DriverReviewController;
use App\Http\Controllers\PublicTestimonialController;
```

Lalu tambahkan route baru persis setelah baris `Route::get('/pantau/{bookingCode}', ...)`:

```php
Route::post('/lacak/{bookingCode}/ulasan-driver', [DriverReviewController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('driver-review.store');
Route::post('/lacak/{bookingCode}/ulasan-bisnis', [PublicTestimonialController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('testimonial.store');
```

- [ ] **Step 5: Jalankan test ulasan driver, pastikan lolos**

Run: `php artisan test tests/Feature/DriverReviewSubmissionTest.php`
Expected: `Tests: 5 passed`

- [ ] **Step 6: Tulis test testimoni bisnis (gagal dulu)**

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTestimonialSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(string $status = 'completed'): Booking
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);

        return Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Rina Wijaya',
            'customer_email' => 'r@x.id', 'customer_phone' => '0811', 'start_date' => '2026-09-01',
            'end_date' => '2026-09-03', 'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000,
            'status' => $status, 'booking_code' => Booking::generateBookingCode(),
        ]);
    }

    public function test_customer_can_submit_business_testimonial_for_completed_booking(): void
    {
        $booking = $this->makeBooking();

        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", [
            'rating' => 5, 'quote' => 'Prosesnya cepat dan mobilnya bersih!',
        ])->assertRedirect(route('tracking.show', $booking->booking_code));

        $testimonial = Testimonial::where('booking_id', $booking->id)->firstOrFail();
        $this->assertFalse($testimonial->is_published);
        $this->assertSame('Rina Wijaya', $testimonial->name);
        $this->assertSame(5, $testimonial->rating);
    }

    public function test_cannot_submit_twice_for_the_same_booking(): void
    {
        $booking = $this->makeBooking();
        $payload = ['rating' => 4, 'quote' => 'Bagus.'];

        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", $payload);
        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", $payload);

        $this->assertSame(1, Testimonial::where('booking_id', $booking->id)->count());
    }

    public function test_cannot_submit_for_booking_not_completed(): void
    {
        $booking = $this->makeBooking('confirmed');

        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", ['rating' => 5, 'quote' => 'Test']);

        $this->assertSame(0, Testimonial::where('booking_id', $booking->id)->count());
    }

    public function test_quote_is_required(): void
    {
        $booking = $this->makeBooking();

        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", ['rating' => 5, 'quote' => ''])
            ->assertSessionHasErrors('quote');

        $this->assertSame(0, Testimonial::where('booking_id', $booking->id)->count());
    }
}
```

- [ ] **Step 7: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/PublicTestimonialSubmissionTest.php`
Expected: FAIL — `Class "App\Http\Controllers\PublicTestimonialController" not found`

- [ ] **Step 8: Tulis controller testimoni bisnis**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicTestimonialController extends Controller
{
    public function store(Request $request, string $bookingCode): RedirectResponse
    {
        $booking = Booking::where('booking_code', strtoupper($bookingCode))->first();

        if ($booking === null) {
            return redirect()->route('tracking.search')->with('tracking_error', 'Kode booking tidak ditemukan.');
        }

        if ($booking->status !== 'completed') {
            abort(404);
        }

        if (Testimonial::where('booking_id', $booking->id)->exists()) {
            return redirect()->route('tracking.show', $bookingCode)
                ->with('testimonial_error', 'Anda sudah mengirim ulasan untuk booking ini.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'quote' => ['required', 'string', 'max:2000'],
        ]);

        try {
            Testimonial::create([
                'name' => $booking->customer_name,
                'rating' => $data['rating'],
                'quote' => $data['quote'],
                'is_published' => false,
                'sort_order' => 0,
                'booking_id' => $booking->id,
            ]);
        } catch (\Illuminate\Database\QueryException) {
            return redirect()->route('tracking.show', $bookingCode)
                ->with('testimonial_error', 'Anda sudah mengirim ulasan untuk booking ini.');
        }

        return redirect()->route('tracking.show', $bookingCode)
            ->with('testimonial_success', 'Terima kasih! Ulasan Anda sedang ditinjau tim kami.');
    }
}
```

- [ ] **Step 9: Jalankan kedua file test, pastikan lolos**

Run: `php artisan test tests/Feature/DriverReviewSubmissionTest.php tests/Feature/PublicTestimonialSubmissionTest.php`
Expected: `Tests: 9 passed`

- [ ] **Step 10: Jalankan full suite**

Run: `php artisan test`
Expected: semua hijau, tidak ada regresi

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/DriverReviewController.php app/Http/Controllers/PublicTestimonialController.php routes/web.php tests/Feature/DriverReviewSubmissionTest.php tests/Feature/PublicTestimonialSubmissionTest.php
git commit -m "feat: rute & controller submit ulasan driver + testimoni bisnis"
```

---

### Task 5: Form ulasan di halaman Lacak Pesanan

**Files:**
- Modify: `app/Http/Controllers/TrackingController.php:20-33` (method `show()`)
- Modify: `resources/views/tracking/show.blade.php`
- Test: `tests/Feature/TrackingPageReviewFormTest.php`

**Interfaces:**
- Consumes: route `driver-review.store`, `testimonial.store` (Task 4), `<x-star-input>` (Task 3), `DriverReview`/`Testimonial` model (Task 1/2).

- [ ] **Step 1: Tulis test (gagal dulu)**

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\DriverReview;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingPageReviewFormTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompletedBookingWithDriver(): Booking
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $driver = User::create(['tenant_id' => $tenant->id, 'name' => 'Rahmat', 'email' => 'r-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);

        return Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
            'booking_code' => Booking::generateBookingCode(),
        ]);
    }

    public function test_review_forms_show_for_completed_booking_with_driver(): void
    {
        $booking = $this->makeCompletedBookingWithDriver();

        $this->get("/lacak/{$booking->booking_code}")
            ->assertOk()
            ->assertSee('rating_punctuality', false)
            ->assertSee('ulasan-bisnis', false);
    }

    public function test_driver_review_form_hidden_once_submitted_pending(): void
    {
        $booking = $this->makeCompletedBookingWithDriver();
        DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $booking->driver_id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5,
            'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'pending',
        ]);

        $this->get("/lacak/{$booking->booking_code}")
            ->assertOk()
            ->assertSee('sedang ditinjau')
            ->assertDontSee('name="rating_punctuality"', false);
    }

    public function test_testimonial_form_hidden_once_submitted(): void
    {
        $booking = $this->makeCompletedBookingWithDriver();
        Testimonial::create(['name' => 'Budi', 'rating' => 5, 'quote' => 'Bagus', 'is_published' => false, 'booking_id' => $booking->id]);

        $this->get("/lacak/{$booking->booking_code}")
            ->assertOk()
            ->assertDontSee('name="quote"', false);
    }

    public function test_review_forms_hidden_when_booking_not_completed(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);
        $booking = Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Budi',
            'customer_email' => 'c@x.id', 'customer_phone' => '0811', 'start_date' => '2026-09-01',
            'end_date' => '2026-09-03', 'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000,
            'status' => 'confirmed', 'booking_code' => Booking::generateBookingCode(),
        ]);

        $this->get("/lacak/{$booking->booking_code}")
            ->assertOk()
            ->assertDontSee('name="quote"', false)
            ->assertDontSee('name="rating_punctuality"', false);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/TrackingPageReviewFormTest.php`
Expected: FAIL — halaman belum menampilkan form/field yang dicari

- [ ] **Step 3: Modifikasi `TrackingController@show`**

Tambahkan dua import baru di bagian atas `app/Http/Controllers/TrackingController.php` (setelah `use App\Models\Booking;`):

```php
use App\Models\DriverReview;
use App\Models\Testimonial;
```

Lalu ganti isi method `show()`:

```php
    public function show(string $bookingCode): View|RedirectResponse
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

        $driverReview = $booking->driver_id
            ? DriverReview::where('booking_id', $booking->id)->first()
            : null;
        $businessReview = Testimonial::where('booking_id', $booking->id)->first();

        return view('tracking.show', [
            'booking' => $booking,
            'demo' => (bool) config('services.tracking.demo'),
            'driverReview' => $driverReview,
            'businessReview' => $businessReview,
        ]);
    }
```

- [ ] **Step 4: Tambah blok ulasan di view** — buka `resources/views/tracking/show.blade.php`, sisipkan section baru persis SEBELUM blok `{{-- ===== Bagikan ke keluarga ===== --}}`:

```blade
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
                    <div class="panel-head"><h2>Ulasan untuk {{ $booking->driver->name }}</h2></div>
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
```

- [ ] **Step 5: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Feature/TrackingPageReviewFormTest.php`
Expected: `Tests: 4 passed`

- [ ] **Step 6: Jalankan full suite**

Run: `php artisan test`
Expected: semua hijau, tidak ada regresi

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/TrackingController.php resources/views/tracking/show.blade.php tests/Feature/TrackingPageReviewFormTest.php
git commit -m "feat: form ulasan driver & bisnis di halaman Lacak Pesanan"
```

---

### Task 6: Profil publik driver

**Files:**
- Create: `app/Http/Controllers/PublicDriverProfileController.php`
- Create: `resources/views/driver/public-profile.blade.php`
- Modify: `routes/web.php` (1 route baru + import, dekat grup tracking)
- Modify: `resources/views/tracking/show.blade.php` (tautan "Lihat Profil Driver")
- Modify: `resources/views/tracking/watch.blade.php` (tautan sama, di baris "Pengemudi" yang sudah ada)
- Test: `tests/Feature/PublicDriverProfileTest.php`

**Interfaces:**
- Consumes: `DriverReview::published()` (Task 1), `<x-avatar>` (sudah ada dari sesi sebelumnya).
- Produces: route `driver.public-profile` (GET `/pengemudi/{driver}`).

- [ ] **Step 1: Tulis test (gagal dulu)**

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\DriverReview;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDriverProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriverWithReview(string $reviewStatus = 'published'): User
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $driver = User::create(['tenant_id' => $tenant->id, 'name' => 'Rahmat Hidayat', 'email' => 'r-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);
        $booking = Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $car->name,
            'customer_name' => 'Budi Santoso', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
            'booking_code' => Booking::generateBookingCode(),
        ]);
        DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $driver->id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 4, 'rating_friendliness' => 5,
            'rating_safety' => 4, 'rating_overall' => 4.5, 'comment' => 'Sangat baik',
            'status' => $reviewStatus,
        ]);

        return $driver;
    }

    public function test_public_profile_shows_published_review_masked_name(): void
    {
        $driver = $this->makeDriverWithReview('published');

        $this->get("/pengemudi/{$driver->id}")
            ->assertOk()
            ->assertSee('Rahmat Hidayat')
            ->assertSee('Budi S.')
            ->assertSee('Sangat baik');
    }

    public function test_pending_review_not_shown_on_public_profile(): void
    {
        $driver = $this->makeDriverWithReview('pending');

        $this->get("/pengemudi/{$driver->id}")
            ->assertOk()
            ->assertDontSee('Budi S.')
            ->assertDontSee('Sangat baik');
    }

    public function test_non_driver_user_returns_404(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'o-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true]);

        $this->get("/pengemudi/{$owner->id}")->assertNotFound();
    }

    public function test_driver_from_another_tenant_returns_404(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-tenant']);
        app(TenantManager::class)->set($other);
        $foreignDriver = User::create(['tenant_id' => $other->id, 'name' => 'Driver Asing', 'email' => 'f-'.uniqid().'@other.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);

        // Konteks tenant kembali ke 'lajur' sebelum request — meniru pengunjung yang
        // membuka /pengemudi/{id} dari subdomain tenant lain (bukan tenant milik driver ini).
        $lajur = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($lajur);

        $this->get("/pengemudi/{$foreignDriver->id}")->assertNotFound();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/PublicDriverProfileTest.php`
Expected: FAIL — route tidak ada (404 pada request yang seharusnya 200, atau class controller belum ada)

- [ ] **Step 3: Tulis controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\DriverReview;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;

class PublicDriverProfileController extends Controller
{
    public function show(User $driver, TenantManager $manager): View
    {
        // User TIDAK punya global tenant scope (lihat App\Models\User) — tanpa guard ini,
        // profil driver dari tenant lain bisa terbuka lewat subdomain tenant manapun.
        abort_unless(
            $driver->role === User::ROLE_DRIVER && $driver->tenant_id === $manager->id(),
            404
        );

        $reviews = DriverReview::published()
            ->where('driver_id', $driver->id)
            ->with('booking')
            ->latest()
            ->paginate(10);

        $completedTrips = $driver->driverBookings()->where('status', 'completed')->count();

        $aggregate = DriverReview::published()->where('driver_id', $driver->id)->selectRaw('
            AVG(rating_overall) as overall,
            AVG(rating_punctuality) as punctuality,
            AVG(rating_cleanliness) as cleanliness,
            AVG(rating_friendliness) as friendliness,
            AVG(rating_safety) as safety
        ')->first();

        return view('driver.public-profile', [
            'driver' => $driver,
            'reviews' => $reviews,
            'completedTrips' => $completedTrips,
            'avgOverall' => $aggregate->overall !== null ? round((float) $aggregate->overall, 1) : null,
            'avgPunctuality' => $aggregate->punctuality !== null ? round((float) $aggregate->punctuality, 1) : null,
            'avgCleanliness' => $aggregate->cleanliness !== null ? round((float) $aggregate->cleanliness, 1) : null,
            'avgFriendliness' => $aggregate->friendliness !== null ? round((float) $aggregate->friendliness, 1) : null,
            'avgSafety' => $aggregate->safety !== null ? round((float) $aggregate->safety, 1) : null,
        ]);
    }
}
```

- [ ] **Step 4: Tambah route** — buka `routes/web.php`, tambah import:

```php
use App\Http\Controllers\PublicDriverProfileController;
```

Tambahkan route persis setelah dua route ulasan yang ditambahkan Task 4:

```php
Route::get('/pengemudi/{driver}', [PublicDriverProfileController::class, 'show'])->name('driver.public-profile');
```

- [ ] **Step 5: Tulis view**

```blade
@extends('layouts.public')

@section('title', $driver->name.' — Profil Driver — Lajur')

@push('head')
<style>
    .drvp-card{background:var(--white);border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);overflow:hidden;margin-bottom:24px}
    .drvp-banner{position:relative;height:104px;background:radial-gradient(120% 160% at 20% -20%,var(--petrol-600) 0%,var(--petrol) 60%,var(--petrol-700) 100%)}
    .drvp-head{display:flex;flex-direction:column;align-items:center;text-align:center;padding:0 24px 26px;margin-top:-52px}
    .drvp-head .avatar-lg{border:4px solid var(--white);box-shadow:0 10px 26px -8px rgba(15,27,51,.35)}
    .drvp-name{font-family:var(--font-display);font-weight:800;font-size:1.4rem;margin-top:14px}
    .drvp-overall{display:flex;align-items:center;gap:6px;margin-top:8px;color:var(--amber-600);font-weight:700}
    .drvp-overall svg{width:20px;height:20px}
    .drvp-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:1px;background:var(--ivory-200);border-top:1px solid var(--ivory-200)}
    .drvp-stat{background:var(--white);padding:16px;text-align:center}
    .drvp-stat .n{display:block;font-family:var(--font-mono);font-weight:700;font-size:1.3rem;color:var(--petrol)}
    .drvp-stat .l{font-size:.78rem;color:var(--graphite)}
    .drvp-breakdown{padding:20px 24px;border-top:1px solid var(--ivory-200);display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .drvp-breakdown .row{display:flex;justify-content:space-between;font-size:.9rem}
    .drvp-breakdown .row .k{color:var(--graphite)}
    .drvp-breakdown .row .v{font-weight:700;color:var(--petrol)}
    .drvp-review{padding:18px 24px;border-top:1px solid var(--ivory-200)}
    .drvp-review .who{font-weight:700;margin-bottom:4px}
    .drvp-review .stars{color:var(--amber);display:flex;gap:2px;margin-bottom:6px}
    .drvp-review .stars svg{width:14px;height:14px}
    .drvp-reply{margin-top:10px;padding:10px 14px;background:var(--ivory);border-radius:var(--radius);font-size:.88rem}
    .drvp-reply .lbl{font-weight:700;color:var(--petrol);margin-bottom:2px}
</style>
@endpush

@section('content')
<section class="section">
    <div class="container" style="max-width:640px">
        <div class="drvp-card">
            <div class="drvp-banner"></div>
            <div class="drvp-head">
                <x-avatar :user="$driver" size="lg" />
                <div class="drvp-name">{{ $driver->name }}</div>
                @if ($avgOverall !== null)
                    <div class="drvp-overall"><x-icon name="star" /> {{ number_format($avgOverall, 1) }} / 5</div>
                @endif
            </div>
            <div class="drvp-stats">
                <div class="drvp-stat"><span class="n">{{ $completedTrips }}</span><span class="l">Perjalanan Selesai</span></div>
                <div class="drvp-stat"><span class="n">{{ $reviews->total() }}</span><span class="l">Ulasan</span></div>
            </div>
            @if ($avgOverall !== null)
                <div class="drvp-breakdown">
                    <div class="row"><span class="k">Ketepatan Waktu</span><span class="v">{{ $avgPunctuality }}</span></div>
                    <div class="row"><span class="k">Kebersihan</span><span class="v">{{ $avgCleanliness }}</span></div>
                    <div class="row"><span class="k">Keramahan</span><span class="v">{{ $avgFriendliness }}</span></div>
                    <div class="row"><span class="k">Keamanan</span><span class="v">{{ $avgSafety }}</span></div>
                </div>
            @endif
        </div>

        @forelse ($reviews as $review)
            <div class="drvp-card">
                <div class="drvp-review">
                    <div class="stars" aria-label="{{ $review->rating_overall }} dari 5">
                        @for ($i = 0; $i < round($review->rating_overall); $i++)<x-icon name="star" />@endfor
                    </div>
                    <div class="who">{{ $review->maskedCustomerName() }}</div>
                    @if ($review->comment)
                        <p style="color:var(--graphite);margin:0">{{ $review->comment }}</p>
                    @endif
                    @if ($review->admin_reply)
                        <div class="drvp-reply">
                            <div class="lbl">Balasan dari Lajur</div>
                            <p style="margin:0">{{ $review->admin_reply }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="drvp-card">
                <div class="drvp-review" style="text-align:center;color:var(--graphite)">Belum ada ulasan untuk driver ini.</div>
            </div>
        @endforelse

        @if ($reviews->hasPages())
            {{ $reviews->links() }}
        @endif
    </div>
</section>
@endsection
```

- [ ] **Step 6: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Feature/PublicDriverProfileTest.php`
Expected: `Tests: 4 passed`

- [ ] **Step 7: Tautkan dari halaman Lacak Pesanan** — di `resources/views/tracking/show.blade.php`, ubah baris judul panel ulasan driver dari:

```blade
                    <div class="panel-head"><h2>Ulasan untuk {{ $booking->driver->name }}</h2></div>
```

menjadi:

```blade
                    <div class="panel-head">
                        <h2>Ulasan untuk {{ $booking->driver->name }}</h2>
                        <a href="{{ route('driver.public-profile', $booking->driver_id) }}" class="tag">Lihat Profil Driver</a>
                    </div>
```

- [ ] **Step 8: Tautkan dari halaman Pantau Perjalanan** — di `resources/views/tracking/watch.blade.php`, cari baris:

```blade
                <div class="detail-item"><div class="k">Pengemudi</div><div class="v">{{ $booking->driver?->name ?? 'Belum ditentukan' }}</div></div>
```

Ganti jadi:

```blade
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
```

- [ ] **Step 9: Jalankan full suite**

Run: `php artisan test`
Expected: semua hijau

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/PublicDriverProfileController.php resources/views/driver/public-profile.blade.php routes/web.php resources/views/tracking/show.blade.php resources/views/tracking/watch.blade.php tests/Feature/PublicDriverProfileTest.php
git commit -m "feat: halaman profil publik driver + tautan dari Lacak Pesanan/Pantau"
```

---

### Task 7: Moderasi admin — panel "Ulasan Driver"

**Files:**
- Create: `app/Http/Controllers/Admin/DriverReviewController.php`
- Create: `resources/views/admin/driver-reviews/index.blade.php`
- Modify: `routes/web.php` (4 route baru dalam grup admin yang sudah ada + import)
- Modify: `resources/views/layouts/admin.blade.php` (item menu baru)
- Test: `tests/Feature/AdminDriverReviewModerationTest.php`

**Interfaces:**
- Consumes: `DriverReview` model (Task 1).

- [ ] **Step 1: Tulis test (gagal dulu)**

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\DriverReview;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDriverReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
        $this->owner = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id', 'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true]);
    }

    private function makeReview(string $status = 'pending'): DriverReview
    {
        $driver = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Driver Uji', 'email' => 'd-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);
        $booking = Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
            'booking_code' => Booking::generateBookingCode(),
        ]);

        return DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $driver->id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5,
            'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => $status,
        ]);
    }

    public function test_owner_can_view_review_list(): void
    {
        $this->makeReview();

        $this->actingAs($this->owner)->get('/admin/ulasan-driver')->assertOk();
    }

    public function test_owner_can_approve_a_pending_review(): void
    {
        $review = $this->makeReview('pending');

        $this->actingAs($this->owner)
            ->patch("/admin/ulasan-driver/{$review->id}/approve")
            ->assertRedirect();

        $this->assertSame('published', $review->fresh()->status);
    }

    public function test_owner_can_reject_a_review(): void
    {
        $review = $this->makeReview('pending');

        $this->actingAs($this->owner)
            ->patch("/admin/ulasan-driver/{$review->id}/reject")
            ->assertRedirect();

        $this->assertSame('rejected', $review->fresh()->status);
    }

    public function test_owner_can_reply_to_a_review(): void
    {
        $review = $this->makeReview('published');

        $this->actingAs($this->owner)
            ->patch("/admin/ulasan-driver/{$review->id}/reply", ['admin_reply' => 'Terima kasih atas ulasannya!'])
            ->assertRedirect();

        $review->refresh();
        $this->assertSame('Terima kasih atas ulasannya!', $review->admin_reply);
        $this->assertNotNull($review->replied_at);
    }

    public function test_admin_cannot_moderate_review_from_another_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-tenant']);
        app(TenantManager::class)->set($other);
        $foreignReview = $this->makeReview('pending'); // dibuat di bawah konteks tenant 'other'

        app(TenantManager::class)->set($this->tenant);
        $this->actingAs($this->owner)
            ->patch("/admin/ulasan-driver/{$foreignReview->id}/approve")
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/AdminDriverReviewModerationTest.php`
Expected: FAIL — route `/admin/ulasan-driver` belum ada (404)

- [ ] **Step 3: Tulis controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverReviewController extends Controller
{
    public function index(): View
    {
        $reviews = DriverReview::query()
            ->with('booking', 'driver')
            ->orderByRaw("FIELD(status, 'pending', 'published', 'rejected')")
            ->latest()
            ->paginate(15);

        return view('admin.driver-reviews.index', compact('reviews'));
    }

    public function approve(DriverReview $driverReview): RedirectResponse
    {
        $driverReview->update(['status' => 'published']);

        return back()->with('success', 'Ulasan diterbitkan ke profil driver.');
    }

    public function reject(DriverReview $driverReview): RedirectResponse
    {
        $driverReview->update(['status' => 'rejected']);

        return back()->with('success', 'Ulasan ditolak dan tidak akan tampil publik.');
    }

    public function reply(Request $request, DriverReview $driverReview): RedirectResponse
    {
        $data = $request->validate([
            'admin_reply' => ['required', 'string', 'max:1000'],
        ], [], ['admin_reply' => 'balasan']);

        $driverReview->update(['admin_reply' => $data['admin_reply'], 'replied_at' => now()]);

        return back()->with('success', 'Balasan tersimpan.');
    }
}
```

- [ ] **Step 4: Tambah route** — buka `routes/web.php`, tambah import:

```php
use App\Http\Controllers\Admin\DriverReviewController as AdminDriverReviewController;
```

Tambahkan di dalam grup `admin` (setelah baris `Route::resource('testimonials', TestimonialController::class)->except('show');`):

```php
        // Ulasan Driver (moderasi)
        Route::get('ulasan-driver', [AdminDriverReviewController::class, 'index'])->name('driver-reviews.index');
        Route::patch('ulasan-driver/{driverReview}/approve', [AdminDriverReviewController::class, 'approve'])->name('driver-reviews.approve');
        Route::patch('ulasan-driver/{driverReview}/reject', [AdminDriverReviewController::class, 'reject'])->name('driver-reviews.reject');
        Route::patch('ulasan-driver/{driverReview}/reply', [AdminDriverReviewController::class, 'reply'])->name('driver-reviews.reply');
```

- [ ] **Step 5: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Feature/AdminDriverReviewModerationTest.php`
Expected: `Tests: 5 passed`

- [ ] **Step 6: Tulis view index**

```blade
@extends('layouts.admin')

@section('title', 'Ulasan Driver')
@section('crumb', 'Manajemen')
@section('heading', 'Ulasan Driver')

@section('content')
    @php
        $statusPill = ['pending' => 'pill-pending', 'published' => 'pill-yes', 'rejected' => 'pill-no'];
        $statusLabel = ['pending' => 'Menunggu', 'published' => 'Terbit', 'rejected' => 'Ditolak'];
    @endphp
    <div class="panel">
        <div class="panel-head">
            <h2>Daftar Ulasan</h2>
            <span class="tag">{{ $reviews->total() }} entri</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Penilai</th>
                        <th>Rating</th>
                        <th>Komentar</th>
                        <th>Status</th>
                        <th style="text-align:right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($reviews as $r)
                    <tr>
                        <td><div class="nm">{{ $r->driver?->name ?? '—' }}</div></td>
                        <td>{{ $r->maskedCustomerName() }}</td>
                        <td>
                            <span class="stars-static" aria-label="{{ $r->rating_overall }} bintang">
                                @for ($i = 0; $i < round($r->rating_overall); $i++)<x-icon name="star" />@endfor
                            </span>
                        </td>
                        <td style="max-width:260px;color:var(--graphite)">{{ \Illuminate\Support\Str::limit($r->comment, 70) ?: '—' }}</td>
                        <td><span class="pill {{ $statusPill[$r->status] ?? '' }}">{{ $statusLabel[$r->status] ?? $r->status }}</span></td>
                        <td>
                            <div class="row-actions">
                                @if ($r->status !== 'published')
                                    <form action="{{ route('admin.driver-reviews.approve', $r) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="icon-btn" aria-label="Setujui"><x-icon name="check" /></button>
                                    </form>
                                @endif
                                @if ($r->status !== 'rejected')
                                    <form action="{{ route('admin.driver-reviews.reject', $r) }}" method="POST" data-confirm="Tolak ulasan ini? Tidak akan tampil di profil driver.">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="icon-btn danger" aria-label="Tolak"><x-icon name="close" /></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6" style="padding-top:0;border-top:0">
                            <details>
                                <summary style="cursor:pointer;color:var(--petrol-600);font-size:.86rem">
                                    {{ $r->admin_reply ? 'Ubah balasan' : 'Balas ulasan' }}
                                </summary>
                                <form action="{{ route('admin.driver-reviews.reply', $r) }}" method="POST" style="margin-top:8px;display:flex;gap:8px;align-items:flex-start">
                                    @csrf @method('PATCH')
                                    <textarea class="input" name="admin_reply" rows="2" maxlength="1000" placeholder="Tulis balasan...">{{ $r->admin_reply }}</textarea>
                                    <button type="submit" class="btn btn-ghost btn-sm">Simpan</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row">Belum ada ulasan driver.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($reviews->hasPages())
            {{ $reviews->links() }}
        @endif
    </div>
@endsection
```

- [ ] **Step 7: Tambah item menu di sidebar admin** — buka `resources/views/layouts/admin.blade.php`, tambahkan persis setelah link testimoni yang sudah ada:

```blade
            <a href="{{ route('admin.driver-reviews.index') }}" class="{{ request()->routeIs('admin.driver-reviews.*') ? 'active' : '' }}">
                <x-icon name="star" /> Ulasan Driver
            </a>
```

- [ ] **Step 8: Jalankan full suite**

Run: `php artisan test`
Expected: semua hijau

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/DriverReviewController.php resources/views/admin/driver-reviews/index.blade.php routes/web.php resources/views/layouts/admin.blade.php tests/Feature/AdminDriverReviewModerationTest.php
git commit -m "feat: panel admin moderasi Ulasan Driver (approve/reject/balas)"
```

---

### Task 8: Badge rating di dashboard & profil driver

**Files:**
- Modify: `app/Http/Controllers/Driver/DriverDashboardController.php` (eager-load ulasan pada `$past`)
- Modify: `resources/views/driver/dashboard.blade.php` (badge bintang di kartu riwayat)
- Modify: `app/Http/Controllers/Driver/DriverProfileController.php` (tambah rata-rata rating)
- Modify: `resources/views/driver/profile.blade.php` (stat tile "Rating")
- Test: `tests/Feature/DriverDashboardRatingBadgeTest.php`

**Interfaces:**
- Consumes: `Booking->driverReview` (Task 1), `DriverReview::published()` (Task 1).

- [ ] **Step 1: Tulis test (gagal dulu)**

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\DriverReview;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverDashboardRatingBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriver(): User
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        return User::create(['tenant_id' => $tenant->id, 'name' => 'Driver Joni', 'email' => 'joni-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);
    }

    private function makeCompletedBooking(User $driver, string $carLabel): Booking
    {
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);

        return Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $carLabel,
            'customer_name' => 'Budi', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => now()->subDays(5)->toDateString(), 'end_date' => now()->subDays(3)->toDateString(),
            'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
            'booking_code' => Booking::generateBookingCode(),
        ]);
    }

    public function test_published_rating_badge_shows_on_past_task_card(): void
    {
        $driver = $this->makeDriver();
        $booking = $this->makeCompletedBooking($driver, 'MobilDiulas');
        DriverReview::create(['booking_id' => $booking->id, 'driver_id' => $driver->id, 'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'published']);

        $this->actingAs($driver)->get('/driver')
            ->assertOk()
            ->assertSee('MobilDiulas')
            ->assertSee('5.0');
    }

    public function test_pending_review_does_not_show_badge(): void
    {
        $driver = $this->makeDriver();
        $booking = $this->makeCompletedBooking($driver, 'MobilPending');
        DriverReview::create(['booking_id' => $booking->id, 'driver_id' => $driver->id, 'rating_punctuality' => 3, 'rating_cleanliness' => 3, 'rating_friendliness' => 3, 'rating_safety' => 3, 'rating_overall' => 3.0, 'status' => 'pending']);

        $response = $this->actingAs($driver)->get('/driver');
        $response->assertOk()->assertSee('MobilPending');
        $response->assertDontSee('3.0');
    }

    public function test_profile_page_shows_average_rating(): void
    {
        $driver = $this->makeDriver();
        $b1 = $this->makeCompletedBooking($driver, 'A');
        $b2 = $this->makeCompletedBooking($driver, 'B');
        DriverReview::create(['booking_id' => $b1->id, 'driver_id' => $driver->id, 'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5, 'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'published']);
        DriverReview::create(['booking_id' => $b2->id, 'driver_id' => $driver->id, 'rating_punctuality' => 3, 'rating_cleanliness' => 3, 'rating_friendliness' => 3, 'rating_safety' => 3, 'rating_overall' => 3.0, 'status' => 'published']);

        $this->actingAs($driver)->get('/driver/profil')
            ->assertOk()
            ->assertSee('4.0'); // rata-rata (5.0 + 3.0) / 2
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/DriverDashboardRatingBadgeTest.php`
Expected: FAIL — badge/rata-rata belum ditampilkan

- [ ] **Step 3: Eager-load ulasan di `DriverDashboardController`**

Buka `app/Http/Controllers/Driver/DriverDashboardController.php`, ubah query `$past` agar memuat relasi `driverReview`:

```php
        $past = (clone $base)
            ->with('driverReview')
            ->where(fn ($q) => $q->whereDate('end_date', '<', $today)
                ->orWhereIn('status', ['completed', 'cancelled']))
            ->latest('start_date')
            ->take(10)
            ->get();
```

(baris `$upcoming` di atasnya tidak perlu diubah — ulasan hanya relevan untuk riwayat/tugas selesai.)

- [ ] **Step 4: Tambah badge di kartu riwayat** — buka `resources/views/driver/dashboard.blade.php`, ubah blok kartu riwayat dari:

```blade
                <div class="drv-side">
                    <span class="pill {{ $statusColors[$b->status] ?? '' }}">{{ $b->status_label }}</span>
                </div>
```

(di dalam `@foreach ($past as $b)`) menjadi:

```blade
                <div class="drv-side">
                    <span class="pill {{ $statusColors[$b->status] ?? '' }}">{{ $b->status_label }}</span>
                    @if ($b->driverReview && $b->driverReview->status === 'published')
                        <span class="pill" style="background:var(--amber-glow);color:var(--amber-600)">
                            <x-icon name="star" style="width:13px;height:13px" /> {{ number_format($b->driverReview->rating_overall, 1) }}
                        </span>
                    @endif
                </div>
```

- [ ] **Step 5: Tambah rata-rata rating di `DriverProfileController`**

Buka `app/Http/Controllers/Driver/DriverProfileController.php`, tambahkan import baru setelah `use App\Models\Booking;`:

```php
use App\Models\DriverReview;
```

Lalu tambahkan sebelum `return view(...)`:

```php
        $avgRating = DriverReview::published()
            ->where('driver_id', $driver->id)
            ->avg('rating_overall');
```

Lalu tambahkan `'avgRating' => $avgRating !== null ? round((float) $avgRating, 1) : null,` ke array data yang dikirim ke `compact('driver', 'completedTrips', 'activeTrips')` — ganti baris `return view('driver.profile', compact('driver', 'completedTrips', 'activeTrips'));` menjadi:

```php
        return view('driver.profile', [
            'driver' => $driver,
            'completedTrips' => $completedTrips,
            'activeTrips' => $activeTrips,
            'avgRating' => $avgRating !== null ? round((float) $avgRating, 1) : null,
        ]);
```

- [ ] **Step 6: Tambah stat tile "Rating" di halaman profil driver** — buka `resources/views/driver/profile.blade.php`, ubah blok `.prof-stats` dari:

```blade
        <div class="prof-stats">
            <div class="prof-stat">
                <span class="n">{{ $activeTrips }}</span>
                <span class="l">Tugas Aktif</span>
            </div>
            <div class="prof-stat">
                <span class="n">{{ $completedTrips }}</span>
                <span class="l">Perjalanan Selesai</span>
            </div>
        </div>
```

menjadi (3 kolom):

```blade
        <div class="prof-stats" style="grid-template-columns:repeat(3,1fr)">
            <div class="prof-stat">
                <span class="n">{{ $activeTrips }}</span>
                <span class="l">Tugas Aktif</span>
            </div>
            <div class="prof-stat">
                <span class="n">{{ $completedTrips }}</span>
                <span class="l">Perjalanan Selesai</span>
            </div>
            <div class="prof-stat">
                <span class="n">{{ $avgRating !== null ? number_format($avgRating, 1) : '—' }}</span>
                <span class="l">Rating</span>
            </div>
        </div>
```

- [ ] **Step 7: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Feature/DriverDashboardRatingBadgeTest.php`
Expected: `Tests: 3 passed`

- [ ] **Step 8: Jalankan full suite final**

Run: `php artisan test`
Expected: semua hijau. Catat jumlah total (baseline 222 + test baru sesi ini).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Driver/DriverDashboardController.php resources/views/driver/dashboard.blade.php app/Http/Controllers/Driver/DriverProfileController.php resources/views/driver/profile.blade.php tests/Feature/DriverDashboardRatingBadgeTest.php
git commit -m "feat: badge rating di kartu riwayat & stat rating di profil driver"
```

---

### Task 9: Verifikasi visual di browser

**Files:** tidak ada file diubah — verifikasi manual.

- [ ] **Step 1: Pastikan server lokal jalan**

Run: `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/`
Expected: `200`

- [ ] **Step 2: Siapkan data uji lewat tinker** — buat 1 booking `completed` dengan driver, jalankan submit ulasan driver + testimoni lewat form sungguhan (bukan hanya lewat test), verifikasi:
  1. Halaman `/lacak/{kode}` menampilkan kedua form ulasan.
  2. Setelah submit, form berganti jadi "sedang ditinjau".
  3. Login admin, buka `/admin/ulasan-driver`, approve ulasan driver → cek `/pengemudi/{id}` menampilkan ulasan itu.
  4. Buka `/admin/testimonials`, centang "Tampilkan" pada testimoni baru → cek testimoni itu muncul di marquee landing page (`/`).
  5. Login sebagai driver itu, cek dashboard menampilkan badge rating di kartu riwayat, dan `/driver/profil` menampilkan rata-rata rating.
  6. Screenshot tiap langkah.

- [ ] **Step 3: Bersihkan data uji** (hapus booking/review percobaan lewat tinker) agar tidak mengotori data nyata.

- [ ] **Step 4: Laporkan hasil ke user** dengan ringkas, screenshot dilampirkan.

---

## Self-Review Checklist (dicek penulis plan, bukan pelaksana)

- **Cakupan spec**: Jalur A (model+submit+profil publik+moderasi+dashboard badge) ada di Task 1,4,5,6,7,8. Jalur B (testimonials.booking_id+submit+reuse admin) ada di Task 2,4,5. Kedua jalur diverifikasi end-to-end di Task 9. ✓
- **Konsistensi nama**: `DriverReview` field names (`rating_punctuality`, dst) sama persis di migration (Task 1), controller submit (Task 4), form (Task 5), profil publik (Task 6), admin (Task 7) — diperiksa satu per satu saat menulis.
- **Route names**: `driver-review.store`, `testimonial.store`, `driver.public-profile`, `admin.driver-reviews.{index,approve,reject,reply}` — dipakai konsisten di setiap task yang mereferensikannya (view maupun test).
- **Tidak ada placeholder/TBD**: seluruh kode di setiap step lengkap siap tempel.
- **Keamanan**: rute admin di dalam grup `auth+admin` yang sudah ada (tenant-scoped otomatis via `BelongsToTenant` pada `DriverReview` — route model binding akan 404 untuk ID tenant lain, diverifikasi Task 7 test `test_admin_cannot_moderate_review_from_another_tenant`). Rute publik `/pengemudi/{driver}` di-guard role di Task 6.
