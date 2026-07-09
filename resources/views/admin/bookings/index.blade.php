@extends('layouts.admin')

@section('title', 'Booking')
@section('crumb', 'Manajemen')
@section('heading', 'Booking')

@section('content')
    <div class="panel">
        <div class="panel-head">
            <form method="GET" class="toolbar" action="{{ route('admin.bookings.index') }}">
                <div class="search">
                    <x-icon name="search" />
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, email, atau mobil...">
                </div>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    @foreach (\App\Models\Booking::STATUSES as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ \App\Models\Booking::STATUS_LABELS[$s] }}</option>
                    @endforeach
                </select>
                <button class="btn btn-ghost btn-sm" type="submit">Cari</button>
                @if ($search || $status)
                    <a href="{{ route('admin.bookings.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                @endif
            </form>
            <span class="tag">{{ $bookings->total() }} booking</span>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Penyewa</th>
                        <th>Mobil</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th style="text-align:right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($bookings as $b)
                    <tr>
                        <td>
                            <div class="nm" style="font-weight:600">{{ $b->customer_name }}</div>
                            <div class="br" style="font-size:.82rem;color:var(--graphite)">{{ $b->customer_email }}</div>
                            @if ($b->booking_code)
                                <div class="mono" style="font-size:.78rem;color:var(--petrol);letter-spacing:.03em">{{ $b->booking_code }}</div>
                            @endif
                        </td>
                        <td>{{ $b->car_name }}</td>
                        <td class="mono" style="font-size:.85rem">
                            {{ $b->start_date->format('d M Y') }}<br>
                            <span style="color:var(--graphite)">→ {{ $b->end_date->format('d M Y') }} ({{ $b->days }}h)</span>
                        </td>
                        <td class="mono">Rp {{ number_format($b->total_price, 0, ',', '.') }}</td>
                        <td><span class="pill pill-{{ $b->status }}">{{ $b->status_label }}</span></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.bookings.show', $b) }}" class="icon-btn" aria-label="Detail"><x-icon name="eye" /></a>
                                <form action="{{ route('admin.bookings.destroy', $b) }}" method="POST" data-confirm="Hapus booking dari {{ $b->customer_name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" aria-label="Hapus"><x-icon name="trash" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row">
                        @if ($search || $status) Tidak ada booking yang cocok. @else Belum ada booking masuk. @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages())
            {{ $bookings->links() }}
        @endif
    </div>
@endsection
