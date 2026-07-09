# Spec: Sistem Mileage (Auto-Mileage & Predictive Maintenance)

**Tanggal:** 2026-07-09
**Status:** Desain disetujui (belum diimplementasi)
**Audiens:** Owner (ops) + customer (ringkasan)

## Tujuan

Ubah jejak GPS jadi tiga nilai: (i) odometer km berjalan per mobil, (ii) prediksi
servis berbasis km yang melengkapi pengingat tanggal, dan (iii) ringkasan jarak
per-booking. "Tambang emas": satu pipeline data, banyak fitur.

## Insight kunci

GPS hanya tahu jarak **sejak tracking mulai**, bukan odometer asli mobil. Servis
dihitung dari odometer absolut. Karena itu owner set **baseline odometer** sekali
(angka odometer asli saat ini); sistem = baseline + km-akumulasi-GPS.

## Keputusan yang diambil

- **Cakupan:** desain penuh (i)+(ii)+(iii), implementasi bertahap.
- **Fondasi:** tabel agregat harian, bukan hitung GPS mentah on-the-fly.

## Arsitektur

### Fondasi data
- **Tabel `car_mileage_daily`** (`car_id`, `date`, `km`): 1 baris per mobil per
  hari. Melayani odometer (jumlah semua), jarak per-booking (jumlah dalam rentang
  tanggal), dan tren.
- **Kolom baru di `cars`:** `odometer_baseline_km` + `baseline_at`,
  `odometer_km` (cache = baseline + total agregat), `service_interval_km`
  (mis. 5000), `service_last_km`.
- **Watermark** per mobil (last `device_time` diproses) supaya sync tidak dobel.

### Pipeline GPS → km (dengan penyaringan)
Scheduled command `mileage:sync` (atau nebeng sync Traccar): per mobil, ambil
posisi baru sejak watermark → jumlah Haversine antar-titik **hanya saat**
`speed > ambang` **atau** jarak antar-titik ≥ minimum; **tolak lonjakan outlier**
(teleport GPS). Tulis ke bucket harian, update cache odometer, geser watermark.
Idempoten.

### Prediksi servis berbasis km
- Servis berikutnya = `service_last_km + service_interval_km`. State:
  overdue / soon (mis. dalam 500 km) / ok — pola sama seperti `reminderState()`.
- **Digabung dengan pengingat tanggal**: alert muncul mana yang lebih dulu
  tercapai. Extend `Car::hasDueReminder()` + widget dashboard yang ada.

### Ringkasan jarak per-booking
Km selama sewa = jumlah `car_mileage_daily` dalam `[start_date, end_date]`.
Tampil di detail booking, invoice, `/lacak`, dan family view: "Jarak tempuh: X km".

### UI
- Daftar/detail mobil: odometer + "servis dalam X km".
- Widget reminder dashboard: tambah baris km-due.
- Booking/invoice/tracking: jarak tempuh.
- Reports (opsional): total km armada.

## Demo
Berbeda dari fitur tracking lain: km dari drift palsu tidak bermakna. Demo pakai
**seeder** yang mengisi `car_mileage_daily` + baseline odometer masuk akal →
UI (odometer, "servis dalam X km", jarak per-booking) langsung bisa dipamerkan.

## Testing
Sync menghitung km benar dengan filter (jitter parkir dikecualikan); watermark
cegah dobel-hitung; state servis-km; jarak per-booking; baseline + akumulasi
benar; isolasi tenant.

## YAGNI (di luar cakupan)
- Tanpa tracking BBM/biaya.
- Tanpa mileage per-driver.
- Baseline odometer manual sekali (tidak menebak dari data lama).

## Dependensi
Butuh GPS asli terkumpul dari waktu ke waktu (Traccar/Fase 2) agar bernilai
penuh. Investasi infrastruktur — cocok di-*tease* ke klien sebagai roadmap,
bukan wajib jadi duluan.

## Urutan implementasi disarankan
(iii) ringkasan jarak per-booking → (i) odometer → (ii) prediksi servis km.
