@extends('layouts.admin')

@section('title', 'Dashboard')
@section('crumb', 'Ringkasan')
@section('heading', 'Dashboard')

@section('content')
    {{-- Kartu AI hanya untuk plan yang punya fitur ai_assistant — tanpa @if ini,
         fetch async insight kena gate feature middleware → flash error nyasar. --}}
    @if (app(\App\Tenancy\TenantManager::class)->current()?->hasFeature('ai_assistant'))
    <div class="ai-insight" data-ai-insight>
        <div class="ai-insight-head">
            <span class="ai-insight-title"><x-icon name="sparkle" /> Ringkasan AI</span>
            <div class="ai-insight-tools">
                <button type="button" class="ai-insight-refresh" data-refresh title="Perbarui ringkasan" aria-label="Perbarui ringkasan">↻</button>
                <a href="{{ route('admin.assistant') }}" class="ai-insight-ask">Tanya Asisten →</a>
            </div>
        </div>
        <p class="ai-insight-body" data-body>Menyusun ringkasan…</p>
    </div>
    @endif

    <div class="stat-grid">
        <div class="stat-card">
            <div class="ico"><x-icon name="car" /></div>
            <div class="num">{{ $stats['cars_total'] }}</div>
            <div class="lbl">Total Mobil</div>
        </div>
        <div class="stat-card">
            <div class="ico"><x-icon name="check" /></div>
            <div class="num">{{ $stats['cars_available'] }}</div>
            <div class="lbl">Mobil Tersedia</div>
        </div>
        <div class="stat-card">
            <div class="ico"><x-icon name="list" /></div>
            <div class="num">{{ $stats['bookings_total'] }}</div>
            <div class="lbl">Total Booking</div>
        </div>
        <div class="stat-card">
            <div class="ico"><x-icon name="clock" /></div>
            <div class="num">{{ $stats['bookings_pending'] }}</div>
            <div class="lbl">Booking Pending</div>
        </div>
        <div class="stat-card">
            <div class="ico"><x-icon name="star" /></div>
            <div class="num">{{ $stats['testimonials'] }}</div>
            <div class="lbl">Testimoni</div>
        </div>
        <div class="stat-card">
            <div class="ico"><x-icon name="chat" /></div>
            <div class="num">{{ $stats['messages_unread'] }}</div>
            <div class="lbl">Pesan Belum Dibaca</div>
        </div>
        <div class="stat-card accent" style="grid-column: span 2">
            <div class="ico"><x-icon name="wallet" /></div>
            <div class="num">Rp {{ number_format($stats['revenue'], 0, ',', '.') }}</div>
            <div class="lbl">Total Pendapatan (booking selesai)</div>
        </div>
    </div>

    <div class="panel-grid">
        <div class="panel">
            <div class="panel-head"><h2>Booking 6 Bulan Terakhir</h2></div>
            <div class="panel-body">
                <div class="chart" role="img" aria-label="Grafik jumlah booking enam bulan terakhir">
                    @foreach ($chart as $bar)
                        <div class="col" title="{{ $bar['full'] }}: {{ $bar['count'] }} booking">
                            <div class="bar" data-h="{{ round($bar['count'] / $maxChart * 100) }}" style="height:0">
                                <span class="val">{{ $bar['count'] }}</span>
                            </div>
                            <span class="lab">{{ $bar['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Booking Terbaru</h2>
                <a href="{{ route('admin.bookings.index') }}" class="btn btn-ghost btn-sm">Semua</a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <tbody>
                    @forelse ($recentBookings as $b)
                        <tr>
                            <td>
                                <div class="cell-car">
                                    <div>
                                        <div class="nm">{{ $b->customer_name }}</div>
                                        <div class="br">{{ $b->car_name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="pill pill-{{ $b->status }}">{{ $b->status_label }}</span></td>
                            <td class="mono" style="text-align:right">
                                <a href="{{ route('admin.bookings.show', $b) }}" class="icon-btn"><x-icon name="eye" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="empty-row">Belum ada booking.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>Pengingat Servis &amp; Pajak</h2>
            <span class="tag">{{ $reminders->count() }} perlu perhatian</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Mobil</th><th>Pajak (STNK)</th><th>Servis</th><th style="text-align:right">Aksi</th></tr>
                </thead>
                <tbody>
                @php
                    $badge = fn ($status) => match ($status) {
                        'overdue' => '<span class="pill pill-cancelled">Terlewat</span>',
                        'soon' => '<span class="pill pill-pending">Segera</span>',
                        default => '',
                    };
                @endphp
                @forelse ($reminders as $car)
                    <tr>
                        <td>
                            <div class="nm">{{ $car->name }}</div>
                            <div class="br">{{ $car->plate_number ?: $car->brand }}</div>
                        </td>
                        <td>
                            @if ($car->tax_due_date)
                                {{ $car->tax_due_date->translatedFormat('d M Y') }} {!! $badge($car->taxStatus()) !!}
                            @else <span class="tag">—</span> @endif
                        </td>
                        <td>
                            @if ($car->service_due_date)
                                {{ $car->service_due_date->translatedFormat('d M Y') }} {!! $badge($car->serviceStatus()) !!}
                            @else <span class="tag">—</span> @endif
                        </td>
                        <td style="text-align:right">
                            <a href="{{ route('admin.cars.edit', $car) }}" class="icon-btn" aria-label="Edit"><x-icon name="edit" /></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-row">Tidak ada pengingat servis atau pajak dalam {{ \App\Models\Car::REMINDER_WINDOW_DAYS }} hari ke depan. 🎉</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var card = document.querySelector('[data-ai-insight]');
    if (!card) return;
    var body = card.querySelector('[data-body]');
    var refresh = card.querySelector('[data-refresh]');
    var url = @json(route('admin.assistant.insight'));
    function load(fresh) {
        card.classList.add('loading');
        body.textContent = 'Menyusun ringkasan…';
        fetch(url + (fresh ? '?fresh=1' : ''), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) { body.textContent = (d && d.text) ? d.text : 'Ringkasan belum tersedia.'; })
            .catch(function () { body.textContent = 'Ringkasan belum tersedia.'; })
            .finally(function () { card.classList.remove('loading'); });
    }
    if (refresh) refresh.addEventListener('click', function () { load(true); });
    load(false);
})();
</script>
@endpush
