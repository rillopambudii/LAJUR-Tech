@extends('layouts.admin')

@section('title', 'BBM & Solar')
@section('crumb', 'Operasional')
@section('heading', 'BBM & Solar')

@section('topbar-action')
    <a href="{{ route('admin.fuel.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" /> Catat Pengisian</a>
@endsection

@php
    use App\Fuel\FuelService;
    $flagPill = fn (string $f) => in_array($f, FuelService::RED_FLAGS, true) ? 'pill-cancelled' : 'pill-pending';
@endphp

@section('content')
    {{-- Nudge onboarding: deteksi kebocoran mati diam-diam tanpa spesifikasi armada. --}}
    @if ($carsMissingSpecs->isNotEmpty())
        <div class="panel" style="border-left:4px solid var(--warn);margin-bottom:18px">
            <div class="panel-body">
                <strong>Deteksi kebocoran belum aktif untuk {{ $carsMissingSpecs->count() }} mobil.</strong>
                <p style="margin:6px 0 12px;color:var(--graphite)">
                    Mobil berikut belum diisi <strong>kapasitas tangki</strong> atau <strong>konsumsi normal (km/L)</strong>,
                    sehingga anomali <em>isi melebihi tangki</em> dan <em>konsumsi boros</em> tidak bisa dihitung.
                    Lengkapi agar deteksi berjalan:
                </p>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @foreach ($carsMissingSpecs as $car)
                        <a href="{{ route('admin.cars.edit', $car) }}" class="btn btn-ghost btn-sm">
                            {{ $car->name }}
                            @if (! $car->tank_capacity_liters && ! $car->fuel_baseline_km_per_l)
                                <span class="tag">tangki &amp; km/L</span>
                            @elseif (! $car->tank_capacity_liters)
                                <span class="tag">tangki</span>
                            @else
                                <span class="tag">km/L</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Filter + export --}}
    <div class="panel" style="margin-bottom:18px">
        <div class="panel-body">
            <form method="GET" action="{{ route('admin.fuel.index') }}" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
                <div class="field" style="margin:0">
                    <label for="car_id">Mobil</label>
                    <select class="input" id="car_id" name="car_id">
                        <option value="">Semua mobil</option>
                        @foreach ($cars as $c)
                            <option value="{{ $c->id }}" @selected($carId === $c->id)>{{ $c->name }} {{ $c->plate_number ? '('.$c->plate_number.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin:0">
                    <label for="from">Dari</label>
                    <input class="input" type="date" id="from" name="from" value="{{ $from->toDateString() }}">
                </div>
                <div class="field" style="margin:0">
                    <label for="to">Sampai</label>
                    <input class="input" type="date" id="to" name="to" value="{{ $to->toDateString() }}">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
                <a href="{{ route('admin.export.download', ['dataset' => 'fuel', 'format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-ghost btn-sm">PDF</a>
                <a href="{{ route('admin.export.download', ['dataset' => 'fuel', 'format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-ghost btn-sm">Excel</a>
            </form>
        </div>
    </div>

    {{-- Indikator per mobil --}}
    @forelse ($summaries as $s)
        <div class="panel" style="margin-bottom:18px">
            <div class="panel-head">
                <h2>{{ $s['car']->name }} @if($s['car']->plate_number)<span class="tag">{{ $s['car']->plate_number }}</span>@endif</h2>
                <span>
                    @if ($s['red_flags'] > 0)<span class="pill pill-cancelled">{{ $s['red_flags'] }} anomali merah</span>@endif
                    @if ($s['yellow_flags'] > 0)<span class="pill pill-pending">{{ $s['yellow_flags'] }} perlu diperiksa</span>@endif
                    @if ($s['red_flags'] === 0 && $s['yellow_flags'] === 0)<span class="pill pill-completed">Normal</span>@endif
                </span>
            </div>
            <div class="panel-body">
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="ico"><x-icon name="fuel" /></div>
                        <div class="num">{{ number_format($s['liters'], 1, ',', '.') }} L</div>
                        <div class="lbl">{{ $s['fills'] }}× pengisian · Rp {{ number_format($s['cost'], 0, ',', '.') }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="ico"><x-icon name="gauge" /></div>
                        <div class="num">{{ $s['km_per_liter'] !== null ? number_format($s['km_per_liter'], 1, ',', '.').' km/L' : '—' }}</div>
                        <div class="lbl">
                            Konsumsi aktual
                            @if ($s['baseline'])
                                (baseline {{ number_format($s['baseline'], 1, ',', '.') }})
                                @if ($s['deviation_pct'] !== null)
                                    ·
                                    @if ($s['deviation_pct'] > 20)
                                        <strong style="color:var(--danger)">{{ $s['deviation_pct'] }}% lebih boros</strong>
                                    @elseif ($s['deviation_pct'] > 0)
                                        {{ $s['deviation_pct'] }}% lebih boros
                                    @else
                                        {{ abs($s['deviation_pct']) }}% lebih irit
                                    @endif
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="ico"><x-icon name="wallet" /></div>
                        <div class="num">{{ $s['cost_per_km'] !== null ? 'Rp '.number_format($s['cost_per_km'], 0, ',', '.') : '—' }}</div>
                        <div class="lbl">Biaya per km ({{ number_format($s['km'], 0, ',', '.') }} km tercatat)</div>
                    </div>
                    <div class="stat-card">
                        <div class="ico"><x-icon name="pin" /></div>
                        <div class="num">
                            @if ($s['gps_gap_pct'] !== null)
                                <span @if($s['gps_gap_pct'] > 30) style="color:var(--danger)" @endif>{{ $s['gps_gap_pct'] }}%</span>
                            @else
                                —
                            @endif
                        </div>
                        <div class="lbl">Selisih GPS ({{ $s['gps_km'] !== null ? number_format($s['gps_km'], 0, ',', '.').' km' : 'tanpa data' }}) vs odometer ({{ $s['odo_delta_km'] !== null ? number_format($s['odo_delta_km'], 0, ',', '.').' km' : 'tanpa data' }})</div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="panel" style="margin-bottom:18px">
            <div class="panel-body">
                <p class="empty-row">Belum ada pengisian BBM pada periode ini. Klik <strong>Catat Pengisian</strong> untuk memulai.</p>
            </div>
        </div>
    @endforelse

    {{-- Daftar log --}}
    <div class="panel">
        <div class="panel-head"><h2>Riwayat Pengisian</h2><span class="tag">{{ $from->translatedFormat('d M') }} – {{ $to->translatedFormat('d M Y') }}</span></div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Mobil</th>
                        <th class="mono" style="text-align:right">Liter</th>
                        <th class="mono" style="text-align:right">Harga/L</th>
                        <th class="mono" style="text-align:right">Total</th>
                        <th class="mono" style="text-align:right">Odometer</th>
                        <th class="mono" style="text-align:right">km/L</th>
                        <th>SPBU</th>
                        <th>Anomali</th>
                        <th>Pencatat</th>
                        <th>Struk</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="mono">{{ $log->filled_at->format('d/m/y H:i') }}</td>
                        <td class="nm">{{ $log->car->name }}</td>
                        <td class="mono" style="text-align:right">{{ number_format($log->liters, 1, ',', '.') }}{{ $log->full_tank ? '' : '*' }}</td>
                        <td class="mono" style="text-align:right">{{ number_format($log->price_per_liter, 0, ',', '.') }}</td>
                        <td class="mono" style="text-align:right">{{ number_format($log->total_cost, 0, ',', '.') }}</td>
                        <td class="mono" style="text-align:right">{{ $log->odometer_km !== null ? number_format($log->odometer_km, 0, ',', '.') : '—' }}</td>
                        <td class="mono" style="text-align:right">{{ $log->segment_km_per_l !== null ? number_format($log->segment_km_per_l, 1, ',', '.') : '—' }}</td>
                        <td>{{ $log->station ?: '—' }}</td>
                        <td>
                            @forelse ($log->flags as $flag)
                                <span class="pill {{ $flagPill($flag) }}" title="{{ FuelService::FLAG_LABELS[$flag] }}">{{ FuelService::FLAG_LABELS[$flag] }}</span>
                            @empty
                                <span class="pill pill-completed">OK</span>
                            @endforelse
                        </td>
                        <td>
                            {{ $log->creator?->name ?? '—' }}
                            @if ($log->creator?->role === \App\Models\User::ROLE_DRIVER)
                                <span class="tag">driver</span>
                            @endif
                        </td>
                        <td>
                            @if ($log->receiptUrl())
                                <a href="{{ $log->receiptUrl() }}" target="_blank" rel="noopener" class="icon-btn" aria-label="Lihat struk"><x-icon name="eye" /></a>
                            @else
                                <span class="tag">—</span>
                            @endif
                        </td>
                        <td style="text-align:right">
                            <form action="{{ route('admin.fuel.destroy', $log) }}" method="POST" onsubmit="return confirm('Hapus catatan pengisian ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" aria-label="Hapus"><x-icon name="trash" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="empty-row">Belum ada catatan pengisian pada periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->isNotEmpty())
            <div class="panel-body" style="padding-top:0">
                <p style="color:var(--graphite);font-size:.82rem;margin:0">* = pengisian tidak penuh (tidak dipakai untuk hitung konsumsi). km/L dihitung metode full-to-full dari odometer, fallback km GPS.</p>
            </div>
        @endif
    </div>
@endsection
