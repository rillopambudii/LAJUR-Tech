@extends('layouts.admin')

@section('title', 'Laporan')
@section('crumb', 'Analitik')
@section('heading', 'Laporan & Analitik')

@php
    // Compact currency for chart bars (e.g. 2,5jt).
    $compact = function (int $v): string {
        if ($v >= 1_000_000) return rtrim(rtrim(number_format($v / 1_000_000, 1, ',', '.'), '0'), ',').'jt';
        if ($v >= 1_000) return round($v / 1_000).'rb';
        return (string) $v;
    };
    $statusColors = ['pending' => 'pill-pending', 'confirmed' => 'pill-confirmed', 'completed' => 'pill-completed', 'cancelled' => 'pill-cancelled'];
@endphp

@section('content')
    {{-- Filter + export --}}
    <div class="panel" style="margin-bottom:18px">
        <div class="panel-body">
            <form method="GET" action="{{ route('admin.reports') }}" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
                <div class="field" style="margin:0">
                    <label for="from">Dari</label>
                    <input class="input" type="date" id="from" name="from" value="{{ $from->toDateString() }}">
                </div>
                <div class="field" style="margin:0">
                    <label for="to">Sampai</label>
                    <input class="input" type="date" id="to" name="to" value="{{ $to->toDateString() }}">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
                <a href="{{ route('admin.reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-ghost btn-sm"><x-icon name="list" /> Ekspor CSV</a>
            </form>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="ico"><x-icon name="wallet" /></div>
            <div class="num">Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</div>
            <div class="lbl">Pendapatan (confirmed + selesai)</div>
        </div>
        <div class="stat-card">
            <div class="ico"><x-icon name="list" /></div>
            <div class="num">{{ $summary['bookings_total'] }}</div>
            <div class="lbl">Total Booking</div>
        </div>
        <div class="stat-card">
            <div class="ico"><x-icon name="tag" /></div>
            <div class="num">Rp {{ number_format($summary['avg_value'], 0, ',', '.') }}</div>
            <div class="lbl">Rata-rata Nilai Booking</div>
        </div>
        <div class="stat-card">
            <div class="ico"><x-icon name="gauge" /></div>
            <div class="num">{{ number_format($summary['utilization'], 1, ',', '.') }}%</div>
            <div class="lbl">Okupansi Armada</div>
        </div>
    </div>

    <div class="panel-grid">
        {{-- Revenue chart (last 12 months) --}}
        <div class="panel">
            <div class="panel-head"><h2>Pendapatan 12 Bulan Terakhir</h2></div>
            <div class="panel-body">
                <div class="chart" role="img" aria-label="Grafik pendapatan dua belas bulan terakhir">
                    @foreach ($revenueByMonth as $bar)
                        <div class="col" title="{{ $bar['full'] }}: Rp {{ number_format($bar['value'], 0, ',', '.') }}">
                            <div class="bar" data-h="{{ round($bar['value'] / $maxRevenue * 100) }}" style="height:0">
                                <span class="val">{{ $compact($bar['value']) }}</span>
                            </div>
                            <span class="lab">{{ $bar['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Status breakdown --}}
        <div class="panel">
            <div class="panel-head"><h2>Booking per Status</h2><span class="tag">{{ $from->translatedFormat('d M') }} – {{ $to->translatedFormat('d M Y') }}</span></div>
            <div class="table-wrap">
                <table class="data">
                    <tbody>
                    @foreach ($summary['status_breakdown'] as $status => $count)
                        <tr>
                            <td><span class="pill {{ $statusColors[$status] ?? '' }}">{{ \App\Models\Booking::STATUS_LABELS[$status] }}</span></td>
                            <td class="mono" style="text-align:right">{{ $count }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Top cars --}}
    <div class="panel">
        <div class="panel-head"><h2>Mobil Terlaris</h2><span class="tag">berdasarkan pendapatan</span></div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Mobil</th><th class="mono" style="text-align:right">Booking</th><th class="mono" style="text-align:right">Pendapatan</th></tr>
                </thead>
                <tbody>
                @forelse ($topCars as $car)
                    <tr>
                        <td class="nm">{{ $car->car_name }}</td>
                        <td class="mono" style="text-align:right">{{ $car->bookings }}</td>
                        <td class="mono" style="text-align:right">Rp {{ number_format($car->revenue, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty-row">Belum ada data pendapatan pada periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
