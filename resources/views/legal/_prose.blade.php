{{-- Gaya prosa dokumen hukum. Dipakai bersama oleh /syarat & /privasi supaya
     keduanya tidak pernah berbeda tampilan. Sengaja tidak menyentuh app.css:
     ini satu-satunya tempat di situs yang butuh teks panjang mengalir. --}}
@push('head')
<style>
    .legal { max-width: 780px; margin-inline: auto; }
    .legal-meta {
        font-size: .92rem; color: var(--graphite);
        border-left: 3px solid var(--ivory-200); padding-left: 14px; margin-bottom: 34px;
    }
    .legal h2 {
        font-size: 1.28rem; margin-top: 40px; margin-bottom: 12px;
        scroll-margin-top: 90px;
    }
    .legal h3 { font-size: 1.02rem; margin-top: 24px; margin-bottom: 8px; }
    .legal p, .legal li { line-height: 1.75; color: var(--graphite); }
    .legal p { margin-bottom: 14px; }
    .legal ul { margin: 0 0 14px 20px; }
    .legal li { margin-bottom: 8px; }
    .legal strong { color: var(--ink); }
    .legal a { color: var(--petrol); text-decoration: underline; }
    .legal .toc {
        background: var(--ivory); border: 1px solid var(--ivory-200);
        border-radius: 12px; padding: 18px 22px; margin-bottom: 40px;
    }
    .legal .toc ol { margin: 0 0 0 18px; }
    .legal .toc li { margin-bottom: 4px; }
    .legal .callout {
        background: var(--ivory); border: 1px solid var(--ivory-200);
        border-left: 3px solid var(--amber); border-radius: 10px;
        padding: 16px 20px; margin: 20px 0;
    }
    .legal .callout p:last-child { margin-bottom: 0; }
    .legal table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: .94rem; }
    .legal th, .legal td {
        text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--ivory-200);
        vertical-align: top; color: var(--graphite);
    }
    .legal th { color: var(--ink); font-weight: 700; }
    /* Tabel bisa lebih lebar dari layar HP — biar dia yang menggeser, bukan halamannya. */
    .legal .table-scroll { overflow-x: auto; }
</style>
@endpush
