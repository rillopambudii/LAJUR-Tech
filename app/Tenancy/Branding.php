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

    public function __construct(private ?Tenant $tenant)
    {
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
}
