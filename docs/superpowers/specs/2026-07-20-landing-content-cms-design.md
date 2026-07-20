# Dashboard Kelola Konten Landing Page — Design

## Konteks

`resources/views/landing.blade.php` (page induk, domain pusat `lajur.id`) berisi ~13
section marketing dengan seluruh teksnya hardcode langsung di Blade: hero, trust strip,
pain point (5 kartu), sebelum/sesudah, fitur berkelompok (4 kelompok bento), tiga sorotan
produk (BBM, navigasi driver, etalase tenant), highlight lacak-keluarga (4 langkah), preview
GPS, kenapa Lajur (6 kartu), workflow (5 langkah), platform ecosystem (9 item), harga, dan
CTA akhir.

Owner platform ingin mengubah teks-teks ini dari dashboard tanpa minta bantuan edit kode
setiap kali strategi marketing berubah.

## Cakupan (disepakati bersama user)

- **Teks saja.** Ikon, gambar produk, struktur section, jumlah kartu per section — tetap
  seperti sekarang. Tidak ada tambah/hapus/reorder kartu dari dashboard.
- **Landing page saja** (`/` di domain pusat). Halaman lain (pilih paket, syarat, privasi)
  di luar cakupan sesi ini.
- **Tombol CTA "Coba Gratis 14 Hari"** yang muncul 5 kali di halaman (hero, 3 sorotan
  produk, CTA akhir) digabung jadi **satu pengaturan** karena teksnya selalu identik.

## Arsitektur

### Penyimpanan: singleton + JSON

Tabel baru `landing_contents`, hanya berisi **1 baris** (id tetap 1), kolom `content` JSON
nullable. Alasan memilih ini dibanding ~120 kolom individual: section landing didominasi
grup berulang (5 pain point, 4 kelompok fitur dengan jumlah item bervariasi, 6 kartu kenapa-
Lajur, 5 langkah workflow, 9 item ecosystem) — JSON bersarang jauh lebih ringkas daripada
migrasi 120 kolom, dan menambah/mengubah field di masa depan tidak perlu migrasi baru.

Model `App\Models\LandingContent`:
```php
protected $fillable = ['content'];
protected function casts(): array { return ['content' => 'array']; }

public static function current(): array
{
    return static::query()->find(1)?->content ?? [];
}
```

### Resolusi teks + fallback: `App\Content\LandingCopy`

Meniru pola `App\Tenancy\Branding` yang sudah terbukti di codebase ini (resolve per-tenant
override dengan fallback ke default). `LandingCopy`:

- Konstruktor menerima array tersimpan (`LandingContent::current()`).
- Const `DEFAULTS` berisi **seluruh teks yang saat ini hardcode** di `landing.blade.php`
  persis apa adanya — jadi deploy fitur ini tidak mengubah tampilan sama sekali sampai
  owner benar-benar mengedit sesuatu.
- Method getter per field/grup, contoh:
  ```php
  public function heroTitleLead(): string
  public function heroTitleReveal(): string
  public function heroSubtitle(): string
  public function ctaLabel(): string  // dipakai di 5 tombol
  public function painItems(): array  // [['icon','title','text'], ...] × 5, merge per-index ke default
  public function featureGroups(): array
  public function whyItems(): array
  public function workflowSteps(): array
  public function ecosystemItems(): array
  // dst — satu getter per section/field yang dipetakan di bagian "Pemetaan Field" di bawah
  ```
- Untuk grup array: merge **per-item** terhadap default (bukan all-or-nothing), supaya
  mengisi hanya kartu #2 dari 5 pain point tidak membuat 4 kartu lain hilang.
- Ikon per item **tidak disimpan di JSON** — tetap konstanta di `DEFAULTS`/kode, karena
  ikon di luar cakupan (teks saja).

### Controller & rute

`App\Http\Controllers\SuperAdmin\LandingContentController`:
- `edit()` — render form, kirim `$copy` (instance `LandingCopy` dari data tersimpan) +
  `$defaults` (untuk placeholder) ke view.
- `update(Request $request)` — validasi semua field `nullable|string|max:...` (array item
  divalidasi per-key dengan `max:500` untuk title, `max:1000` untuk text/deskripsi), simpan
  via `LandingContent::updateOrCreate(['id' => 1], ['content' => $data])`, redirect back
  dengan flash success.

Rute baru di grup `Route::prefix('superadmin')->middleware(['auth','role:super_admin'])`
yang sudah ada di `routes/web.php`:
```php
Route::get('konten-landing', [LandingContentController::class, 'edit'])->name('landing.edit');
Route::patch('konten-landing', [LandingContentController::class, 'update'])->name('landing.update');
```

Item menu baru "Konten Landing" di `resources/views/layouts/superadmin.blade.php`, sejajar
Plans & Tenant.

### View admin

`resources/views/superadmin/landing/edit.blade.php` — satu form panjang, **satu `<div
class="panel">` per section**, urutan panel identik urutan visual di landing page asli
(memudahkan Anda menavigasi sambil membayangkan tampilannya). Total ±14 panel:

1. Hero (eyebrow, judul baris 1, judul baris 2/aksen, subjudul)
2. Tombol CTA (satu field, dipakai di 5 tempat)
3. Trust Strip atas (kalimat pembuka + label 4 item)
4. Pain Point (eyebrow, judul, subjudul, 5× kartu [judul+teks], kalimat penutup)
5. Sebelum/Sesudah (4× label "sebelum", label brand "sesudah", teks "sesudah")
6. Fitur (judul, subjudul, 4× [judul kelompok + N teks item])
7. Sorotan BBM (judul, teks)
8. Sorotan Navigasi Driver (judul, teks)
9. Highlight Keluarga (judul, subjudul, 4× langkah [judul+teks])
10. Sorotan Etalase Tenant (judul, teks)
11. GPS Preview (label badge, judul, teks, catatan kecil)
12. Kenapa Lajur (judul, 6× [judul+teks])
13. Workflow (judul, 5× [judul+teks])
14. Ecosystem (judul, subjudul, 9× label item, kalimat penutup)
15. Harga (judul, subjudul)
16. CTA Akhir (judul, teks, 3× label trust-strip bawah)

Kartu berulang pakai `@for`/`@foreach` **jumlah tetap** dengan `name="pain_items[0][title]"`
dst — pola identik form "Keunggulan" tenant (`resources/views/admin/site.blade.php`) yang
sudah ada. Setiap input punya `placeholder` = teks default section itu (bukan value —
kalau field kosong di DB, input tampil kosong dengan placeholder abu-abu menunjukkan apa
yang akan tampil di halaman). Tombol "Lihat Halaman" di kepala form membuka `/` di tab baru.

### Integrasi ke landing page

`LandingController::index()` menambah `$copy = new LandingCopy(LandingContent::current())`
ke `compact()`. `landing.blade.php` **tidak berubah struktur/class/animasi apa pun** — hanya
string literal diganti panggilan `$copy->method()`. Contoh:

```blade
{{-- sebelum --}}
<span class="hero-title__lead">Kelola seluruh operasional armada</span>

{{-- sesudah --}}
<span class="hero-title__lead">{{ $copy->heroTitleLead() }}</span>
```

## Testing

`tests/Feature/SuperAdminLandingContentTest.php`:
- Superadmin bisa buka form (`assertOk`, melihat placeholder default).
- Superadmin bisa simpan sebagian field → landing page (`GET /`) menampilkan teks baru
  untuk field yang diisi, dan teks default untuk field yang dikosongkan (verifikasi
  fallback per-item, bukan all-or-nothing).
- Non-superadmin (owner tenant biasa) ditolak (`assertForbidden`) baik ke `edit` maupun
  `update`.
- Validasi menolak field yang melebihi panjang maksimum.

## Di luar cakupan (eksplisit, jangan dikerjakan)

- Upload/ganti gambar produk (`product-*.jpg`).
- Tambah/hapus/reorder kartu (jumlah pain point, kelompok fitur, dst tetap seperti kode
  saat ini).
- Ubah ikon per kartu.
- Halaman lain selain landing (`/daftar/harga`, `/syarat`, `/privasi`).
- Multi-bahasa / versioning konten.
