<?php

namespace App\Tenancy;

/**
 * Menentukan apakah sebuah host adalah domain PUSAT (page induk / marketing
 * Lajur) atau SUBDOMAIN tenant (etalase rental milik satu tenant).
 *
 * Pusat  → lajur.id, www.lajur.id, app.lajur.id, localhost, IP  → page induk.
 * Tenant → rentalku.lajur.id                                    → etalase tenant.
 *
 * Sengaja terpisah dari IdentifyTenant (yang me-resolve TENANT dari user →
 * subdomain → default): keputusan routing "/" harus murni dari HOST, tak boleh
 * ikut ke-mana tenant di-resolve (mis. user login me-resolve tenant lebih dulu).
 */
class Domain
{
    /** Label subdomain yang bukan tenant. */
    private const RESERVED = ['www', 'app', 'admin'];

    public static function isCentral(string $host): bool
    {
        // IP (mis. 127.0.0.1 saat dev lokal) = domain pusat.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        $parts = explode('.', $host);

        // Browser me-resolve *.localhost ke 127.0.0.1, jadi saat dev lokal
        // "slug.localhost" (2 label) sudah dianggap subdomain tenant.
        $min = str_ends_with($host, '.localhost') ? 2 : 3;

        // Kurang dari sub.domain.tld (mis. lajur.id, localhost) = pusat.
        if (count($parts) < $min) {
            return true;
        }

        return in_array($parts[0], self::RESERVED, true);
    }
}
