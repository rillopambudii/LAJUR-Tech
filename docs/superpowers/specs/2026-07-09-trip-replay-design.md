# Spec: Trip Replay (Admin)

**Tanggal:** 2026-07-09
**Status:** Desain disetujui (belum diimplementasi)
**Audiens:** Admin/Owner (fase pertama)

## Tujuan

Memutar ulang rute yang ditempuh sebuah mobil selama satu booking, sebagai alat
bukti sengketa, klaim asuransi, dan audit ("mobil ini dibawa ke mana selama
sewa"). Sekaligus jadi batu loncatan pipeline "jarak dari GPS" untuk fitur
Sistem Mileage.

## Keputusan yang diambil

- **Audiens:** admin dulu (di `admin/bookings/show`), belum ke customer.
- **Jendela replay:** seluruh masa sewa, `[start_date 00:00, end_date 23:59]`.
  Pakai kolom yang sudah ada — **nol migrasi**.
- **Data demo:** fabrikasi on-the-fly saat `TRACKING_DEMO=true` & window kosong
  (pola sama seperti `demoPositions()`), deterministik per-booking.
- **Peta:** Leaflet + OpenStreetMap (nol biaya/quota, lepas dari Google Maps API).

## Arsitektur

### Endpoint
`GET /admin/bookings/{id}/replay` → JSON titik `{lat, lng, time, speed}` untuk
mobil booking dalam jendela masa sewa, urut `device_time`.

- Logika nyaris identik dengan `Admin\TrackingController@history` yang sudah ada,
  di-scope per-booking. Pakai relasi `Car::positions()` + `whereBetween`.
- Tenant-scoped otomatis (`BelongsToTenant` di `VehiclePosition`) + guard
  cross-tenant 404 (pola yang sudah dipakai).

### Fabrikasi demo
Saat `TRACKING_DEMO=true` dan window kosong: bikin rute masuk akal menyusuri
jalan Samarinda, timestamp tersebar sepanjang masa sewa, kecepatan bervariasi.
Seed dari booking id → hasil sama tiap kali diputar (stabil untuk demo). Tidak
pernah ditulis ke DB.

### Frontend
File baru `public/js/booking-replay.js`:
- Gambar polyline penuh (samar) + marker bergerak menyusuri rute.
- Kontrol: play/pause, kecepatan 1x/2x/4x/8x, scrubber timeline.
- Readout live: jam, kecepatan, jarak tempuh sejauh ini.
- Kartu ringkasan: total jarak (jumlah Haversine), durasi, kecepatan maks/rata,
  jam mulai–selesai.

### Penempatan UI
Tombol **"Replay Perjalanan"** di `admin/bookings/show` (muncul kalau booking
punya mobil) → buka panel/modal replay. (Keputusan modal vs halaman terpisah
`/admin/bookings/{id}/replay` masih terbuka — default: modal di halaman sama.)

## Edge case
- Mobil belum di-assign / tidak ada titik & demo mati → "Tidak ada data GPS
  untuk perjalanan ini."
- Booking beda tenant → 404.

## Testing
Feature test: endpoint mengembalikan titik untuk window booking; isolasi tenant
(cross-tenant 404); fabrikasi demo saat kosong; empty-state saat demo mati.

## YAGNI (di luar cakupan)
- Tanpa kolom/migrasi baru.
- Belum ada replay sisi customer.
- Belum ada timestamp `trip_status` (pakai window seluruh masa sewa).

## Dependensi
Data GPS asli (`vehicle_positions`) dari Traccar untuk replay non-demo; sampai
itu ada, hanya jalur demo yang berfungsi.
