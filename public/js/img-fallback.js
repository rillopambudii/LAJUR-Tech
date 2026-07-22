/**
 * Placeholder untuk gambar yang gagal dimuat, dipakai semua layout.
 *
 * Sengaja berkas terpisah (bukan di app.js): penanda `data-fallback` dipakai di
 * view publik MAUPUN admin, sedangkan tiap layout memuat bundel JS berbeda.
 * Saat logikanya hanya ada di app.js, gambar rusak di /admin/cars tampil sebagai
 * ikon rusak bawaan browser — ditemukan lewat soak 2026-07-22.
 */
(function () {
    'use strict';

    var PLACEHOLDER = '/img/placeholder-car.svg';

    function apply(img) {
        if (img.src.indexOf(PLACEHOLDER) !== -1) return; // jangan berputar
        img.src = PLACEHOLDER;
    }

    document.querySelectorAll('img[data-fallback]').forEach(function (img) {
        // Gambar yang SUDAH gagal sebelum skrip ini jalan tak akan memicu 'error'
        // lagi, jadi periksa keadaannya langsung — bukan hanya memasang listener.
        if (img.complete && img.naturalWidth === 0) {
            apply(img);
            return;
        }

        img.addEventListener('error', function handle() {
            img.removeEventListener('error', handle);
            apply(img);
        });
    });
})();
