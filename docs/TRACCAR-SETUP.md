# Panduan Install Traccar (Gateway GPS untuk Lajur)

> Traccar adalah **server pelacakan GPS open-source & gratis** yang menerima data dari
> ratusan jenis tracker (Concox/GT06, Teltonika, dll.) dan menyediakan **REST API**.
> Dalam arsitektur Lajur, Traccar berperan sebagai **gateway**:
>
> ```
> Tracker GPS (di mobil) → Traccar (server ini) → Lajur (tarik via API) → Google Maps di /admin/tracking
> ```
>
> Aplikasi Lajur **tidak** menyentuh device langsung; ia hanya membaca posisi & histori
> dari API Traccar. Dokumen ini menyiapkan Traccar-nya. Integrasi ke Lajur dilakukan nanti.

---

## 0. Penting dulu: lokal vs produksi

| | Lokal (Windows/Laragon) | Produksi (VPS ber-IP publik) |
|---|---|---|
| Untuk | Uji coba, kenal UI & API | Tracker fisik sungguhan |
| Bisa terima tracker fisik? | ❌ (tracker di internet tak bisa konek ke `localhost`) | ✅ |
| Database | H2 bawaan (cukup) | MySQL/PostgreSQL |

**Kesimpulan:** tracker fisik **wajib** Traccar di server dengan **IP/port publik** (VPS).
Untuk belajar & uji API, boleh mulai di Windows lokal dulu.

---

## 1. Persyaratan
- **RAM** ≥ 1 GB (2 GB lebih nyaman), disk ≥ 5 GB.
- **Java tidak perlu diinstal terpisah** — installer resmi & image Docker sudah membundel JRE.
- Untuk produksi: VPS (mis. 1 vCPU / 2 GB) dengan IP publik, atau layanan cloud apa pun.
- Halaman unduhan resmi (selalu ambil versi terbaru): **https://www.traccar.org/download/**

---

## 2A. Install di Windows (uji coba lokal)
1. Unduh **`traccar-windows-64-*.zip`** dari halaman download.
2. Ekstrak, jalankan **`traccar.exe`** (installer) → Traccar terpasang sebagai **Windows Service** (auto-start).
3. Buka **http://localhost:8082** → UI Traccar.
4. Lanjut ke **Bagian 4 (akun admin)**.
- Start/stop service: `services.msc` → cari **Traccar**. Log ada di folder `logs/`.

---

## 2B. Install di Linux VPS (produksi) — installer resmi
```bash
# 1. Unduh (ganti nomor versi dengan yang terbaru dari halaman download)
wget https://www.traccar.org/download/traccar-linux-64-6.x.zip
unzip traccar-linux-64-6.x.zip

# 2. Jalankan installer (butuh root)
sudo ./traccar.run

# 3. Kelola service (systemd)
sudo systemctl start traccar
sudo systemctl enable traccar      # auto-start saat boot
sudo systemctl status traccar
```
- Konfigurasi: `/opt/traccar/conf/traccar.xml` · Log: `/opt/traccar/logs/tracker-server.log`
- UI: `http://IP-VPS:8082`

---

## 2C. Install via Docker (rekomendasi produksi, lintas OS)
```bash
# Siapkan file konfigurasi dulu (lihat Bagian 3)
mkdir -p /opt/traccar/{logs,conf}
# taruh traccar.xml di /opt/traccar/conf/traccar.xml

docker run -d --name traccar --restart unless-stopped \
  -p 8082:8082 \
  -p 5000-5150:5000-5150 \
  -p 5000-5150:5000-5150/udp \
  -v /opt/traccar/logs:/opt/traccar/logs \
  -v /opt/traccar/conf/traccar.xml:/opt/traccar/conf/traccar.xml:ro \
  traccar/traccar:latest
```
- Port `8082` = web + API. Port `5000–5150` = port protokol tracker (buka rentang penuh agar semua device tercover).

---

## 3. Konfigurasi (`conf/traccar.xml`)
Untuk **uji coba**: biarkan default (pakai database H2 bawaan). Untuk **produksi**, ganti ke
MySQL/PostgreSQL agar data aman & cepat. Contoh MySQL:

```xml
<?xml version='1.0' encoding='UTF-8'?>
<!DOCTYPE properties SYSTEM 'http://java.sun.com/dtd/properties.dtd'>
<properties>
    <entry key='config.default'>./conf/default.xml</entry>

    <entry key='database.driver'>com.mysql.cj.jdbc.Driver</entry>
    <entry key='database.url'>jdbc:mysql://localhost:3306/traccar?serverTimezone=UTC&amp;useSSL=false&amp;allowPublicKeyRetrieval=true</entry>
    <entry key='database.user'>traccar</entry>
    <entry key='database.password'>PASSWORD_KUAT</entry>
</properties>
```
- Buat database dulu: `CREATE DATABASE traccar;` (Traccar auto-membuat tabelnya saat start).
- **Matikan registrasi publik** setelah akun admin dibuat (biar orang lain tak bisa daftar):
  `<entry key='web.registration'>false</entry>`

Restart Traccar setiap kali `traccar.xml` diubah.

---

## 4. Buat akun admin
1. Buka **http://SERVER:8082**.
2. Klik **Register** → buat akun (email + password).
3. **Jadikan admin.** Akun pertama tidak otomatis admin. Cara paling andal — set flag lewat database:
   ```sql
   -- MySQL/H2: tabel pengguna
   UPDATE tc_users SET administrator = true WHERE email = 'emailkamu@contoh.com';
   ```
   Lalu logout–login lagi. (Alternatif per versi ada di dokumentasi resmi.)
4. Setelah admin siap, set `web.registration=false` (Bagian 3) lalu restart.

> **Ganti password default & pakai password kuat.** Ini server yang memegang lokasi armadamu.

---

## 5. Buka port (firewall)
Agar tracker & aplikasi bisa terhubung, izinkan:
- **8082/tcp** — web UI + REST API (untuk kamu & aplikasi Lajur).
- **5000–5150 tcp & udp** — port protokol tracker (device menyambung ke sini).

Contoh (ufw / Ubuntu):
```bash
sudo ufw allow 8082/tcp
sudo ufw allow 5000:5150/tcp
sudo ufw allow 5000:5150/udp
```
Kalau di VPS, pastikan juga **Security Group / firewall cloud** membuka port yang sama.

---

## 6. Tambah device & arahkan tracker
1. Di UI Traccar: **Settings → Devices → +** → isi **Name** dan **Identifier** (= **IMEI** tracker).
2. Konfigurasi tracker agar menunjuk ke server (biasanya via **SMS command** ke SIM tracker; format tergantung merek):
   - **Server**: `IP-PUBLIK-TRACCAR` + **port protokol** device (lihat tabel bawah).
   - **APN** kartu SIM (sesuai operator).
3. Jika benar, device muncul **online** dan marker bergerak di peta Traccar.

**Port protokol umum** (device menyambung ke port sesuai protokolnya):

| Tracker / protokol | Port |
|---|---|
| Concox / GT06 (paling umum & murah) | **5023** |
| Coban / TK103 (GPS103) | 5001 |
| H02 | 5013 |
| Teltonika | 5027 |
| Meitrack | 5020 |
| Sinotrack | 5093 |

> Tidak yakin protokol device-mu? Buka rentang **5000–5150** dan cek port mana yang aktif saat
> tracker menyambung (lihat log Traccar). Daftar lengkap protokol→port ada di `conf/default.xml`.

---

## 7. Aktifkan API + buat token (untuk integrasi Lajur nanti)
REST API Traccar ada di **`http://SERVER:8082/api`** (port yang sama dengan web).

**Buat token** (dianjurkan untuk server-to-server, agar tak menyimpan password Lajur):
1. UI Traccar → klik **akun (pojok atas) → Account/Settings**.
2. Bagian **Token** → generate → salin.
3. Uji dari terminal:
   ```bash
   # posisi terakhir semua device
   curl "http://SERVER:8082/api/positions" -H "Authorization: Bearer TOKEN"

   # daftar device
   curl "http://SERVER:8082/api/devices" -H "Authorization: Bearer TOKEN"
   ```
Endpoint yang nanti dipakai Lajur:
- `GET /api/devices` — daftar device (untuk memetakan ke mobil).
- `GET /api/positions` — posisi terakhir tiap device (untuk peta **live**).
- `GET /api/reports/route?deviceId=&from=&to=` — **histori rute** (polyline). Tanggal format ISO-8601.

Nilai yang nanti masuk ke `.env` Lajur:
```
TRACCAR_URL=http://SERVER:8082
TRACCAR_TOKEN=isi_token_dari_langkah_di_atas
```

---

## 8. Keamanan (produksi)
- **HTTPS**: taruh Traccar di belakang reverse proxy (Nginx/Caddy) dengan sertifikat (Let's Encrypt),
  sehingga API diakses via `https://track.domainmu`. Web/API di 8082 tetap internal.
- **Ganti semua password default**, pakai token (bukan password) untuk API.
- **Batasi akses 8082** hanya ke server Lajur (firewall / allowlist IP) bila memungkinkan.
- Port protokol 5000–5150 memang harus terbuka ke internet (tracker konek dari mana saja),
  tapi web/API sebaiknya dibatasi.
- Backup database Traccar secara berkala.

---

## 9. Cek berhasil / troubleshooting
- **UI tidak terbuka** → cek service jalan (`systemctl status traccar` / Services), cek port 8082 terbuka.
- **Device tidak online** → pastikan IP:port & APN di tracker benar; cek `logs/tracker-server.log`
  saat tracker mengirim (akan terlihat koneksi dari IP SIM). Pastikan port protokol terbuka di firewall.
- **API 401** → token salah / user bukan admin. Coba `Authorization: Bearer TOKEN`.
- **Waktu/zona salah** → set `serverTimezone=UTC` di URL DB, olah zona di sisi tampilan.

---

## 10. Langkah berikutnya (integrasi ke Lajur)
Setelah Traccar jalan, punya ≥1 device online, dan token API siap, kabari — kita bangun:
1. Pemetaan **device Traccar → mobil → tenant** di DB Lajur (kolom `traccar_device_id`).
2. `TraccarClient` (Laravel Http) yang membaca `positions` & `reports/route`.
3. Halaman **`/admin/tracking`** (Google Maps) + endpoint live/histori (tenant-scoped).
4. Mini-map di detail Mobil.

> Referensi resmi: dokumentasi & API di **https://www.traccar.org** (Documentation & API Reference).
