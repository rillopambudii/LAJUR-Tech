# Fitur BBM / Solar — Pencatatan Pengisian & Deteksi Kebocoran

Tanggal: 2026-07-11 · Status: disetujui Owner (delegasi penuh: "pikirkan dengan sangat detail")

## 1. Masalah

Armada rental memakai solar/bensin yang dibeli lewat driver/operasional. Tanpa
pencatatan terstruktur, ada 4 modus kebocoran yang tidak terlihat:

| # | Modus | Contoh |
|---|-------|--------|
| M1 | Struk digelembungkan / fiktif | klaim 60 L padahal tangki cuma 55 L, atau isi 30 L klaim 50 L |
| M2 | BBM disedot dari tangki lalu dijual | km/L mobil tiba-tiba anjlok jauh di bawah normal |
| M3 | Mobil dipakai trip pribadi | km jalan (GPS) tapi tidak ada booking; BBM ikut terpakai |
| M4 | Harga per liter di-markup | struk SPBU ditulis ulang / beda harga wajar |

## 2. Prinsip desain

1. **Catat semua, jangan blokir.** Input yang "mencurigakan" (mis. liter > kapasitas
   tangki) TETAP boleh disimpan — justru itu bukti yang harus terekam — tapi otomatis
   diberi flag anomali. Memblokir hanya mengajari pelaku membulatkan angkanya.
2. **Silang-periksa 3 sumber km**: odometer manual (diisi saat mengisi BBM), GPS
   (`car_mileage_daily`, sudah ada dari Sistem Mileage), dan jadwal booking. Kecurangan
   di satu sumber ketahuan dari sumber lain.
3. **Metode full-to-full** untuk konsumsi akurat: efisiensi hanya dihitung antara dua
   pengisian penuh yang sama-sama punya odometer (atau fallback km GPS).
4. GPS tetap per-mobil (`vehicle_positions`); tidak ada duplikasi data posisi.

## 3. Skema data

**Tabel baru `fuel_logs`** (tenant-scoped via `BelongsToTenant`):

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| tenant_id | FK | otomatis (trait) |
| car_id | FK cars, cascade | mobil yang diisi |
| filled_at | datetime | waktu pengisian |
| liters | decimal(8,2) | volume |
| price_per_liter | unsignedInteger | Rp |
| total_cost | unsignedInteger | Rp (default liters × price, boleh dikoreksi) |
| odometer_km | unsignedInteger nullable | km odometer saat isi (dianjurkan) |
| full_tank | boolean default true | isi penuh? (metode full-to-full) |
| station | string nullable | SPBU/lokasi |
| notes | text nullable | catatan |
| created_by | FK users nullable, nullOnDelete | siapa yang mencatat |

Index: `(tenant_id, car_id, filled_at)`.

**Kolom baru di `cars`:**
- `tank_capacity_liters` unsignedSmallInteger nullable — kapasitas tangki (dasar flag M1).
- `fuel_baseline_km_per_l` decimal(5,2) nullable — konsumsi normal mobil ini
  (mis. Innova diesel ±12 km/L). Diisi admin di form mobil; dasar flag M2/M3.

## 4. Perhitungan konsumsi (FuelService)

Log per mobil diurutkan `filled_at`. Sebuah **segmen valid** terbentuk antara log
`prev` → `now` bila `now.full_tank` true, dan km segmen bisa ditentukan:

1. **Odometer** (prioritas): `now.odometer_km − prev.odometer_km`, bila keduanya terisi.
2. **Fallback GPS**: jumlah `car_mileage_daily.km` pada tanggal `(prev.filled_at,
   now.filled_at]` bila odometer tak lengkap dan GPS tersedia (> 0 km).

Efisiensi segmen = `km ÷ now.liters` (liter yang diisi penuh di akhir segmen =
liter yang terpakai sepanjang segmen). Agregat per mobil = `Σkm ÷ Σliter` semua
segmen valid — bukan rata-rata dari rasio, supaya segmen panjang berbobot benar.

## 5. Indikator (INI YANG DILIHAT OWNER)

### Per mobil (kartu ringkasan, rentang tanggal terpilih)
| Indikator | Rumus | Arti |
|-----------|-------|------|
| Total liter & biaya | Σ liters, Σ total_cost | belanja BBM |
| Jumlah pengisian | count | frekuensi |
| **Konsumsi aktual (km/L)** | Σkm segmen ÷ Σliter segmen | efisiensi nyata |
| **Deviasi vs baseline** | `(baseline − aktual) / baseline` | > +20% lebih boros = MERAH (M2/M3) |
| Biaya per km | Σbiaya segmen ÷ Σkm | Rp/km, bandingkan antarmobil |
| Selisih GPS vs odometer | Σkm GPS vs Δodometer di periode | > 30% = odometer dimainkan ATAU GPS dicabut |

### Flag anomali per log (badge di daftar, warna = tingkat)
| Kode | Tingkat | Pemicu | Modus |
|------|---------|--------|-------|
| `overfill` | MERAH | liters > tank_capacity_liters | M1 struk digelembungkan |
| `odometer_backwards` | MERAH | odometer_km < log sebelumnya | manipulasi data |
| `guzzling` | MERAH | efisiensi segmen < baseline × 0.8 | M2/M3 BBM hilang |
| `gps_mismatch` | KUNING | odometer vs GPS selisih > 30% (keduanya ada, km ≥ 30) | odometer palsu / GPS dicabut |
| `idle_fill` | KUNING | tanggal isi di luar semua booking aktif mobil itu | M3 dipakai di luar order (bisa sah: persiapan) |
| `price_outlier` | KUNING | harga/L menyimpang > 15% dari median tenant 90 hari (min. 5 log) | M4 markup harga |

MERAH = hampir pasti perlu ditindak; KUNING = perlu ditanya/diperiksa.
Threshold jadi konstanta `FuelService` (mudah dikalibrasi).

## 6. Halaman & alur

- **`/admin/fuel`** (menu sidebar "BBM & Solar"): filter mobil + rentang tanggal;
  kartu ringkasan per mobil (indikator §5); tabel log terbaru dengan badge anomali +
  alasan; tombol export PDF/Excel (fitur export terpisah).
- **`/admin/fuel/create`**: form — mobil, waktu, liter, harga/L (total otomatis di
  browser, tetap bisa dikoreksi), odometer, checkbox "isi penuh", SPBU, catatan.
- **Hapus log**: tombol hapus (confirm). Edit tidak dibuat di v1 (hapus + input ulang).
- **Form mobil** (`/admin/cars/*`): tambah input kapasitas tangki & baseline km/L.

## 7. Di luar cakupan v1 (follow-up)

- Input BBM dari portal driver (v1: admin yang mencatat dari struk).
- Upload foto struk.
- Grafik tren km/L bulanan per mobil.
- Notifikasi otomatis (email/WA) saat flag MERAH muncul.

## 8. Pengujian

`FuelTrackingTest`: perhitungan segmen odometer & fallback GPS; tiap flag anomali
punya kasus positif+negatif; halaman index/create/store/destroy (auth + tenant scope);
liter > kapasitas tetap tersimpan dan ter-flag.
