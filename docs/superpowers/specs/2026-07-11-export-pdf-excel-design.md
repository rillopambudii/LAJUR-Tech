# Export PDF & Excel — Semua Data Operasional

Tanggal: 2026-07-11 · Status: disetujui Owner

## 1. Kebutuhan

Semua data operasional bisa diunduh sebagai **PDF** dan **Excel** dari halaman
adminnya masing-masing, dengan filter (rentang tanggal) yang sedang aktif ikut
terbawa.

## 2. Keputusan teknis

- **PDF**: `barryvdh/laravel-dompdf` (composer, murni PHP, jalan offline setelah
  install). Satu template Blade generik untuk semua dataset tabel.
- **Excel**: **tanpa dependensi** — `App\Exports\XlsxWriter` kecil berbasis
  `ZipArchive` (format .xlsx = zip berisi XML; pakai inline string + sel numerik,
  header bold). Alasan: `maatwebsite/excel` menarik phpspreadsheet yang berat,
  padahal kebutuhan hanya tabel datar; proyek ini konsisten minim dependensi.
- CSV lama di `/admin/reports/export` tetap ada (tidak merusak kebiasaan).

## 3. Arsitektur

- `App\Exports\OperationalDatasets` — registry tunggal: `key → {title, headings,
  rows(from,to)}`. Semua query tenant-scoped lewat model (global scope).
- `Admin\ExportController@download(dataset, format)` — validasi key & format,
  rentang tanggal via query `?from&to` (pola sama dengan ReportController),
  kirim `streamDownload`.
- Route: `GET /admin/export/{dataset}/{format}` (`format` ∈ `xlsx|pdf`).
- Template PDF: `resources/views/admin/exports/table-pdf.blade.php` — judul, nama
  tenant, rentang, waktu cetak, tabel; landscape bila kolom > 8.

## 4. Dataset operasional

| Key | Isi | Sumber |
|-----|-----|--------|
| `bookings` | pesanan: invoice, kode booking, tanggal, mobil, penyewa, kontak, rentang sewa, hari, total, status, trip, driver | Booking (createdBetween) |
| `cars` | armada: nama, plat, tipe, transmisi, BBM, kursi, harga/hari, odometer, servis km, pajak/servis due, status | Car (snapshot, tanpa filter tanggal) |
| `fuel` | log BBM: waktu, mobil, liter, harga/L, total, odometer, penuh?, SPBU, pencatat, flag anomali | FuelLog (filled_at between) |
| `mileage` | km harian per mobil: tanggal, mobil, km | CarMileageDaily (date between) |
| `report` | ringkasan laporan: KPI + breakdown status + top mobil | ReportService |

Tombol "PDF" + "Excel" dipasang di: `/admin/bookings`, `/admin/cars`,
`/admin/fuel`, `/admin/reports` (report: PDF ringkasan; Excel berisi ringkasan
KPI + daftar booking).

## 5. Pengujian

`ExportTest`: tiap dataset × format mengembalikan 200 + header Content-Type &
attachment benar; xlsx bisa dibuka (zip valid berisi `xl/worksheets/sheet1.xml`
dengan data); dataset tak dikenal → 404; tenant lain tidak bocor.
