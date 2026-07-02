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

### Phase 0 — Tenancy + Role Management  ← MULAI DI SINI
- [ ] Tabel `tenants` (nama rental, slug/subdomain, status langganan, plan).
- [ ] Kolom `tenant_id` + FK di `cars`, `bookings`, `contact_messages`,
      `testimonials`, `users`.
- [ ] Trait `BelongsToTenant` + `TenantScope` (global scope) untuk semua model bisnis.
- [ ] Resolusi tenant aktif: via subdomain (`rentalA.app.com`) atau kolom pada user.
- [ ] Kolom `role` pada `users` (owner/admin/driver/customer) + policy/gate.
- [ ] Middleware `role:owner` dsb. menggantikan `admin` yang sekarang.
- [ ] Registrasi tenant baru (signup Owner → buat tenant → seed data contoh).
- [ ] Seeder: pisahkan data demo Lajur menjadi tenant #1.
- [ ] **Test isolasi**: user tenant A tidak boleh melihat/ubah data tenant B.

### Phase 1 — Booking & kalender ketersediaan
- [ ] Kalender ketersediaan armada (cegah double-booking rentang tanggal).
- [ ] Status booking granular + histori perubahan.
- [ ] QR Code konfirmasi booking.

### Phase 2 — Manajemen armada & driver
- [ ] Penugasan driver ke booking.
- [ ] Dashboard driver (jadwal tugas).
- [ ] Pengingat servis & pajak kendaraan (jadwal + notifikasi).

### Phase 3 — Pembayaran online + invoice  (payment gateway = DITUNDA)
- [ ] Abstraksi `PaymentGateway` (adapter: Midtrans / Xendit / Tripay) — buat
      interface dulu, implementasi belakangan.
- [ ] Invoice PDF + kirim via Email.
- [ ] Notifikasi WhatsApp API (booking dibuat, pembayaran, pengingat).

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
