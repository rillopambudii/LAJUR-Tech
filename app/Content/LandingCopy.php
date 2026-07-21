<?php

namespace App\Content;

/**
 * Resolusi teks landing page induk dari data tersimpan (JSON singleton
 * LandingContent), fallback ke DEFAULTS bila kosong/tak ada — meniru pola
 * App\Tenancy\Branding. Field kosong/'' dianggap "tak diisi", per-item untuk
 * grup array (edit satu kartu tidak mengosongkan kartu lain).
 */
class LandingCopy
{
    public const DEFAULTS = [
        'hero_eyebrow' => 'Untuk pemilik usaha rental mobil',
        'hero_title_lead' => 'Kelola seluruh operasional armada',
        'hero_title_reveal' => 'dalam satu platform.',
        'hero_subtitle' => 'Pantau kendaraan, pengemudi, booking, BBM, hingga laporan operasional secara real-time dalam satu dashboard.',
        'cta_label' => 'Coba Gratis 14 Hari',
        'spotlight_eyebrow' => 'Fitur unggulan',
        'trust_lead' => 'Dibangun untuk membantu operasional bisnis rental di Indonesia',
        'trust_items' => ['Monitoring real-time', 'Platform cloud', 'Ramah di HP', 'Support Indonesia'],
        'pain_eyebrow' => 'Kenapa Lajur',
        'pain_title' => 'Masih mengelola armada secara manual?',
        'pain_subtitle' => 'Kalau salah satunya terasa akrab, Anda tidak sendirian. Ini keluhan yang paling sering kami dengar dari pemilik rental.',
        'pain_items' => [
            ['title' => 'Sulit tahu posisi kendaraan', 'text' => 'Mobil disewa keluar kota, kabarnya hanya dari telepon sopir.'],
            ['title' => 'Laporan selalu terlambat', 'text' => 'Rekap bulanan baru jadi tanggal 10. Keputusan diambil pakai firasat.'],
            ['title' => 'Sulit mengontrol driver dan BBM', 'text' => 'Sopir isi 50 ribu, lapor 100 ribu. Sebulan hilang jutaan tanpa jejak.'],
            ['title' => 'Booking masih lewat chat', 'text' => 'Pesanan tercecer di WhatsApp. Dua penyewa, mobil sama, hari sama.'],
            ['title' => 'Data operasional tersebar', 'text' => 'Jadwal di buku, keuangan di Excel, kontak di HP. Tidak ada yang utuh.'],
        ],
        'pain_closing' => 'Lajur menyelesaikan semuanya.',
        'before_items' => ['Excel dan buku catatan', 'Booking via chat', 'Telepon satu per satu', 'Nota dan kertas tercecer'],
        'after_brand' => 'Lajur Platform',
        'after_text' => 'Booking, armada, driver, BBM, dan laporan. Satu login, semua terhubung.',
        'features_title' => 'Semua yang Anda butuh untuk kelola rental',
        'features_subtitle' => 'Empat area kerja, satu dashboard.',
        'feature_groups' => [
            ['title' => 'Operasional', 'items' => ['Booking dan kode unik per pesanan', 'Kalender armada anti-tabrakan', 'Penugasan dan jadwal driver', 'Tujuan perjalanan per booking']],
            ['title' => 'Monitoring', 'items' => ['BBM anti-kebocoran, ditandai otomatis', 'Laporan pendapatan dan utilisasi', 'Export PDF / Excel', 'GPS live di peta']],
            ['title' => 'Produktivitas', 'items' => ['Asisten AI: tanya angka bisnis, dijawab dari data', 'Biaya per km dan konsumsi terhitung sendiri', 'Dashboard ringkas untuk keputusan cepat']],
            ['title' => 'Pengalaman Pelanggan', 'items' => ['Etalase booking online milik Anda sendiri', 'Lacak pesanan dengan kode booking', 'Keluarga ikut memantau perjalanan', 'Profil dan rating sopir yang bisa dilihat penyewa']],
        ],
        'spotlight_fuel_title' => 'BBM yang bocor, langsung ketahuan',
        'spotlight_fuel_text' => 'Sopir isi 60 liter ke tangki yang cuma muat 45? Konsumsi tiba-tiba boros dua kali lipat? Lajur hitung sendiri dari tiap catatan pengisian dan menandainya merah. Anda tak perlu memeriksa satu per satu.',
        'spotlight_driver_title' => 'Sopir tak perlu tanya alamat lagi',
        'spotlight_driver_text' => 'Isi lokasi tujuan sekali saat menugaskan driver, satu tombol Maps langsung muncul di HP sopir. Sekali tap, Google Maps terbuka dengan rute dari posisinya saat itu, tanpa telepon, tanpa dikte alamat lewat WhatsApp.',
        'reviews_eyebrow' => 'Baru',
        'reviews_title' => 'Reputasi sopir jadi alasan orang memilih Anda',
        'reviews_text' => 'Tiap sopir punya halaman profil sendiri: foto, jumlah perjalanan, dan rating dari penyewa sungguhan. Selesai sewa, penyewa menilai ketepatan waktu, kebersihan mobil, keramahan, dan keamanan berkendara.',
        'reviews_items' => [
            'Penyewa tahu siapa yang akan menjemput, lengkap dengan rekam jejaknya. Rasa aman inilah yang sering menentukan pilihan.',
            'Ulasan masuk ke dashboard Anda dulu, tidak langsung tayang. Anda yang memutuskan menayangkan, dan bisa membalas.',
            'Sopir tahu dirinya dinilai, jadi pelayanan terjaga sendiri tanpa perlu Anda awasi terus-menerus.',
        ],
        'reviews_caption' => 'Halaman profil sopir yang dilihat penyewa',
        'family_title' => 'Keluarga juga bisa memantau perjalanan',
        'family_subtitle' => 'Setiap booking punya kode unik. Penyewa cukup membagikannya, dan keluarga di rumah ikut tenang.',
        'family_steps' => [
            ['title' => 'Booking dibuat', 'text' => 'Pesanan masuk, kode booking terbit otomatis.'],
            ['title' => 'Kode dibagikan', 'text' => 'Penyewa mengirim kode ke keluarganya.'],
            ['title' => 'Masukkan kode', 'text' => 'Buka halaman Lacak Pesanan, tanpa perlu akun.'],
            ['title' => 'Perjalanan terpantau', 'text' => 'Status dan detail perjalanan terlihat. Posisi live menyusul bersama GPS.'],
        ],
        'family_chat_caption' => 'Ilustrasi: penyewa membagikan kode ke keluarga',
        'family_track_caption' => 'Ilustrasi: halaman lacak yang dibuka keluarga',
        'spotlight_storefront_title' => 'Situs booking sendiri, bukan skin generik',
        'spotlight_storefront_text' => 'Setiap tenant dapat etalase online sendiri: logo, warna aksen, dan alamat domain sendiri (namatenant.lajur.id). Pelanggan booking langsung dari situs itu, bukan chat WhatsApp yang mudah terlewat.',
        'gps_badge' => 'Segera hadir',
        'gps_title' => 'Mobil Anda, terlihat di peta',
        'gps_text' => 'Begitu alat GPS terpasang di unit, posisi tiap mobil tampil langsung di dashboard. Tak perlu lagi menelepon sopir untuk tanya "sudah sampai mana".',
        'gps_note' => 'Tampilan di samping adalah gambaran fitur yang sedang dalam pengembangan.',
        'why_title' => 'Kenapa memilih Lajur?',
        'why_items' => [
            ['title' => 'Setup cepat', 'text' => 'Akun jadi dalam hitungan menit, tanpa instalasi.'],
            ['title' => 'Berbasis cloud', 'text' => 'Buka dari mana saja, data tersimpan aman di server.'],
            ['title' => 'Ramah di HP', 'text' => 'Dashboard, driver, dan pelanggan nyaman diakses dari ponsel.'],
            ['title' => 'Aman', 'text' => 'Data bisnis terenkripsi dan terisolasi per tenant.'],
            ['title' => 'Real-time', 'text' => 'Booking masuk dan laporan terhitung saat itu juga.'],
            ['title' => 'Support Indonesia', 'text' => 'Tim lokal, respons cepat lewat WhatsApp.'],
        ],
        'workflow_title' => 'Dari daftar sampai go live, tidak ribet',
        'workflow_steps' => [
            ['title' => 'Daftar', 'text' => 'Buat akun dalam semenit, tanpa kartu kredit.'],
            ['title' => 'Coba dan demo', 'text' => 'Jelajahi dashboard dengan akses penuh 14 hari.'],
            ['title' => 'Setup data', 'text' => 'Masukkan mobil, tarif, dan driver. Kami bantu tiap langkah.'],
            ['title' => 'Training singkat', 'text' => 'Tim Anda dipandu lewat WhatsApp sampai lancar.'],
            ['title' => 'Go live', 'text' => 'Terima booking dan pantau operasional dari satu layar.'],
        ],
        'ecosystem_title_line1' => 'Bukan sekadar aplikasi rental.',
        'ecosystem_title_line2' => 'Ini platform operasional Anda.',
        'ecosystem_subtitle' => 'Seluruh data operasional terhubung dalam satu platform, dan terus bertumbuh bersama bisnis Anda.',
        'ecosystem_items' => ['Armada', 'Driver', 'Booking', 'Etalase pelanggan', 'BBM anti-kebocoran', 'Laporan dan export', 'Asisten AI', 'GPS live', 'Integrasi IoT'],
        'ecosystem_caption' => 'Juga dalam pengembangan: prediksi perawatan armada dan notifikasi pintar.',
        'pricing_title' => 'Harga jujur, tanpa kejutan',
        'pricing_subtitle' => 'Semua paket bisa dicoba gratis 14 hari dulu. Bayar bulanan, berhenti kapan saja.',
        'cta_title' => 'Siap mengelola armada lebih efisien?',
        'cta_text' => 'Coba semua fitur Lajur gratis 14 hari. Tanpa kartu kredit, tanpa risiko.',
        'cta_trust_items' => ['Data bisnis aman dan terenkripsi', 'Dukungan cepat via WhatsApp', 'Upgrade atau turun paket kapan saja'],
    ];

    public function __construct(private array $data)
    {
    }

    private function get(string $key): string
    {
        $val = $this->data[$key] ?? null;

        return ($val !== null && $val !== '') ? $val : self::DEFAULTS[$key];
    }

    /** Daftar string sederhana (mis. trust_items) — override per-index. */
    private function getList(string $key): array
    {
        $stored = $this->data[$key] ?? [];
        $result = [];
        foreach (self::DEFAULTS[$key] as $i => $default) {
            $val = $stored[$i] ?? null;
            $result[$i] = ($val !== null && $val !== '') ? $val : $default;
        }

        return $result;
    }

    /** Daftar grup ['title'=>..,'text'=>..] (mis. pain_items) — override per-item, per-field. */
    private function getGroups(string $key): array
    {
        $stored = $this->data[$key] ?? [];
        $result = [];
        foreach (self::DEFAULTS[$key] as $i => $defaultRow) {
            $storedRow = $stored[$i] ?? [];
            $row = [];
            foreach ($defaultRow as $field => $defaultVal) {
                $val = $storedRow[$field] ?? null;
                $row[$field] = ($val !== null && $val !== '') ? $val : $defaultVal;
            }
            $result[$i] = $row;
        }

        return $result;
    }

    public function heroEyebrow(): string { return $this->get('hero_eyebrow'); }
    public function heroTitleLead(): string { return $this->get('hero_title_lead'); }
    public function heroTitleReveal(): string { return $this->get('hero_title_reveal'); }
    public function heroSubtitle(): string { return $this->get('hero_subtitle'); }

    public function ctaLabel(): string { return $this->get('cta_label'); }
    public function spotlightEyebrow(): string { return $this->get('spotlight_eyebrow'); }

    public function trustLead(): string { return $this->get('trust_lead'); }
    public function trustItems(): array { return $this->getList('trust_items'); }

    public function painEyebrow(): string { return $this->get('pain_eyebrow'); }
    public function painTitle(): string { return $this->get('pain_title'); }
    public function painSubtitle(): string { return $this->get('pain_subtitle'); }
    public function painItems(): array { return $this->getGroups('pain_items'); }
    public function painClosing(): string { return $this->get('pain_closing'); }

    public function beforeItems(): array { return $this->getList('before_items'); }
    public function afterBrand(): string { return $this->get('after_brand'); }
    public function afterText(): string { return $this->get('after_text'); }

    public function featuresTitle(): string { return $this->get('features_title'); }
    public function featuresSubtitle(): string { return $this->get('features_subtitle'); }

    /** @return array<int, array{title:string, items:array<int,string>}> */
    public function featureGroups(): array
    {
        $stored = $this->data['feature_groups'] ?? [];
        $result = [];
        foreach (self::DEFAULTS['feature_groups'] as $gi => $defaultGroup) {
            $storedGroup = $stored[$gi] ?? [];
            $title = $storedGroup['title'] ?? null;
            $items = [];
            foreach ($defaultGroup['items'] as $ii => $defaultItem) {
                $val = $storedGroup['items'][$ii] ?? null;
                $items[$ii] = ($val !== null && $val !== '') ? $val : $defaultItem;
            }
            $result[$gi] = [
                'title' => ($title !== null && $title !== '') ? $title : $defaultGroup['title'],
                'items' => $items,
            ];
        }

        return $result;
    }

    public function spotlightFuelTitle(): string { return $this->get('spotlight_fuel_title'); }
    public function spotlightFuelText(): string { return $this->get('spotlight_fuel_text'); }
    public function spotlightDriverTitle(): string { return $this->get('spotlight_driver_title'); }
    public function spotlightDriverText(): string { return $this->get('spotlight_driver_text'); }

    public function reviewsEyebrow(): string { return $this->get('reviews_eyebrow'); }
    public function reviewsTitle(): string { return $this->get('reviews_title'); }
    public function reviewsText(): string { return $this->get('reviews_text'); }
    public function reviewsItems(): array { return $this->getList('reviews_items'); }
    public function reviewsCaption(): string { return $this->get('reviews_caption'); }

    public function familyTitle(): string { return $this->get('family_title'); }
    public function familySubtitle(): string { return $this->get('family_subtitle'); }
    public function familySteps(): array { return $this->getGroups('family_steps'); }
    public function familyChatCaption(): string { return $this->get('family_chat_caption'); }
    public function familyTrackCaption(): string { return $this->get('family_track_caption'); }

    public function spotlightStorefrontTitle(): string { return $this->get('spotlight_storefront_title'); }
    public function spotlightStorefrontText(): string { return $this->get('spotlight_storefront_text'); }

    public function gpsBadge(): string { return $this->get('gps_badge'); }
    public function gpsTitle(): string { return $this->get('gps_title'); }
    public function gpsText(): string { return $this->get('gps_text'); }
    public function gpsNote(): string { return $this->get('gps_note'); }

    public function whyTitle(): string { return $this->get('why_title'); }
    public function whyItems(): array { return $this->getGroups('why_items'); }

    public function workflowTitle(): string { return $this->get('workflow_title'); }
    public function workflowSteps(): array { return $this->getGroups('workflow_steps'); }

    public function ecosystemTitleLine1(): string { return $this->get('ecosystem_title_line1'); }
    public function ecosystemTitleLine2(): string { return $this->get('ecosystem_title_line2'); }
    public function ecosystemSubtitle(): string { return $this->get('ecosystem_subtitle'); }
    public function ecosystemItems(): array { return $this->getList('ecosystem_items'); }
    public function ecosystemCaption(): string { return $this->get('ecosystem_caption'); }

    public function pricingTitle(): string { return $this->get('pricing_title'); }
    public function pricingSubtitle(): string { return $this->get('pricing_subtitle'); }

    public function ctaTitle(): string { return $this->get('cta_title'); }
    public function ctaText(): string { return $this->get('cta_text'); }
    public function ctaTrustItems(): array { return $this->getList('cta_trust_items'); }
}
