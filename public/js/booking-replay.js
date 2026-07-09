/* Trip Replay engine (client-side). Requires Leaflet global `L`. */
(function () {
  function haversine(a, b) {
    var R = 6371000, toRad = function (d) { return d * Math.PI / 180; };
    var dLat = toRad(b[0] - a[0]), dLng = toRad(b[1] - a[1]);
    var s = Math.sin(dLat / 2) * Math.sin(dLat / 2)
      + Math.cos(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.sqrt(s));
  }
  function icon() {
    return L.icon({
      iconUrl: '/vendor/leaflet/images/marker-icon.png',
      iconRetinaUrl: '/vendor/leaflet/images/marker-icon-2x.png',
      shadowUrl: '/vendor/leaflet/images/marker-shadow.png',
      iconSize: [25, 41], iconAnchor: [12, 41]
    });
  }
  function fmtTime(iso) { try { return new Date(iso).toLocaleString('id-ID'); } catch (e) { return iso || ''; } }

  window.BookingReplay = {
    init: function (opts) {
      var map = L.map(opts.mapEl);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
      var c = opts.controls,
          playBtn = document.getElementById(c.playBtn), speedSel = document.getElementById(c.speedSel),
          scrubber = document.getElementById(c.scrubber), clock = document.getElementById(c.clock), summary = document.getElementById(c.summary);

      fetch(opts.url, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (data) {
        var pts = data.points || [];
        if (!pts.length) { summary.textContent = 'Tidak ada data GPS untuk perjalanan ini.'; return; }
        var latlngs = pts.map(function (p) { return [p.lat, p.lng]; });
        L.polyline(latlngs, { color: '#0f1b33', weight: 3, opacity: 0.35 }).addTo(map);
        var marker = L.marker(latlngs[0], { icon: icon() }).addTo(map);
        map.fitBounds(L.latLngBounds(latlngs));

        var dist = 0; for (var k = 0; k < latlngs.length - 1; k++) dist += haversine(latlngs[k], latlngs[k + 1]);
        var speeds = pts.map(function (p) { return p.speed || 0; });
        var maxS = Math.max.apply(null, speeds), avgS = Math.round(speeds.reduce(function (a, b) { return a + b; }, 0) / speeds.length);
        summary.innerHTML = 'Jarak: <strong>' + (dist / 1000).toFixed(1) + ' km</strong> · '
          + 'Kecepatan maks/rata: <strong>' + maxS + '/' + avgS + ' km/j</strong> · '
          + 'Mulai: ' + fmtTime(pts[0].time) + ' → Selesai: ' + fmtTime(pts[pts.length - 1].time);

        scrubber.max = String(pts.length - 1);
        scrubber.value = '0';
        var idx = 0, playing = false, timer = null, speed = 1;

        function show(i) {
          idx = i; marker.setLatLng(latlngs[i]); scrubber.value = String(i);
          clock.textContent = fmtTime(pts[i].time) + ' · ' + (pts[i].speed || 0) + ' km/j';
        }
        function tick() {
          if (idx >= pts.length - 1) { stop(); return; }
          show(idx + 1);
        }
        function play() { if (playing) return; playing = true; playBtn.textContent = '⏸'; timer = setInterval(tick, 700 / speed); }
        function stop() { playing = false; playBtn.textContent = '▶'; if (timer) { clearInterval(timer); timer = null; } }

        playBtn.addEventListener('click', function () { playing ? stop() : play(); });
        scrubber.addEventListener('input', function () { stop(); show(parseInt(scrubber.value, 10)); });
        speedSel.addEventListener('change', function () { speed = parseFloat(speedSel.value) || 1; if (playing) { stop(); play(); } });
        show(0);
      }).catch(function () { summary.textContent = 'Gagal memuat data replay.'; });
    }
  };
})();
