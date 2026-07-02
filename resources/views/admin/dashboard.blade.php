@extends('layouts.admin')

@section('title', 'Dashboard')
@section('crumb', 'Ringkasan')
@section('heading', 'Dashboard')

@section('content')
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
@endsection
