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
}
