@extends('layouts.admin')

@section('title', 'Driver')
@section('crumb', 'Manajemen')
@section('heading', 'Driver')

@section('topbar-action')
    <a href="{{ route('admin.drivers.create') }}" class="btn btn-primary"><x-icon name="plus" /> Tambah Driver</a>
@endsection

@section('content')
    <div class="panel">
        <div class="panel-head">
            <form method="GET" class="toolbar" action="{{ route('admin.drivers.index') }}">
                <div class="search">
                    <x-icon name="search" />
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, email, atau HP...">
                </div>
                <button class="btn btn-ghost btn-sm" type="submit">Cari</button>
                @if ($search)
                    <a href="{{ route('admin.drivers.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                @endif
            </form>
            <span class="tag">{{ $drivers->total() }} driver</span>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Nomor HP</th>
                        <th>Tugas</th>
                        <th style="text-align:right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($drivers as $driver)
                    <tr>
                        <td>
                            <div class="cell-driver">
                                <x-avatar :user="$driver" size="sm" />
                                <div class="nm">{{ $driver->name }}</div>
                            </div>
                        </td>
                        <td><a href="mailto:{{ $driver->email }}">{{ $driver->email }}</a></td>
                        <td>{!! $driver->phone ? '<a href="tel:'.e($driver->phone).'">'.e($driver->phone).'</a>' : '<span class="tag">—</span>' !!}</td>
                        <td><span class="tag">{{ $driver->driver_bookings_count }} booking</span></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.drivers.edit', $driver) }}" class="icon-btn" aria-label="Edit"><x-icon name="edit" /></a>
                                <form action="{{ route('admin.drivers.destroy', $driver) }}" method="POST" data-confirm="Hapus driver &quot;{{ $driver->name }}&quot;? Booking yang terkait akan kehilangan penugasan driver.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" aria-label="Hapus"><x-icon name="trash" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-row">
                        @if ($search) Tidak ada driver yang cocok dengan "{{ $search }}". @else Belum ada driver. Klik "Tambah Driver" untuk memulai. @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($drivers->hasPages())
            {{ $drivers->links() }}
        @endif
    </div>
@endsection
