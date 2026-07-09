# Spec: Demo Tracking + ETA (client-side, Leaflet)

**Tanggal:** 2026-07-09
**Status:** Desain disetujui (belum diimplementasi)
**Tujuan pemakaian:** presentasi/demo ke calon klien travel, tanpa boros API.

## Tujuan

Menampilkan fitur tracking peta + ETA yang meyakinkan tanpa Maps API berbayar
atau Traccar server. Prinsip: **untuk demo, keandalan > realisme** — marker
bergerak mulus + ETA hitung mundur, deterministik, nol risiko gagal di tengah
pitch. Bonus: front-end peta ini sekaligus jadi fondasi visual Fase 2.

## Keputusan yang diambil

- **Panggung:** dua-duanya — `/admin/tracking` (armada bergerak) + `/lacak/{code}`
  (mobil mendekat + ETA countdown).
- **Arsitektur simulasi:** **client-side throwaway** (bukan lewat endpoint asli).
  Bulletproof & simpel; terisolasi dari pipeline produksi, gampang dicabut saat
  Fase 2 asli datang.
- **Peta:** Leaflet + OSM.
- **Rute:** di-precompute sekali via OSRM (offline), disimpan lokal — **nol
  panggilan eksternal saat pitch.**

## Arsitektur

### Aset & renderer
- Leaflet + OSM tiles (tanpa API key).
- `public/js/demo-routes.json`: 1 polyline rute jalan asli Samarinda per mobil
  demo + titik tujuan skenario customer. Dihasilkan sekali via OSRM saat ngoding,
  di-commit.

### Gerakan (100% client-side)
`public/js/tracking-demo.js`: marker interpolasi menyusuri polyline, maju ~X
meter/detik → gerak mulus.
- Admin: beberapa mobil, masing-masing loop di rutenya.
- Customer: satu mobil satu arah menuju tujuan, lalu "Tiba".

### ETA countdown
Sisa jarak = jumlah segmen rute di depan marker. ETA = sisa jarak ÷ kecepatan
asumsi (config, mis. 30 km/jam) atau durasi per-segmen OSRM yang di-precompute.
Angka turun live ("24 → 23 menit") → "Hampir tiba" → "Tiba".

### Pemasangan per panggung (digerbangi `TRACKING_DEMO`)
- `/admin/tracking`: slot peta pakai Leaflet saat demo; render ~4–5 mobil
  bergerak, popup plat + kecepatan.
- `/lacak/{code}`: isi slot `#tracking-map`; satu mobil mendekat + kartu ETA
  hitung mundur; progress bar/`trip_status` maju **secara visual** sinkron marker
  (belum diproses → dalam perjalanan → tiba). Nol tulis DB.

### Saklar ke produksi
`TRACKING_DEMO=true` → simulator client ini. Saat GPS/Traccar asli masuk (Fase
2), slot peta yang sama disuapi endpoint asli. Kode demo terisolasi.

## Default (mudah diubah)
- ~4–5 mobil di peta admin.
- Tujuan customer = satu landmark Samarinda tetap.

## Opsional "wow" (stretch, di luar inti)
Satu HP jalanin aplikasi Traccar Client sebagai unit live beneran ("ini HP saya,
live"). Butuh endpoint Traccar; didokumentasikan terpisah.

## YAGNI (di luar cakupan)
Tanpa perubahan server/pipeline, tanpa tulis DB, tanpa panggilan OSRM/Distance
Matrix saat pitch, tanpa tagihan Google Maps.
