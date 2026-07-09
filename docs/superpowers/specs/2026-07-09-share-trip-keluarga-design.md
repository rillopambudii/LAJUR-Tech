# Spec: Share Trip ke Keluarga

**Tanggal:** 2026-07-09
**Status:** Desain disetujui (belum diimplementasi)
**Audiens:** Customer (berbagi ke keluarga)

## Tujuan

Customer membagikan perjalanannya secara langsung ke keluarga demi rasa aman —
keluarga bisa memantau lokasi mobil real-time tanpa melihat detail harga/finansial.
Angle keselamatan yang emosional dan menjual.

## Temuan awal (kondisi sekarang)

`GET /lacak/{code}` **sudah** bisa dibuka publik hanya dengan kode (nomor HP cuma
diminta di form pencarian). Jadi link tracking secara teknis sudah bisa dibagikan.
Halaman itu menampilkan harga & total. Fitur ini menutup 3 celah:
1. Belum ada tombol share.
2. Halaman `/lacak` membocorkan harga ke siapa pun yang dibagikan link.
3. Peta live belum ada (Fase 2 / Traccar).

## Keputusan yang diambil

- **Bentuk:** family view khusus (halaman read-only ramping, tanpa finansial).
- **Model link:** pakai `booking_code` yang sama pada route baru `/pantau/{code}`.
  **Nol migrasi.** Trade-off diterima: pemilik link bisa buka `/lacak/{code}` &
  lihat harga — cukup untuk keluarga tepercaya.
- **Peta:** Leaflet + OSM.

## Arsitektur

### Titik masuk (di `/lacak/{code}`)
Tombol **"Bagikan ke keluarga"**: Web Share API (share sheet native di HP) dengan
fallback wa.me prefilled. Teks: *"Pantau perjalanan saya (mobil {nama}) secara
langsung: {link}"* → link `/pantau/{code}`.

### Family view — `GET /pantau/{code}`
Route publik baru (reuse lookup by-code, layout `public`, read-only). Tampil:
- Judul hangat: "Perjalanan {nama depan customer}".
- Peta live (marker mobil) + auto-refresh.
- Status perjalanan + progress bar (reuse komponen `/lacak`).
- ETA (`eta_manual_note`; auto-ETA menyusul Fase 2).
- Mobil + plat + nama driver.
- Tombol "Hubungi CS" (WA).

**Disembunyikan:** harga/total, email, detail finansial.

### Peta live + demo
- Marker dari `$booking->car->latestPosition`, auto-refresh polling ~20–30 dtk ke
  endpoint kecil `/pantau/{code}/pos`.
- `TRACKING_DEMO` & GPS kosong → fabrikasi titik bergerak (pola `demoPositions`).
- Tanpa GPS & demo mati → "Mobil belum berangkat, lokasi langsung belum aktif."

### Keamanan & privasi
`/pantau/{code}` publik (kode = kapabilitas tak tertebak), tenant-scoped, tanpa
PII finansial (nama depan saja).

## Testing
`/pantau/{code}` tampil tanpa harga (`assertDontSee` Rp/total) tapi tampil status
+ mobil; kode salah → redirect; endpoint posisi kembalikan titik (demo saat
kosong); isolasi tenant.

## YAGNI (di luar cakupan)
- Tanpa token terpisah/kedaluwarsa (pakai kode).
- Tanpa migrasi.
- Tanpa notifikasi push ke keluarga.
- Auto-ETA menunggu Fase 2.

## Catatan
Membangun peta live family view = menyelesaikan sepotong Fase 2 (peta live
customer + polling), di-scope ke family view dulu.
