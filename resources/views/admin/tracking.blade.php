@extends('layouts.admin')

@section('title', 'Pelacakan Unit')
@section('crumb', 'Armada')
@section('heading', 'Pelacakan Unit')

@section('content')
@if (! $mapsKey && ! $demo)
    <div class="alert alert-error" role="alert">
        <x-icon name="alert" />
        <span>Peta belum aktif. Setel <code>GOOGLE_MAPS_API_KEY</code> di <code>.env</code> (Google Maps JavaScript API), atau nyalakan <code>TRACKING_DEMO=true</code> untuk mode demo. Lalu <code>php artisan config:clear</code>.</span>
    </div>
@else
    <div class="track-wrap">
        <aside class="track-panel">
            <div class="track-mode">
                <x-icon name="pin" /> <span>Unit Live</span>
                @if ($demo)<span class="track-demo">Mode Demo</span>@endif
            </div>
            <div class="track-units" data-units><p class="track-empty">Memuat…</p></div>

            @unless ($demo)
            <div class="track-history">
                <h4>Rute Histori</h4>
                <select data-hist-car aria-label="Pilih mobil">
                    @foreach ($cars as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <input type="date" data-hist-from aria-label="Dari tanggal">
                <input type="date" data-hist-to aria-label="Sampai tanggal">
                <button type="button" class="btn btn-primary btn-sm" data-hist-show><x-icon name="route" /> Tampilkan Rute</button>
                <button type="button" class="btn btn-ghost btn-sm" data-hist-clear>Kembali ke Live</button>
            </div>
            @endunless
        </aside>

        <div class="track-map" id="track-map"></div>
    </div>
@endif
@endsection

@if ($demo)
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/tracking-demo.js') }}"></script>
<script>
    window.TrackingDemo.fleet('track-map', {
        routesUrl: @json(asset('js/demo-routes.json')),
        onUnits: function (units) {
            var list = document.querySelector('[data-units]');
            list.innerHTML = units.map(function (u) {
                return '<button type="button" class="track-unit"><span class="dot moving"></span>'
                    + u.name + '<small>' + u.speed + ' km/j</small></button>';
            }).join('');
        }
    });
</script>
@endpush
@elseif ($mapsKey)
@push('scripts')
<script>
    window.TRACK = {
        live: @json(route('admin.tracking.live')),
        history: @json(route('admin.tracking.history')),
        center: @json($center),
    };
    (function () {
        var map, info, markers = {}, histLine = null;
        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

        window.initTrackingMap = function () {
            map = new google.maps.Map(document.getElementById('track-map'), {
                center: window.TRACK.center, zoom: 12, mapTypeControl: false, streetViewControl: false, fullscreenControl: true,
            });
            info = new google.maps.InfoWindow();
            loadLive();
            setInterval(loadLive, 15000);
            document.querySelector('[data-hist-show]').addEventListener('click', showHistory);
            document.querySelector('[data-hist-clear]').addEventListener('click', clearHistory);
        };

        function loadLive() {
            fetch(window.TRACK.live, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); }).then(render).catch(function () {});
        }

        function render(data) {
            var positions = data.positions || [];
            var list = document.querySelector('[data-units]');
            var bounds = new google.maps.LatLngBounds();
            var seen = {};

            positions.forEach(function (p) {
                seen[p.car_id] = true;
                var pos = { lat: p.lat, lng: p.lng };
                if (markers[p.car_id]) {
                    markers[p.car_id].setPosition(pos);
                } else {
                    markers[p.car_id] = new google.maps.Marker({ map: map, position: pos, title: p.name });
                    markers[p.car_id].addListener('click', function () { openInfo(p.car_id); });
                }
                markers[p.car_id]._data = p;
                bounds.extend(pos);
            });
            Object.keys(markers).forEach(function (id) { if (!seen[id]) { markers[id].setMap(null); delete markers[id]; } });

            if (!positions.length) {
                list.innerHTML = '<p class="track-empty">Belum ada data lokasi. Integrasi Traccar akan mengisi ini.</p>';
            } else {
                list.innerHTML = positions.map(function (p) {
                    return '<button type="button" class="track-unit" data-focus="' + p.car_id + '">'
                        + '<span class="dot' + (p.speed > 0 ? ' moving' : '') + '"></span>' + esc(p.name)
                        + '<small>' + (p.minutes_ago != null ? p.minutes_ago + ' mnt' : 'live') + '</small></button>';
                }).join('');
                list.querySelectorAll('[data-focus]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        var m = markers[b.getAttribute('data-focus')];
                        if (m) { map.panTo(m.getPosition()); map.setZoom(15); openInfo(b.getAttribute('data-focus')); }
                    });
                });
            }
            if (!histLine && positions.length) { map.fitBounds(bounds); if (positions.length === 1) map.setZoom(14); }
        }

        function openInfo(id) {
            var m = markers[id]; if (!m) return;
            var p = m._data || {};
            info.setContent('<div style="font:14px system-ui"><strong>' + esc(p.name) + '</strong><br>'
                + (p.plate ? esc(p.plate) + '<br>' : '')
                + 'Kecepatan: ' + (p.speed || 0) + ' km/j<br>'
                + (p.minutes_ago != null ? 'Update: ' + p.minutes_ago + ' menit lalu' : '') + '</div>');
            info.open(map, m);
        }

        function showHistory() {
            var car = document.querySelector('[data-hist-car]').value;
            var from = document.querySelector('[data-hist-from]').value;
            var to = document.querySelector('[data-hist-to]').value;
            var url = window.TRACK.history + '?car=' + encodeURIComponent(car)
                + (from ? '&from=' + from : '') + (to ? '&to=' + to + 'T23:59:59' : '');
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var pts = (d.points || []).map(function (p) { return { lat: p.lat, lng: p.lng }; });
                    if (histLine) histLine.setMap(null);
                    if (!pts.length) { alert('Tidak ada data rute pada periode ini.'); histLine = null; return; }
                    histLine = new google.maps.Polyline({ map: map, path: pts, strokeColor: '#E7B24C', strokeWeight: 4, strokeOpacity: 0.9 });
                    var b = new google.maps.LatLngBounds(); pts.forEach(function (p) { b.extend(p); }); map.fitBounds(b);
                }).catch(function () {});
        }

        function clearHistory() { if (histLine) { histLine.setMap(null); histLine = null; } loadLive(); }
    })();
</script>
<script async src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&callback=initTrackingMap"></script>
@endpush
@endif
