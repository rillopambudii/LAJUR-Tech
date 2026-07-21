@extends('layouts.admin')

@section('title', 'Staf Admin')
@section('crumb', 'Manajemen')
@section('heading', 'Staf Admin')

@section('topbar-action')
    <a href="{{ route('admin.staff.create') }}" class="btn btn-primary"><x-icon name="plus" /> Tambah Staf</a>
@endsection

@section('content')
    <div class="panel">
        <div class="panel-head">
            <form method="GET" class="toolbar" action="{{ route('admin.staff.index') }}">
                <div class="search">
                    <x-icon name="search" />
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, email, atau HP...">
                </div>
                <button class="btn btn-ghost btn-sm" type="submit">Cari</button>
                @if ($search)
                    <a href="{{ route('admin.staff.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                @endif
            </form>
            <span class="tag">{{ $staff->total() }} staf</span>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Nomor HP</th>
                        <th style="text-align:right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td>
                            <div class="cell-driver">
                                <x-avatar :user="$member" size="sm" />
                                <div class="nm">{{ $member->name }}</div>
                            </div>
                        </td>
                        <td><a href="mailto:{{ $member->email }}">{{ $member->email }}</a></td>
                        <td>{!! $member->phone ? '<a href="tel:'.e($member->phone).'">'.e($member->phone).'</a>' : '<span class="tag">—</span>' !!}</td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.staff.edit', $member) }}" class="icon-btn" aria-label="Edit"><x-icon name="edit" /></a>
                                <form action="{{ route('admin.staff.destroy', $member) }}" method="POST" data-confirm="Hapus staf &quot;{{ $member->name }}&quot;? Akun ini tidak akan bisa masuk lagi.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" aria-label="Hapus"><x-icon name="trash" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-row">
                        @if ($search) Tidak ada staf yang cocok dengan "{{ $search }}". @else Belum ada staf admin. Klik "Tambah Staf" untuk memulai. @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($staff->hasPages())
            {{ $staff->links() }}
        @endif
    </div>
@endsection
