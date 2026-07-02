/* ============================================================
   Lajur Admin — dashboard interactions
   ============================================================ */
(function () {
    'use strict';

    /* ---------- Sidebar toggle (mobile) ---------- */
    var sidebar = document.querySelector('.admin-sidebar');
    var menuBtn = document.querySelector('.mobile-menu');
    var backdrop = document.querySelector('.sidebar-backdrop');
    if (menuBtn && sidebar) {
        var toggle = function () {
            sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('show');
        };
        menuBtn.addEventListener('click', toggle);
        if (backdrop) backdrop.addEventListener('click', toggle);
    }

    /* ---------- Animate chart bars on load ---------- */
    document.querySelectorAll('.chart .bar[data-h]').forEach(function (bar) {
        requestAnimationFrame(function () { bar.style.height = bar.getAttribute('data-h') + '%'; });
    });

    /* ---------- Image upload preview + live card preview ---------- */
    var fileInput = document.querySelector('[data-image-input]');
    var preview = document.querySelector('[data-image-preview]');
    if (fileInput && preview) {
        fileInput.addEventListener('change', function () {
            var file = fileInput.files[0];
            if (!file) return;
            preview.src = URL.createObjectURL(file);
            var pv = document.querySelector('[data-preview-img]');
            if (pv) pv.src = preview.src;
        });
    }

    /* ---------- Live preview card (car form) ---------- */
    var bind = function (selector, target, fmt) {
        var el = document.querySelector(selector);
        var out = document.querySelector(target);
        if (!el || !out) return;
        var update = function () {
            var v = el.value;
            out.textContent = fmt ? fmt(v) : (v || out.getAttribute('data-empty') || '—');
        };
        el.addEventListener('input', update);
        el.addEventListener('change', update);
        update();
    };
    var rupiah = function (n) { n = parseInt(String(n).replace(/\D/g, ''), 10) || 0; return 'Rp ' + n.toLocaleString('id-ID'); };
    bind('[name="name"]', '[data-preview-name]');
    bind('[name="brand"]', '[data-preview-brand]');
    bind('[name="price_per_day"]', '[data-preview-price]', rupiah);
    bind('[name="type"]', '[data-preview-type]');
    bind('[name="seats"]', '[data-preview-seats]');
    bind('[name="transmission"]', '[data-preview-trans]');

    /* ---------- Confirm destructive actions ---------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
        });
    });
})();
