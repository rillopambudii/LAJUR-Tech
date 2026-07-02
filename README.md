# Lajur — Rental Mobil Premium

Aplikasi web rental mobil premium untuk wilayah Kalimantan Timur: landing page publik (etalase + booking request) dan dashboard admin (CRUD mobil, kelola booking, testimoni, pesan).

**Stack:** Laravel · PHP 8.4 · Blade · MySQL · Vanilla CSS/JS (tanpa build step).

---

## Menjalankan secara lokal (Laragon)

```bash
# 1. Dependensi (sudah terpasang di vendor/)
composer install

# 2. Konfigurasi — .env sudah diset ke MySQL (database: lajur)
#    Pastikan MySQL Laragon berjalan, lalu buat database:
#      CREATE DATABASE lajur;
php artisan key:generate   # hanya jika APP_KEY belum ada

# 3. Migrasi + data demo
php artisan migrate:fresh --seed

# 4. Symlink storage (agar upload gambar bisa diakses publik)
php artisan storage:link

# 5. Jalankan
php artisan serve
```

Buka `http://127.0.0.1:8000`.

---

## Akun Admin Default

| Email | Password |
|---|---|
| `admin@lajur.id` | `password` |

> **Wajib ganti password** setelah login pertama (produksi).

Login admin: `/login` · Dashboard: `/admin`

---

## Struktur Fitur

- **Landing** (`/`): hero, etalase + filter tipe, cara sewa, keunggulan, testimoni, tentang, kontak.
- **Booking**: modal dengan estimasi harga real-time; harga **dihitung ulang di server** + snapshot data mobil; honeypot anti-bot; throttle 10/menit.
- **Kontak**: simpan pesan + IP; honeypot; throttle 10/menit.
- **Admin**: dashboard (statistik + grafik CSS), CRUD mobil (upload foto/preview), kelola booking (status), CRUD testimoni, kelola pesan.

## Keamanan

CSRF di semua form · `$fillable` (mass-assignment) · Form Request + pesan Bahasa Indonesia · middleware `auth` + `admin` · password bcrypt · upload tervalidasi & nama file di-hash · escaping Blade (XSS) · Eloquent (SQL injection) · rate limiting (login 5/mnt) · honeypot · pesan login generik (anti-enumeration) · session regenerate/invalidate · pola snapshot booking.

## Deploy ke shared hosting

Migrasi bersifat portabel. Ubah kredensial `.env` ke MySQL hosting, arahkan document root ke `public/`, jalankan `php artisan migrate --seed` dan `php artisan storage:link`.
