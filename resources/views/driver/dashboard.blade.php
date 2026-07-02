@extends('layouts.driver')

@section('title', 'Jadwal Tugas')

@php
    $statusColors = ['pending' => 'pill-pending', 'confirmed' => 'pill-confirmed', 'completed' => 'pill-completed', 'cancelled' => 'pill-cancelled'];
@endphp

@section('content')
    <h1 style="font-family:'Sora',sans-serif;font-size:1.6rem;margin:6px 0 4px">Jadwal Tugas Saya</h1>
    <p style="color:rgba(0,0,0,.55);margin:0">{{ $upcoming->count() }} tugas mendatang</p>

    <h2 class="drv-section-title">Tugas Mendatang</h2>
    <div class="panel">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Mobil</th><th>Penyewa</th><th>Mulai</th><th>Selesai</th><th>Status</th></tr>
                </thead>
                <tbody>
                @forelse ($upcoming as $b)
                    <tr>
                        <td class="nm">{{ $b->car_name }}</td>
                        <td>{{ $b->customer_name }}<br><a href="tel:{{ $b->customer_phone }}" style="font-size:.82rem">{{ $b->customer_phone }}</a></td>
                        <td>{{ $b->start_date->translatedFormat('d M Y') }}</td>
                        <td>{{ $b->end_date->translatedFormat('d M Y') }}</td>
                        <td><span class="pill {{ $statusColors[$b->status] ?? '' }}">{{ $b->status_label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-row">Belum ada tugas mendatang.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($past->isNotEmpty())
        <h2 class="drv-section-title">Riwayat Terakhir</h2>
        <div class="panel">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>Mobil</th><th>Penyewa</th><th>Tanggal</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($past as $b)
                        <tr>
                            <td class="nm">{{ $b->car_name }}</td>
                            <td>{{ $b->customer_name }}</td>
                            <td>{{ $b->start_date->translatedFormat('d M') }} – {{ $b->end_date->translatedFormat('d M Y') }}</td>
                            <td><span class="pill {{ $statusColors[$b->status] ?? '' }}">{{ $b->status_label }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
