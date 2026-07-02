@extends('layouts.admin')

@section('title', 'Mobil')
@section('crumb', 'Manajemen')
@section('heading', 'Mobil')

@section('topbar-action')
    <a href="{{ route('admin.cars.create') }}" class="btn btn-primary"><x-icon name="plus" /> Tambah Mobil</a>
@endsection

@section('content')
    <div class="panel">
        <div class="panel-head">
            <form method="GET" class="toolbar" action="{{ route('admin.cars.index') }}">
                <div class="search">
                    <x-icon name="search" />
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, merek, atau tipe...">
                </div>
                <button class="btn btn-ghost btn-sm" type="submit">Cari</button>
                @if ($search)
                    <a href="{{ route('admin.cars.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                @endif
            </form>
            <span class="tag">{{ $cars->total() }} unit</span>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Mobil</th>
                        <th>Tipe</th>
                        <th>Harga / hari</th>
                        <th>Status</th>
                        <th>Unggulan</th>
                        <th style="text-align:right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($cars as $car)
                    <tr>
                        <td>
                            <div class="cell-car">
                                <img src="{{ $car->image_url ?? asset('img/placeholder-car.svg') }}" alt="" data-fallback>
                                <div>
                                    <div class="nm">{{ $car->name }}</div>
                                    <div class="br">{{ $car->brand }} · {{ $car->seats }} kursi · {{ $car->transmission }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="tag">{{ $car->type }}</span></td>
                        <td class="mono">Rp {{ number_format($car->price_per_day, 0, ',', '.') }}</td>
                        <td><span class="pill {{ $car->is_available ? 'pill-yes' : 'pill-no' }}">{{ $car->is_available ? 'Tersedia' : 'Nonaktif' }}</span></td>
                        <td>{!! $car->is_featured ? '<span class="pill pill-yes">Ya</span>' : '<span class="tag">—</span>' !!}</td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.cars.edit', $car) }}" class="icon-btn" aria-label="Edit"><x-icon name="edit" /></a>
                                <form action="{{ route('admin.cars.destroy', $car) }}" method="POST" data-confirm="Hapus mobil &quot;{{ $car->name }}&quot;? Tindakan ini tidak dapat dibatalkan.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" aria-label="Hapus"><x-icon name="trash" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row">
                        @if ($search) Tidak ada mobil yang cocok dengan "{{ $search }}". @else Belum ada mobil. Klik "Tambah Mobil" untuk memulai. @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($cars->hasPages())
            {{ $cars->links() }}
        @endif
    </div>
@endsection
