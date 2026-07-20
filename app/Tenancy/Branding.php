<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves the active tenant's storefront branding, falling back to the
 * Lajur defaults that were previously hardcoded in the public views. Only
 * layouts.public + home receive this (via the view composer in
 * AppServiceProvider); dashboards stay Lajur-branded.
 */
class Branding
{
    /** key => [display font, body font], both as full CSS font-family values. */
    private const FONT_STYLES = [
        'klasik' => ["'Sora', system-ui, sans-serif", "'Plus Jakarta Sans', system-ui, sans-serif"],
        'netral' => ["'Inter', system-ui, sans-serif", "'Inter', system-ui, sans-serif"],
        'ramah' => ["'Poppins', system-ui, sans-serif", "'Plus Jakarta Sans', system-ui, sans-serif"],
        'elegan' => ["'Playfair Display', serif", "'Plus Jakarta Sans', system-ui, sans-serif"],
        'korporat' => ["'Space Grotesk', system-ui, sans-serif", "'Inter', system-ui, sans-serif"],
    ];

    /** key => [radius-sm, radius, radius-lg, radius-pill, section padding-block], all in px. */
    private const UI_STYLES = [
        'klasik' => [8, 14, 22, 999, 92],
        'tegas' => [4, 8, 12, 8, 72],
        'lembut' => [10, 18, 28, 999, 120],
        'minimalis' => [2, 4, 8, 4, 92],
        'playful' => [12, 20, 30, 999, 92],
    ];

    public static function fontStyleKeys(): array
    {
        return array_keys(self::FONT_STYLES);
    }

    public static function uiStyleKeys(): array
    {
        return array_keys(self::UI_STYLES);
    }

    public function __construct(private ?Tenant $tenant)
    {
    }

    /**
     * URL etalase publik tenant: subdomain slug di atas host APP_URL
     * (mis. ucupadhy.lajur.id, atau ucupadhy.localhost:8000 saat dev).
     * Tenant default/tanpa tenant → "/" domain pusat.
     */
    public function siteUrl(): string
    {
        if (! $this->tenant || $this->tenant->slug === 'lajur') {
            return route('home');
        }

        $base = parse_url(config('app.url'));

        return ($base['scheme'] ?? 'http').'://'
            .$this->tenant->slug.'.'.($base['host'] ?? 'localhost')
            .(isset($base['port']) ? ':'.$base['port'] : '');
    }

    public function name(): string
    {
        if ($this->tenant?->display_name) {
            return $this->tenant->display_name;
        }

        // Tenant default platform tetap tampil sebagai "Lajur" — kolom name
        // legacy-nya berisi string panjang yang bukan nama brand navbar.
        if (! $this->tenant || $this->tenant->slug === 'lajur') {
            return 'Lajur';
        }

        return $this->tenant->name;
    }

    public function tagline(): string
    {
        // `?:` (bukan `??`) agar string kosong pun jatuh ke default, tanpa
        // bergantung pada middleware ConvertEmptyStringsToNull.
        return $this->tenant?->tagline ?: 'Rental Mobil Premium · Kalimantan Timur';
    }

    public function phone(): string
    {
        return $this->tenant?->contact_phone ?: '+62 812-0000-0000';
    }

    public function address(): string
    {
        return $this->tenant?->contact_address ?: 'Samarinda, Kalimantan Timur';
    }

    public function email(): string
    {
        return $this->tenant?->contact_email ?: 'halo@lajur.id';
    }

    public function logoUrl(): ?string
    {
        return $this->tenant?->logo_path
            ? Storage::disk('public')->url($this->tenant->logo_path)
            : null;
    }

    public function accentColor(): ?string
    {
        return $this->tenant?->accent_color;
    }

    /** Accent darkened ~15% for hover states (replaces --amber-600). */
    public function accentDark(): ?string
    {
        $hex = $this->accentColor();
        if (! $hex) {
            return null;
        }

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02X%02X%02X', (int) ($r * .85), (int) ($g * .85), (int) ($b * .85));
    }

    /** Accent at 30% alpha for glow shadows (replaces --amber-glow). */
    public function accentGlow(): ?string
    {
        $hex = $this->accentColor();
        if (! $hex) {
            return null;
        }

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('rgba(%d, %d, %d, 0.30)', $r, $g, $b);
    }

    private function fontStyleKey(): string
    {
        $key = $this->tenant?->font_style;

        return array_key_exists($key, self::FONT_STYLES) ? $key : 'klasik';
    }

    private function uiStyleKey(): string
    {
        $key = $this->tenant?->ui_style;

        return array_key_exists($key, self::UI_STYLES) ? $key : 'klasik';
    }

    public function fontDisplay(): string
    {
        return self::FONT_STYLES[$this->fontStyleKey()][0];
    }

    public function fontBody(): string
    {
        return self::FONT_STYLES[$this->fontStyleKey()][1];
    }

    public function radiusSm(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][0] . 'px';
    }

    public function radius(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][1] . 'px';
    }

    public function radiusLg(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][2] . 'px';
    }

    public function radiusPill(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][3] . 'px';
    }

    public function sectionSpacing(): string
    {
        return self::UI_STYLES[$this->uiStyleKey()][4] . 'px';
    }

    /** Whether font/UI-style personalization differs from Klasik (both default) — gates whether the override <style> block needs to emit anything for these properties. */
    public function hasPersonalization(): bool
    {
        return $this->fontStyleKey() !== 'klasik' || $this->uiStyleKey() !== 'klasik';
    }

    /* ---------------- Hero (halaman depan) ---------------- */

    public function heroImageUrl(): string
    {
        return $this->tenant?->hero_image_path
            ? Storage::disk('public')->url($this->tenant->hero_image_path)
            : asset('img/hero-drive.jpg');
    }

    public function heroTitle(): string
    {
        return $this->tenant?->hero_title ?: 'Perjalanan Anda, dalam kendali penuh.';
    }

    public function heroSubtitle(): string
    {
        return $this->tenant?->hero_subtitle
            ?: 'Sewa mobil premium yang terawat dengan harga transparan dan proses yang cepat. Dari dinas hingga liburan keluarga, '.$this->name().' siap mengantar.';
    }

    /* ---------------- Tentang & keunggulan ---------------- */

    public function aboutTitle(): string
    {
        return $this->tenant?->about_title ?: 'Mitra perjalanan Anda';
    }

    public function aboutText(): string
    {
        return $this->tenant?->about_text
            ?: $this->name().' hadir untuk kebutuhan akan layanan rental mobil yang rapi, jujur, dan bisa diandalkan. Kami percaya menyewa mobil seharusnya sesuai harapan: mudah, aman, dan tanpa drama.';
    }

    /** Default: 4 keunggulan bawaan. @return list<array{icon:string,title:string,text:string}> */
    public function whyUs(): array
    {
        $default = [
            ['icon' => 'shield', 'title' => 'Armada Terawat', 'text' => 'Setiap mobil diservis berkala dan dicek menyeluruh sebelum disewakan.'],
            ['icon' => 'tag', 'title' => 'Harga Transparan', 'text' => 'Tarif jelas per hari, tanpa biaya tersembunyi. Estimasi total dihitung di muka.'],
            ['icon' => 'clock', 'title' => 'Proses Cepat', 'text' => 'Ajukan sewa dalam hitungan menit. Tim responsif siap membantu kapan saja.'],
            ['icon' => 'sparkle', 'title' => 'Layanan Premium', 'text' => 'Pengalaman menyewa yang rapi dan profesional dari awal hingga akhir.'],
        ];

        $custom = $this->tenant?->why_us;
        if (! is_array($custom) || $custom === []) {
            return $default;
        }

        // Tenant hanya mengatur judul + teks; ikon default berputar agar tetap rapi.
        $icons = ['shield', 'tag', 'clock', 'sparkle'];

        return array_values(array_filter(array_map(function ($item, $i) use ($icons) {
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                return null;
            }

            return ['icon' => $icons[$i % 4], 'title' => $title, 'text' => (string) ($item['text'] ?? '')];
        }, $custom, array_keys($custom))));
    }

    /* ---------------- Kontak & sosial ---------------- */

    /** Nomor WA dinormalkan ke format internasional tanpa "+"; null bila kosong. */
    public function whatsapp(): ?string
    {
        return self::waNumber($this->tenant?->whatsapp);
    }

    /** Normalkan nomor telepon apa pun ke format 62 tanpa "+"; null bila kosong. */
    public static function waNumber(?string $phone): ?string
    {
        $raw = preg_replace('/\D+/', '', (string) $phone);
        if ($raw === '' || $raw === null) {
            return null;
        }
        if (str_starts_with($raw, '0')) {
            return '62'.substr($raw, 1);
        }
        if (str_starts_with($raw, '62')) {
            return $raw;
        }

        return '62'.$raw;
    }

    public function whatsappUrl(): ?string
    {
        return $this->whatsapp() ? 'https://wa.me/'.$this->whatsapp() : null;
    }

    /** Terima URL penuh atau username; kembalikan URL profil lengkap. */
    public function instagram(): ?string
    {
        return $this->social($this->tenant?->instagram, 'https://instagram.com/');
    }

    public function facebook(): ?string
    {
        return $this->social($this->tenant?->facebook, 'https://facebook.com/');
    }

    public function tiktok(): ?string
    {
        return $this->social($this->tenant?->tiktok, 'https://tiktok.com/@');
    }

    private function social(?string $value, string $base): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return $base.ltrim($value, '@/');
    }

    /* ---------------- Tampilan, efek, visibilitas, SEO ---------------- */

    public const SECTION_EFFECTS = ['fade-up', 'fade', 'zoom', 'slide', 'none'];

    public function sectionEffect(): string
    {
        $e = $this->tenant?->section_effect;

        return in_array($e, self::SECTION_EFFECTS, true) ? $e : 'fade-up';
    }

    public function splashEnabled(): bool
    {
        // Default true bila kolom null (tenant lama).
        return $this->tenant?->splash_enabled ?? true;
    }

    public function showAbout(): bool
    {
        return $this->tenant?->show_about ?? true;
    }

    public function showWhy(): bool
    {
        return $this->tenant?->show_why ?? true;
    }

    public function showTestimonials(): bool
    {
        return $this->tenant?->show_testimonials ?? true;
    }

    public function metaTitle(): string
    {
        return $this->tenant?->meta_title ?: $this->name().' — Rental Mobil';
    }

    public function metaDescription(): string
    {
        return $this->tenant?->meta_description
            ?: $this->name().': sewa mobil dengan armada terawat, harga transparan, proses cepat dan aman.';
    }
}
