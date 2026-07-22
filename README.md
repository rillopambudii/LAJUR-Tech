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

Migrasi bersifat portabel. Arahkan document root ke `public/`, lalu ikuti urutan di bawah.

### 1. Berkas & database

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate          # hanya sekali, saat instalasi baru
php artisan migrate --force
php artisan db:seed --force       # hanya instalasi baru (isi paket, armada demo)
php artisan storage:link
```

### 2. Isi `.env` produksi

Yang **wajib** diubah dari nilai pengembangan:

| Variabel | Nilai produksi | Kenapa penting |
|---|---|---|
| `APP_ENV` | `production` | Mengaktifkan perilaku & optimasi produksi. |
| `APP_DEBUG` | `false` | Kalau `true`, halaman error menampilkan stack trace **beserta isi konfigurasi** (password DB, API key) ke pengunjung mana pun. |
| `APP_URL` | `https://domainmu` | Dipakai untuk tautan reset password, URL gambar, dan subdomain tenant. Salah isi = tautan di email mengarah ke localhost. |
| `MAIL_MAILER` | `smtp` (+ kredensial) | Selama masih `log`, email hanya ditulis ke berkas log: tautan lupa-password tak pernah sampai ke owner, invoice tak sampai ke pelanggan. |
| `MAIL_FROM_ADDRESS` | alamat domain sendiri | Alamat `example.com` hampir pasti ditolak/masuk spam. |
| `MIDTRANS_IS_PRODUCTION` | `true` **dan** kunci produksi | Mode dan kunci harus dari lingkungan yang sama. Kunci sandbox berawalan `SB-`. Campur = Midtrans menolak, pelanggan tak bisa membayar. |

### 3. Cron — jangan dilewati

```
* * * * * cd /path/ke/app && php artisan schedule:run >> /dev/null 2>&1
```

Satu baris ini menjalankan tiga tugas terjadwal:

- `tenants:check-trial` (02:00) — mengunci tenant yang masa trial/langganannya habis. **Tanpa cron, trial tidak pernah kedaluwarsa dan pelanggan memakai sistem gratis selamanya.**
- `db:backup` (00:30) — cadangan harian, menyimpan 14 terbaru.
- `mileage:sync` (01:00) — hitung ulang jarak tempuh harian dari data GPS.

### 4. Periksa hasilnya

```bash
php artisan lajur:preflight
```

Perintah ini memeriksa semua poin di atas (debug, URL, email, kecocokan kunci Midtrans, storage link, migration, umur backup) dan menyebutkan persis apa yang perlu dibereskan. Jalankan **setiap kali selesai deploy**.

### Cadangan & pemulihan

```bash
php artisan db:backup             # manual, kapan saja
php artisan db:backup --keep=30   # simpan lebih banyak
```

Berkas tersimpan di `storage/app/backups` (tidak ikut git). Pemulihan:

```bash
gunzip -c storage/app/backups/lajur-YYYY-MM-DD_HHMMSS.sql.gz | mysql -u USER -p NAMA_DB
```

> Belum ada model yang memakai *soft delete* — sekali data dihapus lewat panel, hilang permanen. Backup harian ini satu-satunya jaring pengaman, jadi pastikan cron benar-benar jalan.
