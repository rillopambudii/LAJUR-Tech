/* ============================================================
   Lajur — landing page interactions (vanilla JS)
   ============================================================ */
(function () {
    'use strict';

    var rupiah = function (n) {
        n = parseInt(n, 10) || 0;
        return 'Rp ' + n.toLocaleString('id-ID');
    };

    /* ---------- Mobile nav ---------- */
    var navToggle = document.querySelector('.nav-toggle');
    var navLinks = document.getElementById('nav-links');
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', function () {
            var open = navLinks.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        navLinks.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                navLinks.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    /* ---------- Image fallback (branded placeholder) ---------- */
    var PLACEHOLDER = '/img/placeholder-car.svg';
    document.querySelectorAll('img[data-fallback]').forEach(function (img) {
        img.addEventListener('error', function handle() {
            img.removeEventListener('error', handle);
            img.src = PLACEHOLDER;
        });
    });

    /* ---------- Type filter chips ---------- */
    var chips = document.querySelectorAll('.chip[data-filter]');
    var cards = document.querySelectorAll('.car-grid .car-card[data-type]');
    var emptyMsg = document.getElementById('cars-empty');
    if (chips.length) {
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) { c.classList.remove('is-active'); c.setAttribute('aria-pressed', 'false'); });
                chip.classList.add('is-active');
                chip.setAttribute('aria-pressed', 'true');
                var filter = chip.getAttribute('data-filter');
                var visible = 0;
                cards.forEach(function (card) {
                    var show = filter === 'all' || card.getAttribute('data-type') === filter;
                    card.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                if (emptyMsg) emptyMsg.style.display = visible === 0 ? '' : 'none';
            });
        });
    }

    /* ---------- Booking modal ---------- */
    var modal = document.getElementById('booking-modal');
    if (modal) {
        var form = modal.querySelector('form');
        var carIdInput = modal.querySelector('[name="car_id"]');
        var carName = modal.querySelector('[data-modal-name]');
        var carPriceEl = modal.querySelector('[data-modal-price]');
        var carImg = modal.querySelector('[data-modal-img]');
        var startInput = modal.querySelector('[name="start_date"]');
        var endInput = modal.querySelector('[name="end_date"]');
        var estAmount = modal.querySelector('[data-est-amount]');
        var estDays = modal.querySelector('[data-est-days]');
        var cpriceInput = modal.querySelector('[data-cprice]');
        var pricePerDay = 0;

        var today = new Date().toISOString().split('T')[0];
        if (startInput) startInput.min = today;

        var recalc = function () {
            var s = startInput.value, e = endInput.value;
            if (e) endInput.min = s || today;
            if (!s || !e) { estAmount.textContent = rupiah(0); estDays.textContent = '0 hari'; return; }
            var sd = new Date(s), ed = new Date(e);
            var diff = Math.round((ed - sd) / 86400000);
            if (diff < 1) { estAmount.textContent = rupiah(0); estDays.textContent = '0 hari'; return; }
            estDays.textContent = diff + ' hari × ' + rupiah(pricePerDay);
            estAmount.textContent = rupiah(diff * pricePerDay);
        };
        if (startInput) startInput.addEventListener('change', recalc);
        if (endInput) endInput.addEventListener('change', recalc);

        var openModal = function (data) {
            if (data) {
                carIdInput.value = data.id;
                carName.textContent = data.name;
                pricePerDay = parseInt(data.price, 10) || 0;
                carPriceEl.textContent = rupiah(pricePerDay) + ' / hari';
                carImg.src = data.image || PLACEHOLDER;
                if (cpriceInput) cpriceInput.value = pricePerDay;
            } else {
                carPriceEl.textContent = rupiah(pricePerDay) + ' / hari';
            }
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            recalc();
            var first = form.querySelector('input[name="customer_name"]');
            if (first) setTimeout(function () { first.focus(); }, 50);
        };
        var closeModal = function () {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        };

        document.querySelectorAll('[data-book]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal({
                    id: btn.getAttribute('data-car-id'),
                    name: btn.getAttribute('data-car-name'),
                    price: btn.getAttribute('data-car-price'),
                    image: btn.getAttribute('data-car-image')
                });
            });
        });
        modal.querySelectorAll('[data-close]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target === el) closeModal();
            });
        });
        var closeBtn = modal.querySelector('[data-close-btn]');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
        });

        /* Reopen automatically after a failed server validation (FR-17) */
        if (modal.hasAttribute('data-reopen')) {
            pricePerDay = parseInt(modal.getAttribute('data-price') || '0', 10);
            openModal(null);
        }
    }

    /* ---------- Reveal on scroll (respects reduced motion) ---------- */
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var reveals = document.querySelectorAll('.reveal');
    if (reveals.length && !reduce && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) { entry.target.classList.add('in'); io.unobserve(entry.target); }
            });
        }, { threshold: 0.12 });
        reveals.forEach(function (el) { io.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('in'); });
    }
})();
