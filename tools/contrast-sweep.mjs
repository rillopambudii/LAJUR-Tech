/**
 * Sapuan kontras teks — mencari teks/tombol yang tak terbaca karena warnanya
 * terlalu dekat dengan latarnya.
 *
 * Dibuat setelah bug "Pilih Business" (2026-07-22): tombol berteks navy di atas
 * kartu navy — kontras 1:1, mustahil dilihat. Soak keamanan tak menangkapnya
 * (halaman balas 200, nol error JS) dan soak visual pun lolos, karena screenshot
 * pandai menunjukkan "ada yang aneh" tapi buruk menunjukkan "ada yang TIDAK ADA".
 * Rasio kontras tidak butuh penilaian selera — cukup hitungan.
 *
 * CARA PAKAI
 *   1. Jalankan aplikasi:  php artisan serve
 *   2. Jalankan browser headless dengan port debug:
 *      msedge --headless=new --remote-debugging-port=9240 --user-data-dir=<tmp> about:blank
 *   3. node tools/contrast-sweep.mjs [--base=http://127.0.0.1:8000] [--cdp=http://127.0.0.1:9240]
 *
 * Ambang mengikuti WCAG 2.1 AA: 4,5:1 untuk teks biasa, 3:1 untuk teks besar
 * (>=24px, atau >=18.66px bila tebal).
 */
import { writeFileSync } from 'node:fs';

const arg = (name, fallback) =>
  process.argv.find((a) => a.startsWith(`--${name}=`))?.split('=').slice(1).join('=') ?? fallback;

const BASE = arg('base', 'http://127.0.0.1:8000');
const CDP = arg('cdp', 'http://127.0.0.1:9240');

/* ------------------------------------------------------------------ akun uji */
// Password khusus lingkungan pengembangan. Sesuaikan bila berbeda.
const ROLES = {
  tamu: null,
  owner: ['owner@borneotrans.id', 'ujibayar123'],
};

const PAGES = {
  tamu: ['/', '/demo', '/daftar', '/daftar/trial', '/lacak', '/login', '/lupa-password', '/syarat', '/privasi'],
  owner: [
    '/admin', '/admin/langganan', '/admin/langganan/selesai', '/admin/cars', '/admin/cars/create',
    '/admin/drivers', '/admin/drivers/create', '/admin/bookings', '/admin/calendar', '/admin/messages',
    '/admin/reports', '/admin/situs', '/admin/testimonials', '/admin/ulasan-driver', '/admin/profil',
    '/admin/staff', '/admin/staff/create',
  ],
};

/* ------------------------------------------------------- yang dijalankan di halaman */
// Dikirim sebagai string ke browser; tak boleh mengacu variabel Node.
const ANALYZER = `(() => {
  const parse = (s) => {
    const m = String(s).match(/rgba?\\(([^)]+)\\)/);
    if (!m) return null;
    const p = m[1].split(',').map((x) => parseFloat(x));
    return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
  };
  const over = (fg, bg) => ({
    r: fg.r * fg.a + bg.r * (1 - fg.a),
    g: fg.g * fg.a + bg.g * (1 - fg.a),
    b: fg.b * fg.a + bg.b * (1 - fg.a),
    a: 1,
  });
  const lum = (c) => {
    const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
  };
  const ratio = (a, b) => {
    const x = lum(a), y = lum(b);
    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
  };

  // Telusuri ke atas sampai ketemu latar buram. Gradien/gambar tak bisa diukur
  // sebagai satu warna -> ditandai, bukan ditebak (mencegah lapor palsu).
  const backdrop = (el) => {
    const layers = [];
    let node = el;
    while (node && node.nodeType === 1) {
      const cs = getComputedStyle(node);
      if (cs.backgroundImage && cs.backgroundImage !== 'none') return { unknown: true };
      const c = parse(cs.backgroundColor);
      if (c && c.a > 0) { layers.push(c); if (c.a === 1) break; }
      node = node.parentElement;
    }
    let base = { r: 255, g: 255, b: 255, a: 1 };
    for (let i = layers.length - 1; i >= 0; i--) base = over(layers[i], base);
    return base;
  };

  const path = (el) => {
    const bits = [];
    let n = el;
    for (let i = 0; n && n.nodeType === 1 && i < 4; i++) {
      let s = n.tagName.toLowerCase();
      if (n.id) { bits.unshift(s + '#' + n.id); break; }
      const cls = (n.getAttribute('class') || '').trim().split(/\\s+/).filter(Boolean).slice(0, 2);
      if (cls.length) s += '.' + cls.join('.');
      bits.unshift(s);
      n = n.parentElement;
    }
    return bits.join(' > ');
  };

  const out = [];
  let takTerukur = 0;

  for (const el of document.querySelectorAll('body *')) {
    const teks = [...el.childNodes]
      .filter((n) => n.nodeType === 3)
      .map((n) => n.textContent.trim())
      .join(' ')
      .trim();
    if (!teks) continue;

    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0) continue;
    const box = el.getBoundingClientRect();
    if (box.width < 2 || box.height < 2) continue;
    // Elemen yang sengaja diparkir di luar layar (mis. tautan "Lewati ke konten"
    // yang hanya muncul saat difokus) bukan teks yang dibaca pengguna.
    if (box.bottom < 0 || box.right < 0 || box.left > (window.innerWidth + 2000)) continue;
    if (cs.clipPath && cs.clipPath !== 'none' && cs.position === 'absolute') continue;

    const bg = backdrop(el);
    if (bg.unknown) { takTerukur++; continue; }

    // Teks di dalam SVG diwarnai lewat 'fill', bukan 'color' — tanpa ini label
    // peta dilaporkan salah memakai warna yang tak dipakai untuk menggambarnya.
    // CATATAN: pakai namespace, JANGAN ownerSVGElement — untuk elemen HTML biasa
    // nilainya undefined (bukan null), sehingga "!== null" menganggap SELURUH
    // halaman sebagai SVG dan melaporkan ratusan pelanggaran palsu.
    const svg = el.namespaceURI === 'http://www.w3.org/2000/svg';
    let fg = parse(svg ? (cs.fill || cs.color) : cs.color);
    if (!fg) continue;
    if (fg.a < 1) fg = over(fg, bg);

    // Angka besar tembus pandang (mis. "01" ber-alpha 0.12) memang hiasan latar,
    // bukan teks yang harus dibaca. Dipisahkan agar tak menenggelamkan temuan
    // sungguhan — tetap dilaporkan, tapi ditandai.
    const asli = parse(svg ? (cs.fill || cs.color) : cs.color);
    const dekoratif = asli.a < 0.3;

    const size = parseFloat(cs.fontSize);
    const weight = parseInt(cs.fontWeight, 10) || 400;
    const besar = size >= 24 || (size >= 18.66 && weight >= 700);
    const ambang = besar ? 3 : 4.5;
    const r = ratio(fg, bg);

    if (r < ambang) {
      const dis = el.matches('[disabled], [aria-disabled="true"]') ||
                  !!el.closest('[disabled], [aria-disabled="true"]');
      out.push({
        rasio: Math.round(r * 100) / 100,
        ambang,
        teks: teks.slice(0, 48),
        el: path(el),
        warna: cs.color,
        latar: 'rgb(' + Math.round(bg.r) + ', ' + Math.round(bg.g) + ', ' + Math.round(bg.b) + ')',
        ukuran: Math.round(size) + 'px/' + weight,
        nonaktif: dis,
        dekoratif,
      });
    }
  }

  out.sort((a, b) => a.rasio - b.rasio);
  return JSON.stringify({ pelanggaran: out, takTerukur });
})()`;

/* --------------------------------------------------------------------- CDP */
const ver = await (await fetch(`${CDP}/json/version`)).json();
const ws = new WebSocket(ver.webSocketDebuggerUrl);
await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });

let id = 0;
const pending = new Map();
ws.onmessage = (e) => {
  const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
};
const send = (method, params = {}, sessionId) => new Promise((res, rej) => {
  const mid = ++id;
  pending.set(mid, res);
  ws.send(JSON.stringify({ id: mid, method, params, sessionId }));
  setTimeout(() => { pending.delete(mid); rej(new Error('timeout ' + method)); }, 30000);
});

async function login(email, password) {
  const page = await fetch(`${BASE}/login`);
  const html = await page.text();
  const token = html.match(/name="_token"\s+value="([^"]+)"/)?.[1];
  const jar = {};
  for (const c of page.headers.getSetCookie()) { const [kv] = c.split(';'); const [k, ...v] = kv.split('='); jar[k.trim()] = v.join('='); }
  const cookie = Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; ');
  const res = await fetch(`${BASE}/login`, {
    method: 'POST', redirect: 'manual',
    headers: { 'content-type': 'application/x-www-form-urlencoded', cookie },
    body: new URLSearchParams({ _token: token, email, password }),
  });
  if (res.status !== 302) throw new Error(`login ${email} gagal (${res.status})`);
  for (const c of res.headers.getSetCookie()) { const [kv] = c.split(';'); const [k, ...v] = kv.split('='); jar[k.trim()] = v.join('='); }
  return jar;
}

/* -------------------------------------------------------------------- sweep */
const temuan = [];
let halamanDiperiksa = 0;
let totalTakTerukur = 0;

for (const [peran, kredensial] of Object.entries(ROLES)) {
  let jar = {};
  if (kredensial) {
    try { jar = await login(...kredensial); }
    catch (e) { console.log(`!! lewati peran ${peran}: ${e.message}`); continue; }
  }

  for (const path of PAGES[peran] ?? []) {
    const { result: { targetId } } = await send('Target.createTarget', { url: 'about:blank' });
    const { result: { sessionId } } = await send('Target.attachToTarget', { targetId, flatten: true });
    const s = (m, p) => send(m, p, sessionId);

    try {
      await s('Network.enable');
      // WAJIB: profil browser dipakai bersama antar-tab, jadi cookie sesi dari
      // pemeriksaan sebelumnya ikut terbawa. Tanpa ini halaman "tamu" diakses
      // dalam keadaan MASIH LOGIN — sempat membuat /demo dirender memakai
      // branding tenant lain (aksen hijau borneo-trans), bukan tenant default.
      await s('Network.clearBrowserCookies');
      for (const [name, value] of Object.entries(jar)) {
        await s('Network.setCookie', { name, value: decodeURIComponent(value), domain: '127.0.0.1', path: '/' });
      }
      await s('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });
      await s('Page.enable');
      await s('Page.navigate', { url: BASE + path });

      for (let i = 0; i < 40; i++) {
        const { result: r } = await s('Runtime.evaluate', { expression: 'document.readyState', returnByValue: true });
        if (r.result?.value === 'complete') break;
        await new Promise((r2) => setTimeout(r2, 250));
      }
      // Beri waktu animasi masuk selesai; elemen yang masih ber-opacity 0 dilewati.
      await new Promise((r2) => setTimeout(r2, 1200));

      const { result } = await s('Runtime.evaluate', { expression: ANALYZER, returnByValue: true });
      const { pelanggaran, takTerukur } = JSON.parse(result.result.value);
      halamanDiperiksa++;
      totalTakTerukur += takTerukur;

      // Hiasan (numeral watermark, dsb) dipisah: kalau dicampur, ia selalu
      // menempati peringkat terburuk dan menenggelamkan teks yang benar-benar
      // harus dibaca — persis kesalahan yang perkakas ini seharusnya cegah.
      const nyata = pelanggaran.filter((p) => !p.dekoratif);
      const hiasan = pelanggaran.length - nyata.length;

      if (pelanggaran.length) {
        temuan.push({ peran, path, pelanggaran });
        console.log(`\n!! ${peran} ${path} — ${nyata.length} temuan${hiasan ? ` (+${hiasan} hiasan)` : ''}`);
        for (const p of nyata.slice(0, 6)) {
          console.log(`   ${String(p.rasio).padStart(5)}:1 (min ${p.ambang})  ${p.nonaktif ? '[nonaktif] ' : ''}"${p.teks}"`);
          console.log(`            ${p.warna} di atas ${p.latar}   ${p.el}`);
        }
        if (nyata.length > 6) console.log(`   ...dan ${nyata.length - 6} lagi`);
      } else {
        console.log(`ok  ${peran} ${path}`);
      }
    } catch (e) {
      console.log(`!! ${peran} ${path} — gagal diperiksa: ${e.message}`);
    } finally {
      await send('Target.closeTarget', { targetId });
    }
  }
}

writeFileSync('contrast-sweep-results.json', JSON.stringify(temuan, null, 1));

const semua = temuan.flatMap((t) => t.pelanggaran);
const nyata = semua.filter((p) => !p.dekoratif);
const terburuk = [...nyata].sort((a, b) => a.rasio - b.rasio)[0];
const unik = new Set(nyata.map((p) => `${p.warna}|${p.latar}|${p.el.split(' > ').pop()}`)).size;

console.log(`\n${'='.repeat(60)}`);
console.log(`halaman diperiksa : ${halamanDiperiksa}`);
console.log(`halaman bermasalah: ${temuan.filter((t) => t.pelanggaran.some((p) => !p.dekoratif)).length}`);
console.log(`temuan nyata      : ${nyata.length} (${unik} masalah unik)`);
console.log(`hiasan (sengaja samar, diabaikan): ${semua.length - nyata.length}`);
if (terburuk) console.log(`terparah          : ${terburuk.rasio}:1 — "${terburuk.teks}"`);
console.log(`dilewati (latar gradien/gambar, tak bisa diukur): ${totalTakTerukur} elemen`);
console.log('rincian -> contrast-sweep-results.json');

process.exit(0);
