# Rating & Ulasan Driver — Design

## Konteks

Halaman publik "Lacak Pesanan" (`/lacak/{kode}`) dan "Pantau Perjalanan" (`/pantau/{kode}`)
sudah menampilkan nama driver dari sebuah booking (`$booking->driver?->name`), sebagai teks
polos tanpa foto atau rating. `Booking` sudah punya status `completed` yang jadi gerbang
alami untuk menandai kapan sebuah perjalanan benar-benar selesai dan boleh dinilai.

Model `Testimonial` yang sudah ada sejauh ini isinya dikurasi manual oleh owner. Ulasan
driver (Jalur A) butuh model baru karena bentuk datanya beda total (4 rating + terikat ke
driver tertentu) — tapi ulasan bisnis (Jalur B) bentuknya PERSIS sama dengan `Testimonial`
yang sudah ada (nama, rating tunggal, kutipan), jadi cukup ditambah `booking_id` nullable
sebagai sumber baru, bukan tabel terpisah.

Tujuan: customer yang sudah menyelesaikan sewa bisa memberi **dua jenis ulasan terpisah**
dari satu halaman yang sama:
1. **Ulasan untuk driver** — tampil di profil publik driver.
2. **Ulasan untuk bisnis/tenant** — begitu disetujui admin, jadi testimoni yang tayang di
   landing page (etalase tenant), memakai model `Testimonial` yang sudah ada (dipakai
   marquee testimoni yang sudah kita bangun sebelumnya) — bukan sistem terpisah.

Driver melihat hasil penilaiannya sendiri di dashboard; owner melihat testimoni bisnis yang
masuk dan bisa menerbitkannya ke landing page persis seperti testimoni manual yang sudah ada.

## Cakupan (disepakati saat brainstorming)

**Masuk scope:**
- **Jalur A — Ulasan Driver**: rating multi-kriteria (Ketepatan Waktu, Kebersihan & Kondisi
  Mobil, Keramahan & Sikap, Keamanan Berkendara, masing-masing 1-5) + rating keseluruhan
  (rata-rata 4 aspek) + komentar opsional. Satu ulasan per booking. Moderasi via status
  `pending`/`published`/`rejected`. Tampil di profil publik driver (rute baru).
- **Jalur B — Ulasan Bisnis (Testimoni)**: rating tunggal 1-5 + kutipan teks, memakai model
  `Testimonial` yang sudah ada (yang sudah tayang di marquee landing page). Satu testimoni
  per booking. Masuk sebagai `is_published = false` (moderasi via toggle yang SUDAH ADA di
  form edit Testimoni admin — tidak ada mekanisme moderasi baru untuk jalur ini).
- Kedua jalur independen — customer boleh isi salah satu, keduanya, atau tidak sama sekali.
  Sama-sama digerbangi: booking `completed` (Jalur A tambahan syarat: booking punya driver).
- Balasan admin/owner atas ulasan driver (bukan driver — reputasi tetap satu pintu lewat
  admin). Testimoni bisnis TIDAK punya fitur balasan (di luar cakupan; testimoni yang ada
  sekarang juga tidak punya).
- Halaman profil publik driver (foto, rating rata-rata + breakdown 4 aspek, jumlah
  perjalanan selesai, daftar ulasan published).
- Badge rating di kartu "Tugas Selesai" dashboard driver + badge rating rata-rata di
  halaman "Profil Saya" driver (sudah ada dari sesi sebelumnya).

**Ditunda (bukan di iterasi ini, jangan dikerjakan):**
- Lencana "Driver Terbaik Bulan Ini".
- Notifikasi WhatsApp otomatis ke driver saat dapat ulasan baru.
- Jendela waktu kedaluwarsa untuk mengisi ulasan.
- Driver membalas ulasan sendiri (hanya admin/owner untuk sekarang).
- Akun customer / login customer (submit ulasan tetap tanpa login, via kode booking).

## Arsitektur

### Data: tabel `driver_reviews`

```
id
tenant_id            (BelongsToTenant — auto-scope, sama pola dgn Testimonial)
booking_id            FK bookings, UNIQUE (satu booking = maks satu ulasan), cascade on delete
driver_id             FK users
rating_punctuality    tinyint unsigned (1-5)
rating_cleanliness    tinyint unsigned (1-5)
rating_friendliness   tinyint unsigned (1-5)
rating_safety         tinyint unsigned (1-5)
rating_overall        decimal(2,1)  — rata-rata 4 kolom di atas, dihitung & disimpan saat create
comment               text nullable, maks 500 karakter
status                string, default 'pending'  — 'pending' | 'published' | 'rejected'
admin_reply           text nullable
replied_at            timestamp nullable
timestamps
```

Model `DriverReview` pakai trait `BelongsToTenant` (pola identik `Testimonial`). Nama &
identitas customer TIDAK disnapshot — diambil langsung dari relasi `booking.customer_name`
saat tampil (booking tidak pernah terhapus dalam alur normal, jadi tidak ada duplikasi data).

### Perubahan tabel `testimonials` (jalur B)

Tambah dua kolom nullable via migration:
```
booking_id   FK bookings, nullable, UNIQUE bila terisi (partial unique — satu booking
             maks satu testimoni bisnis, tapi banyak baris lama/manual boleh sama-sama null)
```
`is_published` (sudah ada) dipakai sebagai status moderasi jalur B — testimoni dari customer
dibuat dengan `is_published = false`, admin menerbitkannya lewat form edit Testimoni yang
SUDAH ADA (checkbox "Tampilkan"). Tidak ada kolom/status baru di tabel ini. Badge "Dari
Customer" vs "Manual" di panel admin cukup dihitung dari `booking_id !== null`, tidak perlu
kolom `source` terpisah.

### Alur 1 — Customer mengisi ulasan (publik, tanpa login)

Di `TrackingController@show` (halaman `/lacak/{kode}` yang sudah ada), tambah data ke view.
Section "Beri Ulasan" tampil kalau `$booking->status === 'completed'`, berisi dua sub-form
independen:

**1a. Ulasan Driver** (hanya tampil kalau `$booking->driver_id` ada):
- Belum ada `DriverReview` untuk booking ini → form: 4 pemilih bintang + textarea komentar
  opsional.
- Sudah ada → tampilkan ringkasan ulasan yang sudah dikirim + catatan status ("Ulasan Anda
  sedang ditinjau" untuk `pending`, tampil penuh untuk `published`).

**1b. Ulasan untuk [Nama Tenant]** (selalu tampil kalau booking `completed`, tak bergantung
driver):
- Belum ada `Testimonial` dengan `booking_id` = booking ini → form: pemilih bintang tunggal
  1-5 + textarea kutipan (wajib, sama seperti `TestimonialRequest` yang sudah ada).
- Sudah ada → tampilkan "Terima kasih, ulasan Anda sedang ditinjau" (atau kutipannya bila
  sudah `is_published`).

Dua route baru, POST, publik, `throttle:5,1` (pola sama seperti `booking.store`):
- `POST /lacak/{bookingCode}/ulasan-driver` → `DriverReviewController@store`. Validasi:
  booking ditemukan (tenant-scoped otomatis), status `completed`, punya driver, belum ada
  ulasan untuk booking ini (re-check server-side), 4 rating wajib integer 1-5, komentar
  `nullable|string|max:500`.
- `POST /lacak/{bookingCode}/ulasan-bisnis` → `PublicTestimonialController@store`. Validasi:
  booking ditemukan, status `completed`, belum ada testimoni untuk booking ini, rating
  wajib integer 1-5, kutipan wajib `string|max:2000` (batas sama seperti `TestimonialRequest`).
  Buat `Testimonial` baru: `name` = `$booking->customer_name`, `role` = null, `rating`,
  `quote`, `avatar` = null, `is_published` = false, `booking_id` = booking ini,
  `sort_order` = 0.

### Alur 2 — Profil publik driver

Route baru: `GET /pengemudi/{driver}` → `DriverProfileController@showPublic` (nama controller
beda dari `App\Http\Controllers\Driver\DriverProfileController` yang sudah ada — itu untuk
driver melihat profil sendiri saat login; ini untuk publik). Guard: kalau `$driver->role !==
User::ROLE_DRIVER`, `abort(404)` — mencegah URL ini dipakai mengintip akun owner/admin.

Isi halaman: foto (pakai `<x-avatar>` yang sudah ada), nama, rating keseluruhan (rata-rata
`rating_overall` semua ulasan `published`), breakdown rata-rata per 4 aspek, jumlah booking
`completed` sebagai driver ini, daftar ulasan `published` terbaru dulu (paginate), nama
customer disamarkan (`"Budi S."` — nama depan penuh + inisial kata terakhir), dan
`admin_reply` ditampilkan di bawah ulasan yang sudah dibalas.

Ditautkan dari blok info driver di `tracking/show.blade.php` dan `tracking/watch.blade.php`
(link "Lihat Profil Driver" di sebelah nama driver yang sudah tampil).

### Alur 3 — Moderasi admin

**Ulasan driver (Jalur A):** panel baru "Ulasan Driver" di admin (`AdminReviewController`,
meniru struktur `TestimonialController` yang sudah ada): daftar ulasan dengan filter status,
aksi setujui (`pending`→`published`), tolak (`pending`/`published`→`rejected`), dan form
balas (isi `admin_reply` + `replied_at`, bisa dilakukan di status apa pun). Item menu baru
di sidebar admin, sejajar "Testimoni".

**Ulasan bisnis (Jalur B):** TIDAK ada panel/controller baru. Admin memoderasi lewat halaman
"Testimoni" yang SUDAH ADA (`admin.testimonials.index`/`edit`) — testimoni dari customer
otomatis nongol di daftar yang sama (default `is_published = false`, jadi belum tayang
sampai admin buka form edit dan centang "Tampilkan"). Satu tweak kecil ke
`admin/testimonials/index.blade.php`: tambah badge kecil "Dari Customer" pada baris yang
`booking_id`-nya terisi, supaya admin bisa bedakan dari testimoni buatannya sendiri.

### Alur 4 — Tampilan di dashboard driver

`driver/dashboard.blade.php` (kartu riwayat "Tugas Selesai" yang sudah ada): untuk tiap
booking yang punya `DriverReview` berstatus `published`, tampilkan badge bintang kecil
(`★ 4.5`) di kartu itu. Booking tanpa ulasan atau ulasan masih `pending`/`rejected` tidak
menampilkan badge (driver tidak perlu lihat ulasan yang belum final).

`driver/profile.blade.php` (halaman "Profil Saya" driver, sudah ada dari sesi sebelumnya):
tambah satu stat tile baru "Rating" menampilkan rata-rata `rating_overall` semua ulasan
`published` milik driver ini, di samping "Tugas Aktif" dan "Perjalanan Selesai" yang sudah
ada.

## Testing

`tests/Feature/DriverReviewTest.php` (Jalur A):
- Customer bisa submit ulasan untuk booking `completed` miliknya → tersimpan status `pending`.
- Submit kedua untuk booking yang sama ditolak (booking sudah pernah diulas).
- Submit untuk booking yang belum `completed`, atau tanpa driver, ditolak.
- Ulasan `pending` tidak tampil di profil publik driver; setelah admin approve baru tampil.
- Admin bisa approve/reject/balas ulasan (tenant-scoped — tidak bisa moderasi ulasan tenant lain).
- Profil publik driver dari tenant lain / role bukan driver → 404.
- Badge rating tampil di kartu dashboard driver hanya untuk booking dengan ulasan `published`.
- `rating_overall` terhitung benar sebagai rata-rata 4 aspek.

`tests/Feature/PublicTestimonialSubmissionTest.php` (Jalur B):
- Customer bisa submit testimoni untuk booking `completed` miliknya → `Testimonial` tersimpan
  dengan `is_published = false` dan `booking_id` terisi.
- Submit kedua untuk booking yang sama ditolak.
- Submit untuk booking yang belum `completed` ditolak.
- Testimoni belum `is_published` tidak tampil di marquee landing page; setelah admin
  centang "Tampilkan" via form edit yang sudah ada, baru tampil.
- Testimoni manual lama (tanpa `booking_id`) tidak terpengaruh sama sekali oleh perubahan ini.

## Di luar cakupan (eksplisit, jangan dikerjakan sesi ini)

- Lencana/badge driver terbaik.
- Notifikasi WhatsApp otomatis.
- Jendela waktu kedaluwarsa ulasan.
- Driver membalas ulasan sendiri.
- Login/akun customer.
- Edit ulasan setelah dikirim (baik oleh customer maupun sistem).
