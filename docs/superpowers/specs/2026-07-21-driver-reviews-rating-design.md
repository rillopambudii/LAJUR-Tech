# Rating & Ulasan Driver — Design

## Konteks

Halaman publik "Lacak Pesanan" (`/lacak/{kode}`) dan "Pantau Perjalanan" (`/pantau/{kode}`)
sudah menampilkan nama driver dari sebuah booking (`$booking->driver?->name`), sebagai teks
polos tanpa foto atau rating. `Booking` sudah punya status `completed` yang jadi gerbang
alami untuk menandai kapan sebuah perjalanan benar-benar selesai dan boleh dinilai.

Model `Testimonial` yang sudah ada TIDAK dipakai ulang — itu konten marketing yang dikurasi
manual oleh owner, bukan ulasan asli dari transaksi customer. Fitur ini butuh model baru
yang terikat ke booking sungguhan.

Tujuan: customer yang sudah menyelesaikan sewa bisa memberi rating + ulasan untuk driver
yang menangani perjalanannya, ulasan itu tampil di profil publik driver, dan driver melihat
hasil penilaiannya di dashboard sendiri.

## Cakupan (disepakati saat brainstorming)

**Masuk scope:**
- Rating multi-kriteria: Ketepatan Waktu, Kebersihan & Kondisi Mobil, Keramahan & Sikap,
  Keamanan Berkendara (masing-masing 1-5), plus rating keseluruhan (rata-rata 4 aspek).
- Komentar teks opsional menyertai rating.
- Satu ulasan per booking (booking `completed` + punya driver + belum pernah diulas).
- Moderasi: ulasan baru berstatus `pending`, admin approve/reject dari panel admin sebelum
  tampil publik.
- Balasan admin/owner atas ulasan (bukan driver — reputasi tetap satu pintu lewat admin).
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

### Alur 1 — Customer mengisi ulasan (publik, tanpa login)

Di `TrackingController@show` (halaman `/lacak/{kode}` yang sudah ada), tambah data ke view:
- Jika `$booking->status === 'completed'` DAN `$booking->driver_id` ada:
  - Jika belum ada `DriverReview` untuk booking ini → tampilkan form: 4 pemilih bintang +
    textarea komentar opsional.
  - Jika sudah ada → tampilkan ringkasan ulasan yang sudah dikirim, dengan catatan status
    ("Ulasan Anda sedang ditinjau" untuk `pending`, atau tampil penuh untuk `published`).
- Booking tanpa driver, atau belum `completed` → bagian ulasan tidak muncul sama sekali.

Route baru: `POST /lacak/{bookingCode}/ulasan` → `DriverReviewController@store`, publik,
`throttle:5,1` (pola sama seperti `booking.store`). Validasi: booking ditemukan (tenant-
scoped otomatis via global scope), status `completed`, punya driver, belum ada ulasan untuk
booking ini (re-check server-side, jangan percaya state di form saja), 4 rating wajib
integer 1-5, komentar `nullable|string|max:500`.

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

Panel baru "Ulasan Driver" di admin (`AdminReviewController`, meniru struktur
`TestimonialController` yang sudah ada): daftar ulasan dengan filter status, aksi
setujui (`pending`→`published`), tolak (`pending`/`published`→`rejected`), dan form balas
(isi `admin_reply` + `replied_at`, bisa dilakukan di status apa pun). Item menu baru di
sidebar admin, sejajar "Testimoni".

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

`tests/Feature/DriverReviewTest.php`:
- Customer bisa submit ulasan untuk booking `completed` miliknya → tersimpan status `pending`.
- Submit kedua untuk booking yang sama ditolak (booking sudah pernah diulas).
- Submit untuk booking yang belum `completed`, atau tanpa driver, ditolak.
- Ulasan `pending` tidak tampil di profil publik driver; setelah admin approve baru tampil.
- Admin bisa approve/reject/balas ulasan (tenant-scoped — tidak bisa moderasi ulasan tenant lain).
- Profil publik driver dari tenant lain / role bukan driver → 404.
- Badge rating tampil di kartu dashboard driver hanya untuk booking dengan ulasan `published`.
- `rating_overall` terhitung benar sebagai rata-rata 4 aspek.

## Di luar cakupan (eksplisit, jangan dikerjakan sesi ini)

- Lencana/badge driver terbaik.
- Notifikasi WhatsApp otomatis.
- Jendela waktu kedaluwarsa ulasan.
- Driver membalas ulasan sendiri.
- Login/akun customer.
- Edit ulasan setelah dikirim (baik oleh customer maupun sistem).
