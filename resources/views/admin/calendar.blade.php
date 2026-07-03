@extends('layouts.admin')

@section('title', 'Kalender Ketersediaan')
@section('crumb', 'Armada')
@section('heading', 'Kalender Ketersediaan')

@push('head')
<style>
    .cal-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:18px}
    .cal-nav{display:flex;align-items:center;gap:10px}
    .cal-nav a,.cal-nav .cal-today{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:var(--radius,10px);
        border:1px solid rgba(0,0,0,.1);background:#fff;color:inherit;text-decoration:none;font-weight:600;font-size:.9rem}
    .cal-nav .cal-month{font-family:'Sora',sans-serif;font-weight:700;font-size:1.05rem;min-width:150px;text-align:center}
    .cal-legend{display:flex;gap:16px;flex-wrap:wrap;font-size:.82rem;color:rgba(0,0,0,.6)}
    .cal-legend span{display:inline-flex;align-items:center;gap:6px}
    .cal-dot{width:13px;height:13px;border-radius:4px;display:inline-block}
    .cal-dot.confirmed{background:#16a34a}.cal-dot.pending{background:#f59e0b}.cal-dot.free{background:#eef0ee;border:1px solid rgba(0,0,0,.08)}
    .cal-wrap{overflow-x:auto;border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff}
    table.cal{border-collapse:collapse;width:100%;font-size:.8rem}
    table.cal th,table.cal td{border:1px solid rgba(0,0,0,.06);text-align:center;padding:0}
    table.cal thead th{background:#fafbfa;font-weight:600;height:40px;position:sticky;top:0}
    table.cal th.cal-car{position:sticky;left:0;z-index:2;background:#fafbfa;text-align:left;padding:10px 14px;min-width:180px;font-family:'Sora',sans-serif}
    table.cal td.cal-car{position:sticky;left:0;z-index:1;background:#fff;text-align:left;padding:10px 14px;min-width:180px;font-weight:600}
    table.cal td.day{width:30px;height:34px}
    table.cal th.wknd,table.cal td.day.wknd{background:#f6f7f6}
    table.cal th.is-today{background:#eef6ff;color:#1d4ed8;box-shadow:inset 0 -3px 0 #1d4ed8}
    .cal-cell{width:100%;height:100%;min-height:34px;display:block;border-radius:4px}
    .cal-cell.confirmed{background:#16a34a}.cal-cell.pending{background:#f59e0b}
    .cal-empty{color:rgba(0,0,0,.45);padding:26px;text-align:center}
    .cal-car small{display:block;font-weight:400;color:rgba(0,0,0,.5);font-size:.72rem}
</style>
@endpush

@section('content')
    <div class="cal-toolbar">
        <div class="cal-nav">
            <a href="{{ route('admin.calendar', ['month' => $prev]) }}" aria-label="Bulan sebelumnya">‹</a>
            <span class="cal-month">{{ $monthLabel }}</span>
            <a href="{{ route('admin.calendar', ['month' => $next]) }}" aria-label="Bulan berikutnya">›</a>
            <a href="{{ route('admin.calendar') }}" class="cal-today">Hari ini</a>
        </div>
        <div class="cal-legend">
            <span><i class="cal-dot confirmed"></i> Dikonfirmasi</span>
            <span><i class="cal-dot pending"></i> Menunggu</span>
            <span><i class="cal-dot free"></i> Tersedia</span>
        </div>
    </div>

    @if ($cars->isEmpty())
        <div class="cal-wrap"><p class="cal-empty">Belum ada mobil. Tambahkan armada terlebih dahulu di menu <strong>Mobil</strong>.</p></div>
    @else
        <div class="cal-wrap">
            <table class="cal">
                <thead>
                    <tr>
                        <th class="cal-car">Mobil</th>
                        @for ($d = 1; $d <= $daysInMonth; $d++)
                            @php $date = $month->copy()->day($d); $isToday = $date->isSameDay($today); $wknd = $date->isWeekend(); @endphp
                            <th class="{{ $isToday ? 'is-today' : '' }} {{ $wknd ? 'wknd' : '' }}" title="{{ $date->translatedFormat('l, d F Y') }}">{{ $d }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cars as $car)
                        <tr>
                            <td class="cal-car">
                                {{ $car->name }}
                                <small>{{ $car->brand }} · {{ $car->type }}@unless($car->is_available) · nonaktif @endunless</small>
                            </td>
                            @for ($d = 1; $d <= $daysInMonth; $d++)
                                @php $booking = $grid[$car->id][$d] ?? null; $wknd = $month->copy()->day($d)->isWeekend(); @endphp
                                <td class="day {{ $wknd ? 'wknd' : '' }}">
                                    @if ($booking)
                                        <a class="cal-cell {{ $booking->status }}"
                                           href="{{ route('admin.bookings.show', $booking) }}"
                                           title="{{ $booking->customer_name }} — {{ $booking->status_label }} ({{ \Illuminate\Support\Carbon::parse($booking->start_date)->format('d/m') }}–{{ \Illuminate\Support\Carbon::parse($booking->end_date)->format('d/m') }})"></a>
                                    @else
                                        <span class="cal-cell"></span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
