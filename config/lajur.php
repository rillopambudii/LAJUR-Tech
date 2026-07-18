<?php

/**
 * Identitas & kontak platform Lajur (page induk / situs marketing SaaS).
 * Terpisah dari config/legal.php yang khusus identitas hukum.
 */
return [
    // Nomor WhatsApp untuk tombol kontak/CTA. Format internasional tanpa "+"
    // (untuk wa.me). Asli 0838-9369-7318 → 62 + 83893697318.
    'whatsapp' => env('LAJUR_WHATSAPP', '6283893697318'),

    'email' => env('LAJUR_EMAIL', 'halo@lajur.id'),

    'tagline' => 'Software manajemen rental mobil untuk pemilik usaha.',
];
