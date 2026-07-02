# Lajur → Rental Management System (SaaS) — Blueprint

> Status: **draft / menunggu keputusan Owner.** Dokumen ini mengubah "Lajur" (rental
> mobil single-tenant) menjadi produk SaaS berlangganan yang bisa dijual ke banyak
> bisnis rental. Payment gateway sengaja ditunda (lihat Phase 3).

## Kondisi sekarang (baseline)

- **Stack:** Laravel 12 · PHP 8.3 · Blade · MySQL · vanilla CSS/JS (vite tersedia).
- **Model:** `User`, `Car`, `Booking`, `ContactMessage`, `Testimonial`.
- **Auth:** satu peran saja (middleware `admin`), belum ada multi-user/multi-role.
- **Tenancy:** TIDAK ADA. Semua query mengasumsikan satu bisnis (Lajur).
- **Data booking:** sudah punya `total_price`, `status`, `created_at` → laporan
  pendapatan bisa dihitung tanpa perubahan skema.

## Keputusan fondasi (harus diambil sebelum coding)

### 1. Model tenancy — **Rekomendasi: Single DB + `tenant_id` + global scope**
Satu database, setiap tabel bisnis (`cars`, `bookings`, `contact_messages`,
`testimonials`, `users`) mendapat kolom `tenant_id`. Isolasi dijamin oleh Eloquent
Global Scope yang otomatis menambahkan `WHERE tenant_id = <current>` di setiap query.

- Alternatif "DB per tenant" ditunda kecuali ada kebutuhan enterprise/data sangat
  sensitif — biaya migrasi/backup/deploy jauh lebih tinggi.
- Risiko utama single-DB = query yang lupa scope → kebocoran antar-tenant. Mitigasi:
  Global Scope wajib + trait `BelongsToTenant` + test isolasi.

### 2. Model peran — Owner / Admin / Driver / Customer
Gabung ke fondasi (bukan fitur terpisah), karena "Owner punya banyak Admin" dan
"Driver punya akun" adalah bagian dari definisi tenant.

---

## Roadmap bertahap

### Phase 0 — Tenancy + Role Management  ← SEDANG DIKERJAKAN (inti selesai)
- [x] Tabel `tenants` (nama rental, slug/subdomain, status langganan, plan).
- [x] Kolom `tenant_id` + FK di `cars`, `bookings`, `contact_messages`,
      `testimonials`, `users` (nullable + di-backfill ke tenant default `lajur`).
- [x] Trait `BelongsToTenant` + `TenantScope` (global scope, null-safe) untuk model bisnis.
- [x] Resolusi tenant aktif: `IdentifyTenant` middleware (user → subdomain → default).
- [x] Kolom `role` pada `users` (owner/admin/driver/customer).
- [x] Middleware `role:owner,admin` + `admin` diperbarui (owner & admin punya akses back office).
- [x] Seeder: data demo Lajur menjadi tenant #1; akun admin lama → role `owner`.
- [x] **Test isolasi**: `tests/Feature/TenancyIsolationTest.php` (hijau).
- [ ] **Registrasi tenant mandiri** (signup Owner → buat tenant + slug → seed data contoh
      → login). ← SATU-SATUNYA SISA PHASE 0, butuh keputusan alur signup.
- [ ] (Nanti) email unik per-tenant (kini masih unik global) saat customer bisa daftar
      di banyak rental.

**Catatan implementasi:** `User` sengaja TIDAK diberi global scope tenant agar auth/login
tidak rusak (tenant di-resolve DARI user). Scope model bisnis bersifat null-safe: tanpa
konteks tenant (console/seed/super-admin) query tidak difilter — perilaku lama tetap utuh.

### Phase 1 — Booking & kalender ketersediaan  ← SEDANG DIKERJAKAN
- [x] **Cegah double-booking**: `Booking::scopeActive/scopeOverlapping`,
      `Car::isAvailableForRange()`; ditegakkan di alur booking publik
      (BookingController). Status pemblokir = pending + confirmed.
- [x] **Kalender ketersediaan armada** (admin): `/admin/calendar` — grid mobil × hari,
      sel berwarna per status, navigasi bulan, link ke detail booking. Menu sidebar "Kalender".
- [x] Test: `tests/Feature/BookingAvailabilityTest.php` (5 test, hijau).
- [ ] **Live-check di modal booking publik** (tampilkan tanggal terpesan sebelum submit
      via endpoint JSON) — enhancement UX, belum dikerjakan.
- [ ] Status booking granular + **histori perubahan status**.
- [ ] **QR Code** konfirmasi booking.

### Phase 2 — Manajemen armada & driver  ← SELESAI (inti)
- [x] **Akun driver (CRUD)** — `/admin/drivers`, users role=driver, tenant-scoped manual
      (User tanpa global scope), guard 404 lintas-tenant. Menu sidebar "Driver".
- [x] **Penugasan driver ke booking** — `bookings.driver_id`, dropdown di detail booking,
      validasi driver harus milik tenant yang sama.
- [x] **Dashboard driver** — `/driver` (role:driver), layout terpisah, jadwal tugas
      mendatang + riwayat. Login kini berbasis peran (owner/admin→/admin, driver→/driver).
- [x] **Pengingat servis & pajak** — kolom `plate_number`, `tax_due_date`,
      `service_due_date` di cars; helper status (overdue/soon/ok); widget di dashboard
      (jendela {{REMINDER_WINDOW_DAYS}}=30 hari). Notifikasi otomatis (WA/email) → Phase 3.
- [x] Test: `DriverManagementTest` (6) + `FleetReminderTest` (3). Total suite 19 hijau.

### Phase 3 — Invoice + notifikasi  (payment gateway = MASIH DITUNDA)
- [x] Abstraksi `PaymentGateway` (interface) + `ManualPaymentGateway` (offline)
      di-bind sebagai default. Midtrans/Xendit/Tripay tinggal implement + bind.
- [x] **Invoice** — halaman cetak `/admin/bookings/{id}/invoice` (CSS print →
      "Simpan sebagai PDF" dari browser) + nomor `INV/{SLUG}/{tahun}/{id}`.
- [x] **Email invoice** — `BookingInvoiceMail` + tombol kirim di detail booking
      (dev: MAIL_MAILER=log). 
- [x] **WhatsApp** — link `wa.me` dengan pesan invoice terisi otomatis
      (`Booking::whatsappUrl`, normalisasi 08→62). Dependency-free.
- [x] Test: `InvoiceNotificationTest` (4). Total suite **23 hijau**.
- [ ] (Nanti) PDF server-side (dompdf) untuk lampiran email; WA API otomatis
      (Fonnte/Twilio) via driver; implementasi gateway sungguhan.

### Phase 4 — Dashboard analitik & laporan
- [ ] Ringkasan pendapatan, okupansi armada, booking per status.
- [ ] Ekspor laporan (PDF/Excel).

### Phase 5 — Customer dashboard + loyalty
- [ ] Riwayat booking pelanggan, ulangi booking, poin loyalitas.

### Phase 6 — Fitur AI  (paling akhir — butuh fondasi tenant & data)
Target: Owner tanya "Pendapatan bulan ini berapa?" → AI menjawab dari data.

- **Pola aman = function calling, BUKAN generate SQL bebas.** Definisikan sekumpulan
  "tool" query yang sudah discope tenant, mis:
  - `monthly_revenue(month)` → `SUM(total_price) WHERE status IN (confirmed,completed)`
  - `fleet_utilization(range)`, `top_cars(range)`, `pending_bookings()`.
- AI (Claude, function calling) memilih tool + parameter; kode kita yang mengeksekusi
  query dengan `tenant_id` aktif. AI tidak pernah menyentuh SQL mentah → tidak ada
  risiko prompt-injection `DELETE` atau baca data tenant lain.
- Tambahan: rekomendasi harga dinamis, chatbot customer.

---

## Lintas-fase (SaaS plumbing)
- Billing langganan (plan Free/Pro/Enterprise, batas armada per plan) — sebelum jual.
- Custom domain / subdomain per tenant.
- Multi-admin per tenant (sudah tercakup Phase 0).
- Audit log per tenant.

## Catatan kebersihan repo
- Ada folder bersarang `Travel/Travel/` — pertimbangkan merapikan.
- Belum ada git — inisialisasi disarankan sebelum refactor besar.
