# Dashboard Kelola Konten Landing Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Superadmin bisa mengedit semua teks di landing page induk (`/` domain pusat) dari satu form dashboard, tanpa menyentuh kode, dan tanpa risiko section kosong kalau field belum diisi.

**Architecture:** Satu baris data singleton (`landing_contents.content` JSON) diakses lewat class resolver `App\Content\LandingCopy` yang meniru pola `App\Tenancy\Branding` yang sudah ada di codebase ini — const `DEFAULTS` berisi salinan persis teks yang sekarang hardcode, getter per field/grup melakukan fallback ke default kalau tersimpan kosong/tak ada (merge per-item untuk grup array, bukan all-or-nothing). Form superadmin di `resources/views/superadmin/landing/edit.blade.php` menulis JSON via `SuperAdmin\LandingContentController`. `landing.blade.php` diubah agar semua string literal diganti pemanggilan `$copy->method()` — struktur HTML/CSS/animasi/ikon sama sekali tidak berubah.

**Tech Stack:** Laravel 11 (Blade, Eloquent), PHPUnit (`php artisan test`), tidak ada dependency baru.

## Global Constraints

- Cakupan: **teks saja**. Ikon, gambar produk, jumlah kartu per section, urutan section — TIDAK bisa diubah dari dashboard (tetap hardcode di kode).
- Cakupan: **landing page saja** (`/` di domain pusat, file `resources/views/landing.blade.php`). Halaman lain (pilih paket, syarat, privasi) di luar cakupan.
- Tombol CTA "Coba Gratis 14 Hari" (muncul 5×) dan eyebrow "Fitur unggulan" (muncul 3×, di ketiga section sorotan produk) masing-masing **satu pengaturan** dipakai berulang — bukan field terpisah per kemunculan.
- Field kosong di database HARUS fallback ke teks default, per-item untuk grup array (mengisi kartu #2 dari 5 tidak boleh mengosongkan 4 kartu lain).
- Semua teks disimpan **plain text tanpa entitas HTML** (tidak ada `&amp;`), ditampilkan dengan `{{ }}` (escaped) di semua tempat — tidak ada `{!! !!}` untuk field yang berasal dari `$copy`. Satu pengecualian: judul ecosystem 2 baris dipecah jadi 2 field terpisah (`ecosystem_title_line1`/`line2`) supaya `<br>` di antaranya tetap hardcode di template, bukan disisipkan dari data.
- Route baru ada di grup existing `Route::prefix('superadmin')->middleware(['auth','role:super_admin'])` di `routes/web.php` (sekitar baris 212-222) — JANGAN buat middleware/grup baru.
- Sebelum mengklaim task selesai, jalankan `php artisan test` dan pastikan hijau (baseline sebelum plan ini: 204 passed).
- Jangan `git push`. Commit lokal saja per task selesai.

---

### Task 1: Migration + model `LandingContent`

**Files:**
- Create: `database/migrations/2026_07_20_090000_create_landing_contents_table.php`
- Create: `app/Models/LandingContent.php`
- Test: `tests/Unit/LandingContentTest.php`

**Interfaces:**
- Produces: `LandingContent::current(): array` — dipakai Task 2 (`LandingCopy`) dan Task 3 (`LandingContentController`).

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
        Schema::create('landing_contents', function (Blueprint $table) {
            $table->id();
            $table->json('content')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_contents');
    }
};
```

- [ ] **Step 2: Jalankan migration**

Run: `php artisan migrate`
Expected: `2026_07_20_090000_create_landing_contents_table ... DONE`

- [ ] **Step 3: Tulis test model (gagal dulu — model belum ada)**

```php
<?php

namespace Tests\Unit;

use App\Models\LandingContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_empty_array_when_no_row_exists(): void
    {
        $this->assertSame([], LandingContent::current());
    }

    public function test_current_returns_stored_content(): void
    {
        LandingContent::create(['id' => 1, 'content' => ['hero_title_lead' => 'Judul Baru']]);

        $this->assertSame(['hero_title_lead' => 'Judul Baru'], LandingContent::current());
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan gagal (class belum ada)**

Run: `php artisan test tests/Unit/LandingContentTest.php`
Expected: FAIL — `Class "App\Models\LandingContent" not found`

- [ ] **Step 5: Tulis model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingContent extends Model
{
    protected $fillable = ['content'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /** Data tersimpan (baris tunggal id=1), array kosong bila belum pernah disimpan. */
    public static function current(): array
    {
        return static::query()->find(1)?->content ?? [];
    }
}
```

- [ ] **Step 6: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Unit/LandingContentTest.php`
Expected: `Tests: 2 passed`

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_20_090000_create_landing_contents_table.php app/Models/LandingContent.php tests/Unit/LandingContentTest.php
git commit -m "feat: tabel & model singleton landing_contents"
```

---

### Task 2: Class `LandingCopy` (default + fallback per field)

**Files:**
- Create: `app/Content/LandingCopy.php`
- Test: `tests/Unit/LandingCopyTest.php`

**Interfaces:**
- Consumes: array data (bentuk `LandingContent::current()` dari Task 1).
- Produces: 45 method getter publik dipakai Task 4 (view admin, sebagai nilai placeholder default) dan Task 6 (`landing.blade.php`). Signature lengkap:
  - `heroEyebrow(): string`, `heroTitleLead(): string`, `heroTitleReveal(): string`, `heroSubtitle(): string`
  - `ctaLabel(): string`, `spotlightEyebrow(): string`
  - `trustLead(): string`, `trustItems(): array` (4 string)
  - `painEyebrow(): string`, `painTitle(): string`, `painSubtitle(): string`, `painItems(): array` (5× `['title'=>string,'text'=>string]`), `painClosing(): string`
  - `beforeItems(): array` (4 string), `afterBrand(): string`, `afterText(): string`
  - `featuresTitle(): string`, `featuresSubtitle(): string`, `featureGroups(): array` (4× `['title'=>string,'items'=>array<string>]`)
  - `spotlightFuelTitle(): string`, `spotlightFuelText(): string`
  - `spotlightDriverTitle(): string`, `spotlightDriverText(): string`
  - `familyTitle(): string`, `familySubtitle(): string`, `familySteps(): array` (4× `['title'=>string,'text'=>string]`)
  - `spotlightStorefrontTitle(): string`, `spotlightStorefrontText(): string`
  - `gpsBadge(): string`, `gpsTitle(): string`, `gpsText(): string`, `gpsNote(): string`
  - `whyTitle(): string`, `whyItems(): array` (6× `['title'=>string,'text'=>string]`)
  - `workflowTitle(): string`, `workflowSteps(): array` (5× `['title'=>string,'text'=>string]`)
  - `ecosystemTitleLine1(): string`, `ecosystemTitleLine2(): string`, `ecosystemSubtitle(): string`, `ecosystemItems(): array` (9 string), `ecosystemCaption(): string`
  - `pricingTitle(): string`, `pricingSubtitle(): string`
  - `ctaTitle(): string`, `ctaText(): string`, `ctaTrustItems(): array` (3 string)
  - Static: `LandingCopy::DEFAULTS` (const array, dipakai Task 4 untuk `placeholder`)

- [ ] **Step 1: Tulis test (gagal dulu)**

```php
<?php

namespace Tests\Unit;

use App\Content\LandingCopy;
use PHPUnit\Framework\TestCase;

class LandingCopyTest extends TestCase
{
    public function test_returns_defaults_when_no_data_stored(): void
    {
        $copy = new LandingCopy([]);

        $this->assertSame('Kelola seluruh operasional armada', $copy->heroTitleLead());
        $this->assertSame('Coba Gratis 14 Hari', $copy->ctaLabel());
        $this->assertCount(5, $copy->painItems());
        $this->assertSame('Sulit tahu posisi kendaraan', $copy->painItems()[0]['title']);
    }

    public function test_overrides_single_field_without_losing_others(): void
    {
        $copy = new LandingCopy(['hero_title_lead' => 'Judul Kustom']);

        $this->assertSame('Judul Kustom', $copy->heroTitleLead());
        $this->assertSame('dalam satu platform.', $copy->heroTitleReveal()); // tetap default
    }

    public function test_overrides_single_item_in_a_group_without_losing_siblings(): void
    {
        $copy = new LandingCopy([
            'pain_items' => [
                1 => ['title' => 'Judul Baru Item Kedua'],
            ],
        ]);

        $items = $copy->painItems();
        $this->assertSame('Sulit tahu posisi kendaraan', $items[0]['title']); // item 0 tetap default
        $this->assertSame('Judul Baru Item Kedua', $items[1]['title']); // item 1 diganti
        $this->assertSame('Rekap bulanan baru jadi tanggal 10. Keputusan diambil pakai firasat.', $items[1]['text']); // text item 1 tetap default (tak dikirim)
    }

    public function test_empty_string_treated_same_as_missing(): void
    {
        $copy = new LandingCopy(['cta_label' => '']);

        $this->assertSame('Coba Gratis 14 Hari', $copy->ctaLabel());
    }

    public function test_simple_list_override_per_index(): void
    {
        $copy = new LandingCopy(['trust_items' => [0 => 'Label Baru']]);

        $items = $copy->trustItems();
        $this->assertSame('Label Baru', $items[0]);
        $this->assertSame('Platform cloud', $items[1]); // index 1 tetap default
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/LandingCopyTest.php`
Expected: FAIL — `Class "App\Content\LandingCopy" not found`

- [ ] **Step 3: Tulis class lengkap**

```php
<?php

namespace App\Content;

/**
 * Resolusi teks landing page induk dari data tersimpan (JSON singleton
 * LandingContent), fallback ke DEFAULTS bila kosong/tak ada — meniru pola
 * App\Tenancy\Branding. Field kosong/'' dianggap "tak diisi", per-item untuk
 * grup array (edit satu kartu tidak mengosongkan kartu lain).
 */
class LandingCopy
{
    public const DEFAULTS = [
        'hero_eyebrow' => 'Untuk pemilik usaha rental mobil',
        'hero_title_lead' => 'Kelola seluruh operasional armada',
        'hero_title_reveal' => 'dalam satu platform.',
        'hero_subtitle' => 'Pantau kendaraan, pengemudi, booking, BBM, hingga laporan operasional secara real-time dalam satu dashboard.',
        'cta_label' => 'Coba Gratis 14 Hari',
        'spotlight_eyebrow' => 'Fitur unggulan',
        'trust_lead' => 'Dibangun untuk membantu operasional bisnis rental di Indonesia',
        'trust_items' => ['Monitoring real-time', 'Platform cloud', 'Ramah di HP', 'Support Indonesia'],
        'pain_eyebrow' => 'Kenapa Lajur',
        'pain_title' => 'Masih mengelola armada secara manual?',
        'pain_subtitle' => 'Kalau salah satunya terasa akrab, Anda tidak sendirian. Ini keluhan yang paling sering kami dengar dari pemilik rental.',
        'pain_items' => [
            ['title' => 'Sulit tahu posisi kendaraan', 'text' => 'Mobil disewa keluar kota, kabarnya hanya dari telepon sopir.'],
            ['title' => 'Laporan selalu terlambat', 'text' => 'Rekap bulanan baru jadi tanggal 10. Keputusan diambil pakai firasat.'],
            ['title' => 'Sulit mengontrol driver dan BBM', 'text' => 'Sopir isi 50 ribu, lapor 100 ribu. Sebulan hilang jutaan tanpa jejak.'],
            ['title' => 'Booking masih lewat chat', 'text' => 'Pesanan tercecer di WhatsApp. Dua penyewa, mobil sama, hari sama.'],
            ['title' => 'Data operasional tersebar', 'text' => 'Jadwal di buku, keuangan di Excel, kontak di HP. Tidak ada yang utuh.'],
        ],
        'pain_closing' => 'Lajur menyelesaikan semuanya.',
        'before_items' => ['Excel dan buku catatan', 'Booking via chat', 'Telepon satu per satu', 'Nota dan kertas tercecer'],
        'after_brand' => 'Lajur Platform',
        'after_text' => 'Booking, armada, driver, BBM, dan laporan. Satu login, semua terhubung.',
        'features_title' => 'Semua yang Anda butuh untuk kelola rental',
        'features_subtitle' => 'Empat area kerja, satu dashboard.',
        'feature_groups' => [
            ['title' => 'Operasional', 'items' => ['Booking dan kode unik per pesanan', 'Kalender armada anti-tabrakan', 'Penugasan dan jadwal driver', 'Tujuan perjalanan per booking']],
            ['title' => 'Monitoring', 'items' => ['BBM anti-kebocoran, ditandai otomatis', 'Laporan pendapatan dan utilisasi', 'Export PDF / Excel', 'GPS live di peta']],
            ['title' => 'Produktivitas', 'items' => ['Asisten AI: tanya angka bisnis, dijawab dari data', 'Biaya per km dan konsumsi terhitung sendiri', 'Dashboard ringkas untuk keputusan cepat']],
            ['title' => 'Pengalaman Pelanggan', 'items' => ['Etalase booking online milik Anda sendiri', 'Lacak pesanan dengan kode booking', 'Keluarga ikut memantau perjalanan']],
        ],
        'spotlight_fuel_title' => 'BBM yang bocor, langsung ketahuan',
        'spotlight_fuel_text' => 'Sopir isi 60 liter ke tangki yang cuma muat 45? Konsumsi tiba-tiba boros dua kali lipat? Lajur hitung sendiri dari tiap catatan pengisian dan menandainya merah. Anda tak perlu memeriksa satu per satu.',
        'spotlight_driver_title' => 'Sopir tak perlu tanya alamat lagi',
        'spotlight_driver_text' => 'Isi lokasi tujuan sekali saat menugaskan driver, satu tombol Maps langsung muncul di HP sopir. Sekali tap, Google Maps terbuka dengan rute dari posisinya saat itu, tanpa telepon, tanpa dikte alamat lewat WhatsApp.',
        'family_title' => 'Keluarga juga bisa memantau perjalanan',
        'family_subtitle' => 'Setiap booking punya kode unik. Penyewa cukup membagikannya, dan keluarga di rumah ikut tenang.',
        'family_steps' => [
            ['title' => 'Booking dibuat', 'text' => 'Pesanan masuk, kode booking terbit otomatis.'],
            ['title' => 'Kode dibagikan', 'text' => 'Penyewa mengirim kode ke keluarganya.'],
            ['title' => 'Masukkan kode', 'text' => 'Buka halaman Lacak Pesanan, tanpa perlu akun.'],
            ['title' => 'Perjalanan terpantau', 'text' => 'Status dan detail perjalanan terlihat. Posisi live menyusul bersama GPS.'],
        ],
        'spotlight_storefront_title' => 'Situs booking sendiri, bukan skin generik',
        'spotlight_storefront_text' => 'Setiap tenant dapat etalase online sendiri: logo, warna aksen, dan alamat domain sendiri (namatenant.lajur.id). Pelanggan booking langsung dari situs itu, bukan chat WhatsApp yang mudah terlewat.',
        'gps_badge' => 'Segera hadir',
        'gps_title' => 'Mobil Anda, terlihat di peta',
        'gps_text' => 'Begitu alat GPS terpasang di unit, posisi tiap mobil tampil langsung di dashboard. Tak perlu lagi menelepon sopir untuk tanya "sudah sampai mana".',
        'gps_note' => 'Tampilan di samping adalah gambaran fitur yang sedang dalam pengembangan.',
        'why_title' => 'Kenapa memilih Lajur?',
        'why_items' => [
            ['title' => 'Setup cepat', 'text' => 'Akun jadi dalam hitungan menit, tanpa instalasi.'],
            ['title' => 'Berbasis cloud', 'text' => 'Buka dari mana saja, data tersimpan aman di server.'],
            ['title' => 'Ramah di HP', 'text' => 'Dashboard, driver, dan pelanggan nyaman diakses dari ponsel.'],
            ['title' => 'Aman', 'text' => 'Data bisnis terenkripsi dan terisolasi per tenant.'],
            ['title' => 'Real-time', 'text' => 'Booking masuk dan laporan terhitung saat itu juga.'],
            ['title' => 'Support Indonesia', 'text' => 'Tim lokal, respons cepat lewat WhatsApp.'],
        ],
        'workflow_title' => 'Dari daftar sampai go live, tidak ribet',
        'workflow_steps' => [
            ['title' => 'Daftar', 'text' => 'Buat akun dalam semenit, tanpa kartu kredit.'],
            ['title' => 'Coba dan demo', 'text' => 'Jelajahi dashboard dengan akses penuh 14 hari.'],
            ['title' => 'Setup data', 'text' => 'Masukkan mobil, tarif, dan driver. Kami bantu tiap langkah.'],
            ['title' => 'Training singkat', 'text' => 'Tim Anda dipandu lewat WhatsApp sampai lancar.'],
            ['title' => 'Go live', 'text' => 'Terima booking dan pantau operasional dari satu layar.'],
        ],
        'ecosystem_title_line1' => 'Bukan sekadar aplikasi rental.',
        'ecosystem_title_line2' => 'Ini platform operasional Anda.',
        'ecosystem_subtitle' => 'Seluruh data operasional terhubung dalam satu platform, dan terus bertumbuh bersama bisnis Anda.',
        'ecosystem_items' => ['Armada', 'Driver', 'Booking', 'Etalase pelanggan', 'BBM anti-kebocoran', 'Laporan dan export', 'Asisten AI', 'GPS live', 'Integrasi IoT'],
        'ecosystem_caption' => 'Juga dalam pengembangan: prediksi perawatan armada dan notifikasi pintar.',
        'pricing_title' => 'Harga jujur, tanpa kejutan',
        'pricing_subtitle' => 'Semua paket bisa dicoba gratis 14 hari dulu. Bayar bulanan, berhenti kapan saja.',
        'cta_title' => 'Siap mengelola armada lebih efisien?',
        'cta_text' => 'Coba semua fitur Lajur gratis 14 hari. Tanpa kartu kredit, tanpa risiko.',
        'cta_trust_items' => ['Data bisnis aman dan terenkripsi', 'Dukungan cepat via WhatsApp', 'Upgrade atau turun paket kapan saja'],
    ];

    public function __construct(private array $data)
    {
    }

    private function get(string $key): string
    {
        $val = $this->data[$key] ?? null;

        return ($val !== null && $val !== '') ? $val : self::DEFAULTS[$key];
    }

    /** Daftar string sederhana (mis. trust_items) — override per-index. */
    private function getList(string $key): array
    {
        $stored = $this->data[$key] ?? [];
        $result = [];
        foreach (self::DEFAULTS[$key] as $i => $default) {
            $val = $stored[$i] ?? null;
            $result[$i] = ($val !== null && $val !== '') ? $val : $default;
        }

        return $result;
    }

    /** Daftar grup ['title'=>..,'text'=>..] (mis. pain_items) — override per-item, per-field. */
    private function getGroups(string $key): array
    {
        $stored = $this->data[$key] ?? [];
        $result = [];
        foreach (self::DEFAULTS[$key] as $i => $defaultRow) {
            $storedRow = $stored[$i] ?? [];
            $row = [];
            foreach ($defaultRow as $field => $defaultVal) {
                $val = $storedRow[$field] ?? null;
                $row[$field] = ($val !== null && $val !== '') ? $val : $defaultVal;
            }
            $result[$i] = $row;
        }

        return $result;
    }

    public function heroEyebrow(): string { return $this->get('hero_eyebrow'); }
    public function heroTitleLead(): string { return $this->get('hero_title_lead'); }
    public function heroTitleReveal(): string { return $this->get('hero_title_reveal'); }
    public function heroSubtitle(): string { return $this->get('hero_subtitle'); }

    public function ctaLabel(): string { return $this->get('cta_label'); }
    public function spotlightEyebrow(): string { return $this->get('spotlight_eyebrow'); }

    public function trustLead(): string { return $this->get('trust_lead'); }
    public function trustItems(): array { return $this->getList('trust_items'); }

    public function painEyebrow(): string { return $this->get('pain_eyebrow'); }
    public function painTitle(): string { return $this->get('pain_title'); }
    public function painSubtitle(): string { return $this->get('pain_subtitle'); }
    public function painItems(): array { return $this->getGroups('pain_items'); }
    public function painClosing(): string { return $this->get('pain_closing'); }

    public function beforeItems(): array { return $this->getList('before_items'); }
    public function afterBrand(): string { return $this->get('after_brand'); }
    public function afterText(): string { return $this->get('after_text'); }

    public function featuresTitle(): string { return $this->get('features_title'); }
    public function featuresSubtitle(): string { return $this->get('features_subtitle'); }

    /** @return array<int, array{title:string, items:array<int,string>}> */
    public function featureGroups(): array
    {
        $stored = $this->data['feature_groups'] ?? [];
        $result = [];
        foreach (self::DEFAULTS['feature_groups'] as $gi => $defaultGroup) {
            $storedGroup = $stored[$gi] ?? [];
            $title = $storedGroup['title'] ?? null;
            $items = [];
            foreach ($defaultGroup['items'] as $ii => $defaultItem) {
                $val = $storedGroup['items'][$ii] ?? null;
                $items[$ii] = ($val !== null && $val !== '') ? $val : $defaultItem;
            }
            $result[$gi] = [
                'title' => ($title !== null && $title !== '') ? $title : $defaultGroup['title'],
                'items' => $items,
            ];
        }

        return $result;
    }

    public function spotlightFuelTitle(): string { return $this->get('spotlight_fuel_title'); }
    public function spotlightFuelText(): string { return $this->get('spotlight_fuel_text'); }
    public function spotlightDriverTitle(): string { return $this->get('spotlight_driver_title'); }
    public function spotlightDriverText(): string { return $this->get('spotlight_driver_text'); }

    public function familyTitle(): string { return $this->get('family_title'); }
    public function familySubtitle(): string { return $this->get('family_subtitle'); }
    public function familySteps(): array { return $this->getGroups('family_steps'); }

    public function spotlightStorefrontTitle(): string { return $this->get('spotlight_storefront_title'); }
    public function spotlightStorefrontText(): string { return $this->get('spotlight_storefront_text'); }

    public function gpsBadge(): string { return $this->get('gps_badge'); }
    public function gpsTitle(): string { return $this->get('gps_title'); }
    public function gpsText(): string { return $this->get('gps_text'); }
    public function gpsNote(): string { return $this->get('gps_note'); }

    public function whyTitle(): string { return $this->get('why_title'); }
    public function whyItems(): array { return $this->getGroups('why_items'); }

    public function workflowTitle(): string { return $this->get('workflow_title'); }
    public function workflowSteps(): array { return $this->getGroups('workflow_steps'); }

    public function ecosystemTitleLine1(): string { return $this->get('ecosystem_title_line1'); }
    public function ecosystemTitleLine2(): string { return $this->get('ecosystem_title_line2'); }
    public function ecosystemSubtitle(): string { return $this->get('ecosystem_subtitle'); }
    public function ecosystemItems(): array { return $this->getList('ecosystem_items'); }
    public function ecosystemCaption(): string { return $this->get('ecosystem_caption'); }

    public function pricingTitle(): string { return $this->get('pricing_title'); }
    public function pricingSubtitle(): string { return $this->get('pricing_subtitle'); }

    public function ctaTitle(): string { return $this->get('cta_title'); }
    public function ctaText(): string { return $this->get('cta_text'); }
    public function ctaTrustItems(): array { return $this->getList('cta_trust_items'); }
}
```

- [ ] **Step 4: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Unit/LandingCopyTest.php`
Expected: `Tests: 5 passed`

- [ ] **Step 5: Commit**

```bash
git add app/Content/LandingCopy.php tests/Unit/LandingCopyTest.php
git commit -m "feat: LandingCopy — resolusi teks landing dgn fallback default per-item"
```

---

### Task 3: Controller, route, menu superadmin

**Files:**
- Create: `app/Http/Controllers/SuperAdmin/LandingContentController.php`
- Modify: `routes/web.php:212-222` (tambah 2 baris di grup superadmin yang sudah ada)
- Modify: `resources/views/layouts/superadmin.blade.php` (tambah 1 link nav)
- Test: `tests/Feature/SuperAdminLandingContentTest.php`

**Interfaces:**
- Consumes: `App\Models\LandingContent::current()` (Task 1), `App\Content\LandingCopy` (Task 2).
- Produces: route `superadmin.landing.edit` (GET `superadmin/konten-landing`), `superadmin.landing.update` (PATCH sama), dipakai Task 4 (view) dan test end-to-end Task 7.

- [ ] **Step 1: Tulis test (gagal dulu)**

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminLandingContentTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@lajur.id',
            'password' => 'password', 'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_super_admin_can_view_edit_form(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/superadmin/konten-landing')
            ->assertOk()
            ->assertSee('Kelola seluruh operasional armada'); // placeholder default hero
    }

    public function test_non_super_admin_cannot_view_edit_form(): void
    {
        $tenant = Tenant::create(['name' => 'Lajur', 'slug' => 'lajur', 'plan' => 'business', 'subscription_status' => 'active']);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/superadmin/konten-landing')->assertForbidden();
    }

    public function test_non_super_admin_cannot_update_content(): void
    {
        $tenant = Tenant::create(['name' => 'Lajur', 'slug' => 'lajur', 'plan' => 'business', 'subscription_status' => 'active']);
        $owner = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)
            ->patch('/superadmin/konten-landing', ['hero_title_lead' => 'Hack'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/SuperAdminLandingContentTest.php`
Expected: FAIL — route `superadmin/konten-landing` tidak ada (404)

- [ ] **Step 3: Tulis controller**

```php
<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Content\LandingCopy;
use App\Http\Controllers\Controller;
use App\Models\LandingContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingContentController extends Controller
{
    public function edit(): View
    {
        $stored = LandingContent::current();
        $copy = new LandingCopy($stored);

        return view('superadmin.landing.edit', compact('stored', 'copy'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hero_eyebrow' => ['nullable', 'string', 'max:150'],
            'hero_title_lead' => ['nullable', 'string', 'max:150'],
            'hero_title_reveal' => ['nullable', 'string', 'max:150'],
            'hero_subtitle' => ['nullable', 'string', 'max:400'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'spotlight_eyebrow' => ['nullable', 'string', 'max:60'],
            'trust_lead' => ['nullable', 'string', 'max:200'],
            'trust_items' => ['nullable', 'array'],
            'trust_items.*' => ['nullable', 'string', 'max:80'],
            'pain_eyebrow' => ['nullable', 'string', 'max:60'],
            'pain_title' => ['nullable', 'string', 'max:150'],
            'pain_subtitle' => ['nullable', 'string', 'max:300'],
            'pain_items' => ['nullable', 'array'],
            'pain_items.*.title' => ['nullable', 'string', 'max:120'],
            'pain_items.*.text' => ['nullable', 'string', 'max:300'],
            'pain_closing' => ['nullable', 'string', 'max:150'],
            'before_items' => ['nullable', 'array'],
            'before_items.*' => ['nullable', 'string', 'max:80'],
            'after_brand' => ['nullable', 'string', 'max:80'],
            'after_text' => ['nullable', 'string', 'max:300'],
            'features_title' => ['nullable', 'string', 'max:150'],
            'features_subtitle' => ['nullable', 'string', 'max:200'],
            'feature_groups' => ['nullable', 'array'],
            'feature_groups.*.title' => ['nullable', 'string', 'max:80'],
            'feature_groups.*.items' => ['nullable', 'array'],
            'feature_groups.*.items.*' => ['nullable', 'string', 'max:150'],
            'spotlight_fuel_title' => ['nullable', 'string', 'max:150'],
            'spotlight_fuel_text' => ['nullable', 'string', 'max:600'],
            'spotlight_driver_title' => ['nullable', 'string', 'max:150'],
            'spotlight_driver_text' => ['nullable', 'string', 'max:600'],
            'family_title' => ['nullable', 'string', 'max:150'],
            'family_subtitle' => ['nullable', 'string', 'max:300'],
            'family_steps' => ['nullable', 'array'],
            'family_steps.*.title' => ['nullable', 'string', 'max:80'],
            'family_steps.*.text' => ['nullable', 'string', 'max:200'],
            'spotlight_storefront_title' => ['nullable', 'string', 'max:150'],
            'spotlight_storefront_text' => ['nullable', 'string', 'max:600'],
            'gps_badge' => ['nullable', 'string', 'max:40'],
            'gps_title' => ['nullable', 'string', 'max:150'],
            'gps_text' => ['nullable', 'string', 'max:400'],
            'gps_note' => ['nullable', 'string', 'max:200'],
            'why_title' => ['nullable', 'string', 'max:150'],
            'why_items' => ['nullable', 'array'],
            'why_items.*.title' => ['nullable', 'string', 'max:80'],
            'why_items.*.text' => ['nullable', 'string', 'max:200'],
            'workflow_title' => ['nullable', 'string', 'max:150'],
            'workflow_steps' => ['nullable', 'array'],
            'workflow_steps.*.title' => ['nullable', 'string', 'max:80'],
            'workflow_steps.*.text' => ['nullable', 'string', 'max:200'],
            'ecosystem_title_line1' => ['nullable', 'string', 'max:150'],
            'ecosystem_title_line2' => ['nullable', 'string', 'max:150'],
            'ecosystem_subtitle' => ['nullable', 'string', 'max:300'],
            'ecosystem_items' => ['nullable', 'array'],
            'ecosystem_items.*' => ['nullable', 'string', 'max:80'],
            'ecosystem_caption' => ['nullable', 'string', 'max:200'],
            'pricing_title' => ['nullable', 'string', 'max:150'],
            'pricing_subtitle' => ['nullable', 'string', 'max:300'],
            'cta_title' => ['nullable', 'string', 'max:150'],
            'cta_text' => ['nullable', 'string', 'max:300'],
            'cta_trust_items' => ['nullable', 'array'],
            'cta_trust_items.*' => ['nullable', 'string', 'max:100'],
        ]);

        LandingContent::query()->updateOrCreate(['id' => 1], ['content' => $data]);

        return redirect()->route('superadmin.landing.edit')
            ->with('success', 'Konten landing page disimpan.');
    }
}
```

- [ ] **Step 4: Tambah route** — buka `routes/web.php`, cari grup superadmin (sekitar baris 212), tambahkan 2 baris setelah baris `tenants.plan`:

```php
        Route::get('konten-landing', [SuperAdminLandingContentController::class, 'edit'])->name('landing.edit');
        Route::patch('konten-landing', [SuperAdminLandingContentController::class, 'update'])->name('landing.update');
```

Tambahkan juga import di bagian atas file (sejajar import `SuperAdminPlanController`/`SuperAdminTenantController` yang sudah ada):

```php
use App\Http\Controllers\SuperAdmin\LandingContentController as SuperAdminLandingContentController;
```

- [ ] **Step 5: Tambah item menu** — buka `resources/views/layouts/superadmin.blade.php`, tambahkan link baru persis setelah link `superadmin.tenants.index` yang sudah ada (baris ~26-28), pola sama:

```blade
            <a href="{{ route('superadmin.landing.edit') }}" class="{{ request()->routeIs('superadmin.landing.*') ? 'active' : '' }}">
                Konten Landing
            </a>
```

- [ ] **Step 6: Buat view sementara minimal** (supaya route tidak 500 — view lengkap di Task 4)

Create: `resources/views/superadmin/landing/edit.blade.php`

```blade
@extends('layouts.superadmin')

@section('title', 'Konten Landing')
@section('crumb', 'Super Admin')
@section('heading', 'Konten Landing')

@section('content')
<p>{{ $copy->heroTitleLead() }}</p>
@endsection
```

- [ ] **Step 7: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Feature/SuperAdminLandingContentTest.php`
Expected: `Tests: 3 passed`

- [ ] **Step 8: Jalankan full suite, pastikan tidak ada regresi**

Run: `php artisan test`
Expected: semua hijau (baseline + 3 test baru)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/SuperAdmin/LandingContentController.php routes/web.php resources/views/layouts/superadmin.blade.php resources/views/superadmin/landing/edit.blade.php tests/Feature/SuperAdminLandingContentTest.php
git commit -m "feat: rute & controller superadmin kelola konten landing"
```

---

### Task 4: Form admin lengkap (semua section)

**Files:**
- Modify: `resources/views/superadmin/landing/edit.blade.php` (ganti isi sementara Task 3 dengan form penuh)

**Interfaces:**
- Consumes: `$copy` (instance `LandingCopy`, Task 2) untuk nilai `placeholder`; `$stored` (array mentah `LandingContent::current()`) untuk nilai `value` input via `data_get($stored, "key.$i.field")`.
- Produces: field POST dengan nama persis sama seperti kunci `validate()` di Task 3 — dikonsumsi controller `update()`.

- [ ] **Step 1: Tulis form lengkap**

```blade
@extends('layouts.superadmin')

@section('title', 'Konten Landing')
@section('crumb', 'Super Admin')
@section('heading', 'Konten Landing')

@section('content')
@php
    $v = fn (string $key, $default = '') => old($key, data_get($stored, $key, $default));
@endphp

<div class="panel">
    <div class="panel-head">
        <h2>Halaman Landing (lajur.id)</h2>
        <span class="tag"><a href="{{ url('/') }}" target="_blank" rel="noopener">Lihat Halaman &rarr;</a></span>
    </div>
    <div class="panel-body">
        <p style="color:var(--graphite);font-size:.92rem">
            Kosongkan field mana pun untuk memakai teks default (ditampilkan sebagai placeholder abu-abu).
            Hanya teks — ikon dan jumlah kartu per bagian tetap seperti sekarang.
        </p>
    </div>
</div>

<form method="POST" action="{{ route('superadmin.landing.update') }}">
    @csrf @method('PATCH')

    {{-- 1. HERO --}}
    <div class="panel">
        <div class="panel-head"><h2>1. Hero</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="hero_eyebrow">Label kecil di atas judul</label>
                <input class="input" type="text" id="hero_eyebrow" name="hero_eyebrow" value="{{ $v('hero_eyebrow') }}" placeholder="{{ $copy->heroEyebrow() }}">
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="hero_title_lead">Judul baris 1</label>
                    <input class="input" type="text" id="hero_title_lead" name="hero_title_lead" value="{{ $v('hero_title_lead') }}" placeholder="{{ $copy->heroTitleLead() }}">
                </div>
                <div class="field">
                    <label for="hero_title_reveal">Judul baris 2 (warna aksen)</label>
                    <input class="input" type="text" id="hero_title_reveal" name="hero_title_reveal" value="{{ $v('hero_title_reveal') }}" placeholder="{{ $copy->heroTitleReveal() }}">
                </div>
            </div>
            <div class="field">
                <label for="hero_subtitle">Subjudul</label>
                <textarea class="input" id="hero_subtitle" name="hero_subtitle" rows="2" placeholder="{{ $copy->heroSubtitle() }}">{{ $v('hero_subtitle') }}</textarea>
            </div>
        </div>
    </div>

    {{-- 2. TOMBOL CTA & EYEBROW SOROTAN (dipakai berulang) --}}
    <div class="panel">
        <div class="panel-head"><h2>2. Teks Berulang</h2><p>Dipakai di beberapa tempat sekaligus.</p></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="cta_label">Teks tombol ajakan (muncul 5×: hero, 3 sorotan produk, CTA akhir)</label>
                    <input class="input" type="text" id="cta_label" name="cta_label" value="{{ $v('cta_label') }}" placeholder="{{ $copy->ctaLabel() }}">
                </div>
                <div class="field">
                    <label for="spotlight_eyebrow">Label kecil di 3 section sorotan produk</label>
                    <input class="input" type="text" id="spotlight_eyebrow" name="spotlight_eyebrow" value="{{ $v('spotlight_eyebrow') }}" placeholder="{{ $copy->spotlightEyebrow() }}">
                </div>
            </div>
        </div>
    </div>

    {{-- 3. TRUST STRIP ATAS --}}
    <div class="panel">
        <div class="panel-head"><h2>3. Baris Kepercayaan (bawah preview produk)</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="trust_lead">Kalimat pembuka</label>
                <input class="input" type="text" id="trust_lead" name="trust_lead" value="{{ $v('trust_lead') }}" placeholder="{{ $copy->trustLead() }}">
            </div>
            <div class="form-row">
                @foreach ($copy->trustItems() as $i => $default)
                    <div class="field">
                        <label for="trust_items_{{ $i }}">Item {{ $i + 1 }}</label>
                        <input class="input" type="text" id="trust_items_{{ $i }}" name="trust_items[{{ $i }}]" value="{{ $v("trust_items.$i") }}" placeholder="{{ $default }}">
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 4. PAIN POINT --}}
    <div class="panel">
        <div class="panel-head"><h2>4. Masalah yang Diselesaikan</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="pain_eyebrow">Label kecil</label>
                    <input class="input" type="text" id="pain_eyebrow" name="pain_eyebrow" value="{{ $v('pain_eyebrow') }}" placeholder="{{ $copy->painEyebrow() }}">
                </div>
                <div class="field">
                    <label for="pain_title">Judul</label>
                    <input class="input" type="text" id="pain_title" name="pain_title" value="{{ $v('pain_title') }}" placeholder="{{ $copy->painTitle() }}">
                </div>
            </div>
            <div class="field">
                <label for="pain_subtitle">Subjudul</label>
                <textarea class="input" id="pain_subtitle" name="pain_subtitle" rows="2" placeholder="{{ $copy->painSubtitle() }}">{{ $v('pain_subtitle') }}</textarea>
            </div>
            @foreach ($copy->painItems() as $i => $default)
                <div class="why-card">
                    <div class="num">KARTU {{ $i + 1 }}</div>
                    <div class="form-row">
                        <div class="field">
                            <label for="pain_items_{{ $i }}_title">Judul</label>
                            <input class="input" type="text" id="pain_items_{{ $i }}_title" name="pain_items[{{ $i }}][title]" value="{{ $v("pain_items.$i.title") }}" placeholder="{{ $default['title'] }}">
                        </div>
                        <div class="field">
                            <label for="pain_items_{{ $i }}_text">Keterangan</label>
                            <input class="input" type="text" id="pain_items_{{ $i }}_text" name="pain_items[{{ $i }}][text]" value="{{ $v("pain_items.$i.text") }}" placeholder="{{ $default['text'] }}">
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="field">
                <label for="pain_closing">Kalimat penutup (di bawah panah)</label>
                <input class="input" type="text" id="pain_closing" name="pain_closing" value="{{ $v('pain_closing') }}" placeholder="{{ $copy->painClosing() }}">
            </div>
        </div>
    </div>

    {{-- 5. SEBELUM / SESUDAH --}}
    <div class="panel">
        <div class="panel-head"><h2>5. Sebelum / Sesudah</h2></div>
        <div class="panel-body">
            <div class="form-row">
                @foreach ($copy->beforeItems() as $i => $default)
                    <div class="field">
                        <label for="before_items_{{ $i }}">Sebelum {{ $i + 1 }}</label>
                        <input class="input" type="text" id="before_items_{{ $i }}" name="before_items[{{ $i }}]" value="{{ $v("before_items.$i") }}" placeholder="{{ $default }}">
                    </div>
                @endforeach
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="after_brand">Label brand "sesudah"</label>
                    <input class="input" type="text" id="after_brand" name="after_brand" value="{{ $v('after_brand') }}" placeholder="{{ $copy->afterBrand() }}">
                </div>
                <div class="field">
                    <label for="after_text">Teks "sesudah"</label>
                    <input class="input" type="text" id="after_text" name="after_text" value="{{ $v('after_text') }}" placeholder="{{ $copy->afterText() }}">
                </div>
            </div>
        </div>
    </div>

    {{-- 6. FITUR --}}
    <div class="panel">
        <div class="panel-head"><h2>6. Fitur (4 Kelompok)</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="features_title">Judul</label>
                    <input class="input" type="text" id="features_title" name="features_title" value="{{ $v('features_title') }}" placeholder="{{ $copy->featuresTitle() }}">
                </div>
                <div class="field">
                    <label for="features_subtitle">Subjudul</label>
                    <input class="input" type="text" id="features_subtitle" name="features_subtitle" value="{{ $v('features_subtitle') }}" placeholder="{{ $copy->featuresSubtitle() }}">
                </div>
            </div>
            @foreach ($copy->featureGroups() as $gi => $group)
                <div class="why-card">
                    <div class="num">KELOMPOK {{ $gi + 1 }}</div>
                    <div class="field">
                        <label for="feature_groups_{{ $gi }}_title">Judul kelompok</label>
                        <input class="input" type="text" id="feature_groups_{{ $gi }}_title" name="feature_groups[{{ $gi }}][title]" value="{{ $v("feature_groups.$gi.title") }}" placeholder="{{ $group['title'] }}">
                    </div>
                    @foreach ($group['items'] as $ii => $defaultItem)
                        <div class="field">
                            <label for="feature_groups_{{ $gi }}_items_{{ $ii }}">Baris {{ $ii + 1 }}</label>
                            <input class="input" type="text" id="feature_groups_{{ $gi }}_items_{{ $ii }}" name="feature_groups[{{ $gi }}][items][{{ $ii }}]" value="{{ $v("feature_groups.$gi.items.$ii") }}" placeholder="{{ $defaultItem }}">
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    {{-- 7. SOROTAN BBM --}}
    <div class="panel">
        <div class="panel-head"><h2>7. Sorotan: BBM</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="spotlight_fuel_title">Judul</label>
                <input class="input" type="text" id="spotlight_fuel_title" name="spotlight_fuel_title" value="{{ $v('spotlight_fuel_title') }}" placeholder="{{ $copy->spotlightFuelTitle() }}">
            </div>
            <div class="field">
                <label for="spotlight_fuel_text">Teks</label>
                <textarea class="input" id="spotlight_fuel_text" name="spotlight_fuel_text" rows="3" placeholder="{{ $copy->spotlightFuelText() }}">{{ $v('spotlight_fuel_text') }}</textarea>
            </div>
        </div>
    </div>

    {{-- 8. SOROTAN NAVIGASI DRIVER --}}
    <div class="panel">
        <div class="panel-head"><h2>8. Sorotan: Navigasi Driver</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="spotlight_driver_title">Judul</label>
                <input class="input" type="text" id="spotlight_driver_title" name="spotlight_driver_title" value="{{ $v('spotlight_driver_title') }}" placeholder="{{ $copy->spotlightDriverTitle() }}">
            </div>
            <div class="field">
                <label for="spotlight_driver_text">Teks</label>
                <textarea class="input" id="spotlight_driver_text" name="spotlight_driver_text" rows="3" placeholder="{{ $copy->spotlightDriverText() }}">{{ $v('spotlight_driver_text') }}</textarea>
            </div>
        </div>
    </div>

    {{-- 9. HIGHLIGHT KELUARGA --}}
    <div class="panel">
        <div class="panel-head"><h2>9. Highlight: Keluarga Memantau</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="family_title">Judul</label>
                    <input class="input" type="text" id="family_title" name="family_title" value="{{ $v('family_title') }}" placeholder="{{ $copy->familyTitle() }}">
                </div>
                <div class="field">
                    <label for="family_subtitle">Subjudul</label>
                    <input class="input" type="text" id="family_subtitle" name="family_subtitle" value="{{ $v('family_subtitle') }}" placeholder="{{ $copy->familySubtitle() }}">
                </div>
            </div>
            @foreach ($copy->familySteps() as $i => $default)
                <div class="why-card">
                    <div class="num">LANGKAH {{ $i + 1 }}</div>
                    <div class="form-row">
                        <div class="field">
                            <label for="family_steps_{{ $i }}_title">Judul</label>
                            <input class="input" type="text" id="family_steps_{{ $i }}_title" name="family_steps[{{ $i }}][title]" value="{{ $v("family_steps.$i.title") }}" placeholder="{{ $default['title'] }}">
                        </div>
                        <div class="field">
                            <label for="family_steps_{{ $i }}_text">Keterangan</label>
                            <input class="input" type="text" id="family_steps_{{ $i }}_text" name="family_steps[{{ $i }}][text]" value="{{ $v("family_steps.$i.text") }}" placeholder="{{ $default['text'] }}">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 10. SOROTAN ETALASE --}}
    <div class="panel">
        <div class="panel-head"><h2>10. Sorotan: Etalase Tenant</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="spotlight_storefront_title">Judul</label>
                <input class="input" type="text" id="spotlight_storefront_title" name="spotlight_storefront_title" value="{{ $v('spotlight_storefront_title') }}" placeholder="{{ $copy->spotlightStorefrontTitle() }}">
            </div>
            <div class="field">
                <label for="spotlight_storefront_text">Teks</label>
                <textarea class="input" id="spotlight_storefront_text" name="spotlight_storefront_text" rows="3" placeholder="{{ $copy->spotlightStorefrontText() }}">{{ $v('spotlight_storefront_text') }}</textarea>
            </div>
        </div>
    </div>

    {{-- 11. PREVIEW GPS --}}
    <div class="panel">
        <div class="panel-head"><h2>11. Preview GPS</h2><p>Section ilustrasi berlabel "segera hadir" — jangan hapus label ini, fitur belum berjalan.</p></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="gps_badge">Label badge</label>
                    <input class="input" type="text" id="gps_badge" name="gps_badge" value="{{ $v('gps_badge') }}" placeholder="{{ $copy->gpsBadge() }}">
                </div>
                <div class="field">
                    <label for="gps_title">Judul</label>
                    <input class="input" type="text" id="gps_title" name="gps_title" value="{{ $v('gps_title') }}" placeholder="{{ $copy->gpsTitle() }}">
                </div>
            </div>
            <div class="field">
                <label for="gps_text">Teks</label>
                <textarea class="input" id="gps_text" name="gps_text" rows="2" placeholder="{{ $copy->gpsText() }}">{{ $v('gps_text') }}</textarea>
            </div>
            <div class="field">
                <label for="gps_note">Catatan kecil</label>
                <input class="input" type="text" id="gps_note" name="gps_note" value="{{ $v('gps_note') }}" placeholder="{{ $copy->gpsNote() }}">
            </div>
        </div>
    </div>

    {{-- 12. KENAPA LAJUR --}}
    <div class="panel">
        <div class="panel-head"><h2>12. Kenapa Memilih Lajur</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="why_title">Judul</label>
                <input class="input" type="text" id="why_title" name="why_title" value="{{ $v('why_title') }}" placeholder="{{ $copy->whyTitle() }}">
            </div>
            @foreach ($copy->whyItems() as $i => $default)
                <div class="why-card">
                    <div class="num">KARTU {{ $i + 1 }}</div>
                    <div class="form-row">
                        <div class="field">
                            <label for="why_items_{{ $i }}_title">Judul</label>
                            <input class="input" type="text" id="why_items_{{ $i }}_title" name="why_items[{{ $i }}][title]" value="{{ $v("why_items.$i.title") }}" placeholder="{{ $default['title'] }}">
                        </div>
                        <div class="field">
                            <label for="why_items_{{ $i }}_text">Keterangan</label>
                            <input class="input" type="text" id="why_items_{{ $i }}_text" name="why_items[{{ $i }}][text]" value="{{ $v("why_items.$i.text") }}" placeholder="{{ $default['text'] }}">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 13. WORKFLOW --}}
    <div class="panel">
        <div class="panel-head"><h2>13. Alur Mulai (Workflow)</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="workflow_title">Judul</label>
                <input class="input" type="text" id="workflow_title" name="workflow_title" value="{{ $v('workflow_title') }}" placeholder="{{ $copy->workflowTitle() }}">
            </div>
            @foreach ($copy->workflowSteps() as $i => $default)
                <div class="why-card">
                    <div class="num">LANGKAH {{ $i + 1 }}</div>
                    <div class="form-row">
                        <div class="field">
                            <label for="workflow_steps_{{ $i }}_title">Judul</label>
                            <input class="input" type="text" id="workflow_steps_{{ $i }}_title" name="workflow_steps[{{ $i }}][title]" value="{{ $v("workflow_steps.$i.title") }}" placeholder="{{ $default['title'] }}">
                        </div>
                        <div class="field">
                            <label for="workflow_steps_{{ $i }}_text">Keterangan</label>
                            <input class="input" type="text" id="workflow_steps_{{ $i }}_text" name="workflow_steps[{{ $i }}][text]" value="{{ $v("workflow_steps.$i.text") }}" placeholder="{{ $default['text'] }}">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 14. ECOSYSTEM --}}
    <div class="panel">
        <div class="panel-head"><h2>14. Platform Ecosystem</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="ecosystem_title_line1">Judul baris 1</label>
                    <input class="input" type="text" id="ecosystem_title_line1" name="ecosystem_title_line1" value="{{ $v('ecosystem_title_line1') }}" placeholder="{{ $copy->ecosystemTitleLine1() }}">
                </div>
                <div class="field">
                    <label for="ecosystem_title_line2">Judul baris 2</label>
                    <input class="input" type="text" id="ecosystem_title_line2" name="ecosystem_title_line2" value="{{ $v('ecosystem_title_line2') }}" placeholder="{{ $copy->ecosystemTitleLine2() }}">
                </div>
            </div>
            <div class="field">
                <label for="ecosystem_subtitle">Subjudul</label>
                <input class="input" type="text" id="ecosystem_subtitle" name="ecosystem_subtitle" value="{{ $v('ecosystem_subtitle') }}" placeholder="{{ $copy->ecosystemSubtitle() }}">
            </div>
            <div class="form-row">
                @foreach ($copy->ecosystemItems() as $i => $default)
                    <div class="field">
                        <label for="ecosystem_items_{{ $i }}">Item {{ $i + 1 }}</label>
                        <input class="input" type="text" id="ecosystem_items_{{ $i }}" name="ecosystem_items[{{ $i }}]" value="{{ $v("ecosystem_items.$i") }}" placeholder="{{ $default }}">
                    </div>
                @endforeach
            </div>
            <div class="field">
                <label for="ecosystem_caption">Kalimat penutup</label>
                <input class="input" type="text" id="ecosystem_caption" name="ecosystem_caption" value="{{ $v('ecosystem_caption') }}" placeholder="{{ $copy->ecosystemCaption() }}">
            </div>
        </div>
    </div>

    {{-- 15. HARGA --}}
    <div class="panel">
        <div class="panel-head"><h2>15. Harga</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="pricing_title">Judul</label>
                    <input class="input" type="text" id="pricing_title" name="pricing_title" value="{{ $v('pricing_title') }}" placeholder="{{ $copy->pricingTitle() }}">
                </div>
                <div class="field">
                    <label for="pricing_subtitle">Subjudul</label>
                    <input class="input" type="text" id="pricing_subtitle" name="pricing_subtitle" value="{{ $v('pricing_subtitle') }}" placeholder="{{ $copy->pricingSubtitle() }}">
                </div>
            </div>
        </div>
    </div>

    {{-- 16. CTA AKHIR --}}
    <div class="panel">
        <div class="panel-head"><h2>16. Ajakan Penutup</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="cta_title">Judul</label>
                <input class="input" type="text" id="cta_title" name="cta_title" value="{{ $v('cta_title') }}" placeholder="{{ $copy->ctaTitle() }}">
            </div>
            <div class="field">
                <label for="cta_text">Teks</label>
                <input class="input" type="text" id="cta_text" name="cta_text" value="{{ $v('cta_text') }}" placeholder="{{ $copy->ctaText() }}">
            </div>
            <div class="form-row">
                @foreach ($copy->ctaTrustItems() as $i => $default)
                    <div class="field">
                        <label for="cta_trust_items_{{ $i }}">Baris kepercayaan {{ $i + 1 }}</label>
                        <input class="input" type="text" id="cta_trust_items_{{ $i }}" name="cta_trust_items[{{ $i }}]" value="{{ $v("cta_trust_items.$i") }}" placeholder="{{ $default }}">
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary" style="margin-bottom:40px">
        <x-icon name="check" /> Simpan Semua Perubahan
    </button>
</form>
@endsection
```

- [ ] **Step 2: Verifikasi tak ada error render**

Run: `php artisan test tests/Feature/SuperAdminLandingContentTest.php`
Expected: `Tests: 3 passed` (test `test_super_admin_can_view_edit_form` sudah mem-visit halaman ini via `assertOk()`, kalau ada syntax error Blade test ini akan gagal)

- [ ] **Step 3: Commit**

```bash
git add resources/views/superadmin/landing/edit.blade.php
git commit -m "feat: form superadmin lengkap kelola konten landing (16 section)"
```

---

### Task 5: Wire `$copy` ke `LandingController`

**Files:**
- Modify: `app/Http/Controllers/LandingController.php`

**Interfaces:**
- Consumes: `LandingContent::current()` (Task 1), `LandingCopy` (Task 2).
- Produces: variabel `$copy` tersedia di `resources/views/landing.blade.php` (dipakai Task 6).

- [ ] **Step 1: Modifikasi controller**

```php
<?php

namespace App\Http\Controllers;

use App\Content\LandingCopy;
use App\Models\LandingContent;
use App\Models\Plan;
use App\Tenancy\Domain;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index(Request $request, HomeController $home): View
    {
        if (! Domain::isCentral($request->getHost())) {
            return $home->index(); // subdomain tenant → etalase
        }

        // Ringkasan paket untuk teaser harga (decoy) — urut sesuai sort_order.
        $plans = Plan::with('features')->orderBy('sort_order')->get();

        $copy = new LandingCopy(LandingContent::current());

        return view('landing', compact('plans', 'copy'));
    }
}
```

- [ ] **Step 2: Jalankan test landing existing, pastikan belum rusak (landing.blade.php belum diubah, `$copy` belum dipakai — harus tetap lolos)**

Run: `php artisan test tests/Feature/LandingPageTest.php`
Expected: `Tests: 3 passed`

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/LandingController.php
git commit -m "feat: LandingController kirim \$copy ke view landing"
```

---

### Task 6: `landing.blade.php` konsumsi `$copy`

**Files:**
- Modify: `resources/views/landing.blade.php` (seluruh isi `@section('content')`; bagian `@push('head')`/`@push('scripts')` di bawahnya TIDAK berubah)

**Interfaces:**
- Consumes: `$copy` (Task 5), semua 45 method getter dari Task 2.

- [ ] **Step 1: Ganti section HERO sampai FITUR** — cari blok berikut di `landing.blade.php` dan ganti persis:

```blade
{{-- ============ HERO ============ --}}
<section class="hero" id="home">
    <div class="container">
        <div class="hero-content">
            <span class="eyebrow hero-eyebrow">{{ $copy->heroEyebrow() }}</span>
            <h1 class="hero-title">
                <span class="hero-title__lead">{{ $copy->heroTitleLead() }}</span>
                <span class="hero-title__reveal">{{ $copy->heroTitleReveal() }}</span>
            </h1>
            <p>{{ $copy->heroSubtitle() }}</p>
            <div class="hero-actions">
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }} <x-icon name="arrow-right" /></a>
                <a href="{{ url('/demo') }}" class="btn btn-light">Lihat Demo</a>
            </div>
        </div>
    </div>
</section>

{{-- ============ PREVIEW PRODUK ============ --}}
<div class="product-band">
    <div class="container">
        {{-- Tanpa "reveal": gambar ini sudah tampil di layar pertama saat load
             (tak perlu di-scroll), jadi animasi fade-in-saat-scroll cuma bikin
             kedip sekilas tiap refresh alih-alih reveal yang mulus. --}}
        <div class="browser">
            <div class="browser-bar"><span></span><span></span><span></span></div>
            <img src="{{ asset('img/product-dashboard.jpg') }}" width="1600" height="955"
                 alt="Dashboard Lajur, kalender ketersediaan armada dalam satu layar" loading="lazy">
        </div>
    </div>
</div>

{{-- ============ TRUST STRIP ============ --}}
<div class="container">
    <p class="trust-lead reveal">{{ $copy->trustLead() }}</p>
    <div class="trust-strip reveal" style="margin-top:14px">
        @foreach ([['gauge', 0], ['dashboard', 1], ['phone', 2], ['whatsapp', 3]] as [$icon, $i])
            <span class="item"><x-icon name="{{ $icon }}" /> {{ $copy->trustItems()[$i] }}</span>
        @endforeach
    </div>
</div>

{{-- ============ PAIN POINT ============ --}}
<section class="section" id="masalah">
    <div class="container">
        <div class="prob-grid">
            <div class="prob-head reveal">
                <span class="eyebrow">{{ $copy->painEyebrow() }}</span>
                <h2 class="section-title">{{ $copy->painTitle() }}</h2>
                <p class="section-sub">{{ $copy->painSubtitle() }}</p>
            </div>
            <div class="prob-list">
                @php $painIcons = ['pin', 'gauge', 'fuel', 'chat', 'list']; @endphp
                @foreach ($copy->painItems() as $i => $item)
                    <div class="prob reveal">
                        <span class="prob-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="prob-ico"><x-icon name="{{ $painIcons[$i] }}" /></div>
                        <div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="prob-close reveal">
            <x-icon name="chevron-down" class="prob-arrow" />
            <p>{{ $copy->painClosing() }}</p>
        </div>
    </div>
</section>

{{-- ============ SEBELUM / SESUDAH ============ --}}
<section class="section" style="background:var(--ivory-200);padding-block:64px">
    <div class="container">
        <div class="ba-grid reveal">
            <div class="ba-before">
                <span class="ba-lbl">Sebelum</span>
                <ul>
                    @php $beforeIcons = ['list', 'chat', 'phone', 'edit']; @endphp
                    @foreach ($copy->beforeItems() as $i => $label)
                        <li><x-icon name="{{ $beforeIcons[$i] }}" /> {{ $label }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="ba-arrow" aria-hidden="true"><x-icon name="arrow-right" /></div>
            <div class="ba-after">
                <span class="ba-lbl">Sesudah</span>
                <div class="ba-brand"><span class="mark"><x-icon name="route" /></span> {{ $copy->afterBrand() }}</div>
                <p>{{ $copy->afterText() }}</p>
            </div>
        </div>
    </div>
</section>

{{-- ============ FITUR (BERKELOMPOK) ============ --}}
<section class="section" id="fitur">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->featuresTitle() }}</h2>
            <p class="section-sub">{{ $copy->featuresSubtitle() }}</p>
        </div>
        <div class="bento">
            @php
                $groupMeta = [
                    ['ico' => 'calendar', 'class' => 'cell-wide', 'itemIcons' => ['check', 'check', 'check', 'check']],
                    ['ico' => 'fuel', 'class' => 'cell-wide cell-dark', 'itemIcons' => ['check', 'check', 'check', 'clock']],
                    ['ico' => 'sparkle', 'class' => 'cell-wide cell-tint', 'itemIcons' => ['check', 'check', 'check']],
                    ['ico' => 'users', 'class' => 'cell-wide', 'itemIcons' => ['check', 'check', 'check']],
                ];
            @endphp
            @foreach ($copy->featureGroups() as $gi => $group)
                <div class="cell {{ $groupMeta[$gi]['class'] }} reveal">
                    <div class="ico"><x-icon name="{{ $groupMeta[$gi]['ico'] }}" /></div>
                    <h3>{{ $group['title'] }}</h3>
                    <ul class="fg-list">
                        @foreach ($group['items'] as $ii => $item)
                            <li>
                                <x-icon name="{{ $groupMeta[$gi]['itemIcons'][$ii] }}" /> {{ $item }}
                                {{-- Item terakhir kelompok Monitoring (GPS) selalu berlabel "segera hadir" — fitur belum berjalan, bukan bagian teks yg diedit. --}}
                                @if ($gi === 1 && $ii === 3)
                                    <span class="pill pill-pending" style="font-size:.64rem;vertical-align:middle">Segera hadir</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>
```

- [ ] **Step 2: Ganti section SOROTAN BBM sampai SOROTAN ETALASE**

```blade
{{-- ============ SOROTAN BBM ============ --}}
<section class="section">
    <div class="container">
        <div class="spotlight">
            <div class="browser reveal">
                <div class="browser-bar"><span></span><span></span><span></span></div>
                <img src="{{ asset('img/product-bbm.jpg') }}" width="1600" height="955"
                     alt="Halaman BBM Lajur menandai pengisian yang tak wajar" loading="lazy">
            </div>
            <div class="spotlight-text reveal">
                <span class="eyebrow">{{ $copy->spotlightEyebrow() }}</span>
                <h2>{{ $copy->spotlightFuelTitle() }}</h2>
                <p>{{ $copy->spotlightFuelText() }}</p>
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }} <x-icon name="arrow-right" /></a>
            </div>
        </div>
    </div>
</section>

{{-- ============ SOROTAN NAVIGASI DRIVER ============ --}}
<section class="section">
    <div class="container">
        <div class="spotlight spotlight-rev">
            <div class="spotlight-text reveal">
                <span class="eyebrow">{{ $copy->spotlightEyebrow() }}</span>
                <h2>{{ $copy->spotlightDriverTitle() }}</h2>
                <p>{{ $copy->spotlightDriverText() }}</p>
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }} <x-icon name="arrow-right" /></a>
            </div>
            <div class="phone-frame-wrap reveal">
                <div class="phone-frame-col">
                    <div class="phone-frame">
                        <img src="{{ asset('img/product-driver-full.jpg') }}" width="480" height="882"
                             alt="Dashboard driver Lajur: jadwal hari ini dan detail tugas mendatang lengkap dengan tombol Maps" loading="lazy">
                    </div>
                    <span class="phone-frame-cap">Jadwal &amp; tugas mendatang</span>
                </div>
                {{-- Ilustrasi rute — BUKAN screenshot Google Maps asli (tak boleh
                     ditangkap/ditiru utk materi marketing); gaya visual sama dgn
                     mockup peta GPS di section #gps agar konsisten satu halaman. --}}
                <div class="phone-frame-col">
                    <div class="phone-frame maps-demo">
                        <div class="gps-bar">
                            <span class="car-lbl">Rute ke tujuan</span>
                        </div>
                        <div class="maps-demo-map">
                            <svg viewBox="0 0 480 760" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                                <rect width="480" height="760" fill="#E9EDF2"/>
                                <g fill="#DCE2EA">
                                    <rect x="24" y="32" width="160" height="130" rx="6"/><rect x="208" y="32" width="120" height="130" rx="6"/><rect x="352" y="32" width="104" height="130" rx="6"/>
                                    <rect x="24" y="196" width="160" height="160" rx="6"/><rect x="208" y="196" width="120" height="160" rx="6"/><rect x="352" y="196" width="104" height="160" rx="6"/>
                                    <rect x="24" y="390" width="160" height="145" rx="6"/><rect x="208" y="390" width="120" height="145" rx="6"/><rect x="352" y="390" width="104" height="145" rx="6"/>
                                    <rect x="24" y="569" width="160" height="160" rx="6"/><rect x="208" y="569" width="120" height="160" rx="6"/><rect x="352" y="569" width="104" height="160" rx="6"/>
                                </g>
                                <path d="M0 540 C 110 505, 250 590, 480 520" stroke="#BFD4E6" stroke-width="22" fill="none" opacity=".85"/>
                                <path d="M72 700 L72 460 L272 460 L272 250 L420 250 L420 90"
                                      stroke="#E7B24C" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="72" cy="700" r="9" fill="#5A6478"/>
                                <circle cx="420" cy="90" r="11" fill="#1F8A63"/>
                            </svg>
                        </div>
                        <div class="gps-foot">
                            <span>Bandara APT Pranoto</span>
                            <span><b>6</b> km · <b>12</b> menit</span>
                        </div>
                    </div>
                    <span class="phone-frame-cap">Ilustrasi: rute otomatis terbuka</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============ HIGHLIGHT: KELUARGA IKUT MEMANTAU ============ --}}
<section class="section" style="background:var(--ivory-200)">
    <div class="container">
        <div class="section-head reveal" style="max-width:680px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->familyTitle() }}</h2>
            <p class="section-sub">{{ $copy->familySubtitle() }}</p>
        </div>
        <div class="flow reveal">
            @php $familyIcons = ['car', 'chat', 'search', 'pin']; @endphp
            @foreach ($copy->familySteps() as $i => $step)
                <div class="flow-step"><div class="fico"><x-icon name="{{ $familyIcons[$i] }}" /></div><h3>{{ $step['title'] }}</h3><p>{{ $step['text'] }}</p></div>
                @if (! $loop->last)<span class="flow-arr" aria-hidden="true"><x-icon name="arrow-right" /></span>@endif
            @endforeach
        </div>
    </div>
</section>

{{-- ============ SOROTAN ETALASE TENANT ============ --}}
<section class="section" style="background:var(--ivory-200)">
    <div class="container">
        <div class="spotlight">
            <div class="browser reveal">
                <div class="browser-bar"><span></span><span></span><span></span></div>
                <img src="{{ asset('img/product-storefront.jpg') }}" width="1600" height="956"
                     alt="Etalase booking online bermerek milik tenant Lajur, lengkap dengan warna dan logo sendiri" loading="lazy">
            </div>
            <div class="spotlight-text reveal">
                <span class="eyebrow">{{ $copy->spotlightEyebrow() }}</span>
                <h2>{{ $copy->spotlightStorefrontTitle() }}</h2>
                <p>{{ $copy->spotlightStorefrontText() }}</p>
                <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }} <x-icon name="arrow-right" /></a>
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 3: Ganti section GPS sampai HARGA**

```blade
{{-- ============ PREVIEW GPS (ilustrasi berlabel — fitur dalam pengembangan) ============ --}}
<section class="section gps-band" id="gps">
    <div class="container gps-grid">
        <div class="gps-text reveal">
            <span class="gps-badge"><i></i> {{ $copy->gpsBadge() }}</span>
            <h2>{{ $copy->gpsTitle() }}</h2>
            <p>{{ $copy->gpsText() }}</p>
            <p class="gps-note">{{ $copy->gpsNote() }}</p>
        </div>
        <div class="gps-phone-wrap reveal">
            <div class="gps-phone">
                <div class="gps-bar">
                    <span class="car-lbl">Innova Zenix</span>
                    <span class="live"><i></i> LIVE</span>
                </div>
                <div class="gps-eta">
                    <x-icon name="clock" />
                    <span>Estimasi tiba <b id="gps-eta-clock">15:42</b> · <b id="gps-eta-min">6</b> menit lagi</span>
                </div>
                <div class="gps-map">
                    <svg viewBox="0 0 600 900" id="gps-svg" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                        <rect width="600" height="900" fill="#E9EDF2"/>
                        <g fill="#DCE2EA">
                            <rect x="30" y="40" width="200" height="150" rx="6"/><rect x="260" y="40" width="150" height="150" rx="6"/><rect x="440" y="40" width="130" height="150" rx="6"/>
                            <rect x="30" y="230" width="200" height="190" rx="6"/><rect x="260" y="230" width="150" height="190" rx="6"/><rect x="440" y="230" width="130" height="190" rx="6"/>
                            <rect x="30" y="460" width="200" height="170" rx="6"/><rect x="260" y="460" width="150" height="170" rx="6"/><rect x="440" y="460" width="130" height="170" rx="6"/>
                            <rect x="30" y="670" width="200" height="190" rx="6"/><rect x="260" y="670" width="150" height="190" rx="6"/><rect x="440" y="670" width="130" height="190" rx="6"/>
                        </g>
                        <path d="M0 640 C 140 600, 300 700, 600 620" stroke="#BFD4E6" stroke-width="26" fill="none" opacity=".85"/>
                        <text x="240" y="215" font-size="15" fill="#8A93A3">Jl. Merdeka Raya</text>
                        <text x="240" y="445" font-size="15" fill="#8A93A3">Jl. Sudirman</text>
                        <text x="240" y="655" font-size="15" fill="#8A93A3">Jl. Gatot Subroto</text>
                        <path id="gps-route" d="M 90 830 L 90 560 L 330 560 L 330 300 L 520 300 L 520 110"
                              stroke="#E7B24C" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity=".45"/>
                        <path id="gps-trail" d="" stroke="#E7B24C" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="90" cy="830" r="9" fill="#5A6478"/>
                        <circle cx="520" cy="110" r="11" fill="#1F8A63"/>
                        <g id="gps-car">
                            <circle r="22" fill="#E7B24C" opacity=".28"/>
                            <circle r="13" fill="#E7B24C" stroke="#0F1B33" stroke-width="4"/>
                        </g>
                    </svg>
                </div>
                <div class="gps-foot">
                    <span id="gps-street">Jl. Gatot Subroto</span>
                    <span><b id="gps-speed">48</b> km/j</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============ KENAPA MEMILIH LAJUR ============ --}}
<section class="section">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->whyTitle() }}</h2>
        </div>
        <div class="why-grid">
            @php $whyIcons = ['clock', 'dashboard', 'phone', 'shield', 'gauge', 'whatsapp']; @endphp
            @foreach ($copy->whyItems() as $i => $item)
                <div class="why reveal">
                    <div class="wico"><x-icon name="{{ $whyIcons[$i] }}" /></div>
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ WORKFLOW ============ --}}
<section class="section" id="cara-kerja" style="background:var(--ivory-200)">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->workflowTitle() }}</h2>
        </div>
        <div class="steps">
            @foreach ($copy->workflowSteps() as $i => $step)
                <div class="step reveal">
                    <div class="num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ PLATFORM ECOSYSTEM + FUTURE READY ============ --}}
<section class="section" id="platform">
    <div class="container">
        <div class="section-head reveal" style="max-width:720px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->ecosystemTitleLine1() }}<br>{{ $copy->ecosystemTitleLine2() }}</h2>
            <p class="section-sub">{{ $copy->ecosystemSubtitle() }}</p>
        </div>
        <div class="eco-grid reveal">
            @php
                $ecoMeta = [
                    ['car', false], ['users', false], ['calendar', false], ['home', false],
                    ['fuel', false], ['gauge', false], ['sparkle', false],
                    ['pin', true], ['chip', true],
                ];
            @endphp
            @foreach ($copy->ecosystemItems() as $i => $label)
                <div class="eco-tile {{ $ecoMeta[$i][1] ? 'soon' : '' }}">
                    <div class="eco-ico"><x-icon name="{{ $ecoMeta[$i][0] }}" /></div>
                    <span>{{ $label }}</span>
                    @if ($ecoMeta[$i][1])<em>Segera hadir</em>@endif
                </div>
            @endforeach
        </div>
        <p class="eco-caption reveal">{{ $copy->ecosystemCaption() }}</p>
    </div>
</section>

{{-- ============ HARGA RINGKAS ============ --}}
<section class="section" id="harga" style="background:var(--ivory-200)">
    <div class="container">
        <div class="section-head reveal" style="max-width:640px;margin-inline:auto;text-align:center">
            <h2 class="section-title">{{ $copy->pricingTitle() }}</h2>
            <p class="section-sub">{{ $copy->pricingSubtitle() }}</p>
        </div>
        <div class="price-teaser">
            @foreach ($plans as $plan)
                <a href="{{ route('signup.pricing') }}" class="pt-card @if ($plan->key === 'business') pt-featured @endif">
                    @if ($plan->key === 'business')<span class="pt-flag">Paling populer</span>@endif
                    <span class="pt-name">{{ $plan->name }}</span>
                    @if ($plan->hasDiscount())
                        <span class="pt-strike"><s>Rp {{ number_format($plan->price, 0, ',', '.') }}</s>
                            @if ($plan->discount_label)<em>{{ $plan->discount_label }}</em>@endif
                        </span>
                    @endif
                    <span class="pt-price">Rp {{ number_format($plan->effectivePrice(), 0, ',', '.') }}<small>/bln</small></span>
                    <span class="pt-note">{{ $plan->features->count() }} fitur premium</span>
                </a>
            @endforeach
        </div>
        <div style="text-align:center;margin-top:28px">
            <a href="{{ route('signup.pricing') }}" class="btn btn-ghost">Lihat detail paket <x-icon name="arrow-right" /></a>
        </div>
    </div>
</section>
```

- [ ] **Step 4: Ganti section AJAKAN (CTA akhir)**

```blade
{{-- ============ AJAKAN ============ --}}
<section class="section">
    <div class="container">
        <div class="cta-band reveal">
            <div>
                <h2>{{ $copy->ctaTitle() }}</h2>
                <p>{{ $copy->ctaText() }}</p>
            </div>
            <a href="{{ route('signup.trial.form') }}" class="btn btn-primary">{{ $copy->ctaLabel() }}</a>
        </div>
        <div class="trust-strip">
            @foreach ([['shield', 0], ['whatsapp', 1], ['settings', 2]] as [$icon, $i])
                <span class="item"><x-icon name="{{ $icon }}" /> {{ $copy->ctaTrustItems()[$i] }}</span>
            @endforeach
        </div>
    </div>
</section>
```

- [ ] **Step 5: Jalankan test landing, pastikan lolos tanpa perubahan teks (masih pakai default)**

Run: `php artisan test tests/Feature/LandingPageTest.php`
Expected: `Tests: 3 passed` — assertion `assertSee('Kelola seluruh operasional armada')` tetap lolos karena itu persis `LandingCopy::DEFAULTS['hero_title_lead']`.

- [ ] **Step 6: Jalankan full suite**

Run: `php artisan test`
Expected: semua hijau, tidak ada regresi

- [ ] **Step 7: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: landing.blade.php konsumsi \$copy (LandingCopy) menggantikan teks hardcode"
```

---

### Task 7: Test end-to-end (edit tersimpan → landing berubah, fallback per-item bekerja)

**Files:**
- Modify: `tests/Feature/SuperAdminLandingContentTest.php` (tambah 3 test)

**Interfaces:**
- Consumes: rute `superadmin.landing.update` (Task 3), `GET /` (Task 6).

- [ ] **Step 1: Tambah test**

```php
    public function test_super_admin_can_update_hero_and_it_reflects_on_landing_page(): void
    {
        $this->actingAs($this->superAdmin())
            ->patch('/superadmin/konten-landing', [
                'hero_title_lead' => 'Judul Kustom Owner',
                'hero_title_reveal' => 'baris kedua kustom.',
            ])
            ->assertRedirect(route('superadmin.landing.edit'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Judul Kustom Owner')
            ->assertSee('baris kedua kustom.');
    }

    public function test_partial_pain_item_edit_keeps_other_items_default(): void
    {
        $this->actingAs($this->superAdmin())
            ->patch('/superadmin/konten-landing', [
                'pain_items' => [
                    2 => ['title' => 'Kartu Ketiga Kustom'],
                ],
            ])
            ->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('Kartu Ketiga Kustom') // item 2 diganti
            ->assertSee('Sulit tahu posisi kendaraan') // item 0 tetap default
            ->assertSee('Data operasional tersebar'); // item 4 tetap default
    }

    public function test_blank_field_falls_back_to_default_after_being_previously_set(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patch('/superadmin/konten-landing', ['cta_title' => 'Judul Sementara']);
        $this->get('/')->assertSee('Judul Sementara');

        $this->actingAs($admin)->patch('/superadmin/konten-landing', ['cta_title' => '']);
        $this->get('/')->assertSee('Siap mengelola armada lebih efisien?'); // kembali ke default
    }
```

Tambahkan 3 method ini ke dalam class `SuperAdminLandingContentTest` yang sudah ada (sebelum kurung kurawal penutup `}` terakhir).

- [ ] **Step 2: Jalankan test, pastikan lolos**

Run: `php artisan test tests/Feature/SuperAdminLandingContentTest.php`
Expected: `Tests: 6 passed`

- [ ] **Step 3: Jalankan full suite final**

Run: `php artisan test`
Expected: semua hijau. Catat jumlah total test (baseline 204 + test baru sesi ini).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/SuperAdminLandingContentTest.php
git commit -m "test: end-to-end simpan konten landing, fallback per-item, blank kembali default"
```

---

### Task 8: Verifikasi visual di browser

**Files:** tidak ada file diubah — verifikasi manual.

- [ ] **Step 1: Pastikan server lokal jalan**

Run: `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/`
Expected: `200` (kalau bukan, jalankan `php artisan serve` di background dulu)

- [ ] **Step 2: Screenshot form admin dan landing page sebelum/sesudah edit**

Gunakan pendekatan Edge headless via CDP yang sudah dipakai sesi-sesi sebelumnya di proyek ini (`--remote-debugging-port=9222`, login via `fetch` lalu suntik cookie via `Network.setCookie`, screenshot via `Page.captureScreenshot`) untuk:
1. Login sebagai super admin (`platform@lajur.id` / `password`).
2. Screenshot `/superadmin/konten-landing` (form terlihat rapi, placeholder terisi default).
3. Isi salah satu field (mis. `hero_title_lead`) via form sungguhan, submit.
4. Screenshot `/` — pastikan judul hero berubah, section lain tetap seperti semula.
5. Kosongkan lagi field itu, submit, screenshot `/` lagi — pastikan kembali ke teks default (tidak error).

- [ ] **Step 3: Laporkan hasil ke user** dengan ringkas: field mana yang diuji, apakah render sesuai ekspektasi, screenshot dilampirkan.

---

## Self-Review Checklist (dicek penulis plan, bukan pelaksana)

- **Cakupan spec**: 16 section spec (`docs/superpowers/specs/2026-07-20-landing-content-cms-design.md`) semua punya field & task — Task 4 mencakup seluruh 16 panel, Task 6 mencakup seluruh 16 section di `landing.blade.php`. ✓.
- **Konsistensi nama field**: nama key di `LandingCopy::DEFAULTS` (Task 2) = nama field validasi controller (Task 3) = nama `name="..."` input form (Task 4) = key yang dibaca `data_get($stored, ...)` — diperiksa satu per satu saat menulis, semua cocok (`pain_items.*.title`, `feature_groups.*.items.*`, dst).
- **Fallback per-item**: diuji eksplisit di `LandingCopyTest` (unit, Task 2) dan `SuperAdminLandingContentTest` (feature end-to-end, Task 7) — dua lapis.
- **Tidak ada placeholder/TBD**: seluruh kode di setiap step adalah kode lengkap siap tempel, bukan potongan sebagian.
