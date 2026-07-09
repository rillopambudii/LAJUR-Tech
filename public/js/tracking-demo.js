/* Demo tracking simulator (client-side only). Requires Leaflet global `L`.
   No network except OSM tiles + fetching the local routes JSON. */
(function () {
  var SPEED_KMH = 30, STEP_METERS = 60, TICK_MS = 1000;

  function haversine(a, b) { // [lat,lng] -> meters
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
  function baseMap(el, center) {
    var map = L.map(el, { zoomControl: true, attributionControl: true }).setView(center, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '© OpenStreetMap'
    }).addTo(map);
    return map;
  }
  // A mover walks a polyline `pts` in STEP_METERS increments; loops if `loop`.
  function mover(pts, loop) {
    var i = 0, frac = 0; // between pts[i] and pts[i+1]
    var segLen = function (k) { return haversine(pts[k], pts[k + 1]); };
    return {
      done: false,
      pos: function () {
        var a = pts[i], b = pts[i + 1] || pts[i];
        return [a[0] + (b[0] - a[0]) * frac, a[1] + (b[1] - a[1]) * frac];
      },
      remainingMeters: function () {
        var m = segLen(i) * (1 - frac);
        for (var k = i + 1; k < pts.length - 1; k++) m += segLen(k);
        return m;
      },
      progress: function () {
        var total = 0, passed = 0;
        for (var k = 0; k < pts.length - 1; k++) { var L2 = segLen(k); total += L2; if (k < i) passed += L2; else if (k === i) passed += L2 * frac; }
        return total ? Math.min(100, Math.round(passed / total * 100)) : 100;
      },
      step: function () {
        if (i >= pts.length - 1) { if (loop) { i = 0; frac = 0; } else { this.done = true; } return; }
        var need = STEP_METERS, len = segLen(i) || 0.0001;
        frac += need / len;
        while (frac >= 1 && i < pts.length - 1) { frac -= 1; i++; if (i < pts.length - 1) { len = segLen(i) || 0.0001; } }
        if (i >= pts.length - 1) { frac = 0; if (!loop) this.done = true; }
      }
    };
  }
  function etaMinutes(meters) { return Math.max(0, Math.round(meters / 1000 / SPEED_KMH * 60)); }

  function load(url) { return fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }); }

  window.TrackingDemo = {
    fleet: function (el, opts) {
      var timer, map, markers = {};
      load(opts.routesUrl).then(function (data) {
        map = baseMap(el, data.center);
        var movers = data.routes.map(function (r) { return { r: r, m: mover(r.points, true) }; });
        movers.forEach(function (o) { markers[o.r.id] = L.marker(o.m.pos(), { icon: icon() }).addTo(map).bindPopup(o.r.name + '<br>' + o.r.plate); });
        map.setView(data.center, 12);
        timer = setInterval(function () {
          var units = movers.map(function (o) {
            o.m.step(); var p = o.m.pos(); markers[o.r.id].setLatLng(p);
            return { id: o.r.id, name: o.r.name, plate: o.r.plate, speed: SPEED_KMH };
          });
          if (opts.onUnits) opts.onUnits(units);
        }, TICK_MS);
      });
      return { stop: function () { clearInterval(timer); } };
    },
    trip: function (el, opts) {
      var timer, map;
      load(opts.routesUrl).then(function (data) {
        map = baseMap(el, data.center);
        var r = data.routes[0], m = mover(r.points, false);
        var marker = L.marker(m.pos(), { icon: icon() }).addTo(map).bindPopup(r.name);
        var dest = L.circleMarker(data.destination, { radius: 8, color: '#E7B24C', fillOpacity: 0.9 }).addTo(map).bindPopup('Tujuan kamu');
        L.polyline(r.points, { color: '#0f1b33', weight: 3, opacity: 0.25 }).addTo(map);
        map.fitBounds(L.latLngBounds(r.points));
        timer = setInterval(function () {
          m.step(); marker.setLatLng(m.pos());
          var arrived = m.done, mins = etaMinutes(m.remainingMeters());
          if (opts.onEta) opts.onEta({ minutes: mins, progress: arrived ? 100 : m.progress(), arrived: arrived });
          if (arrived) clearInterval(timer);
        }, TICK_MS);
      });
      return { stop: function () { clearInterval(timer); } };
    }
  };
})();
