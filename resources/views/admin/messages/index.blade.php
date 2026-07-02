@extends('layouts.admin')

@section('title', 'Pesan')
@section('crumb', 'Manajemen')
@section('heading', 'Pesan Masuk')

@section('content')
    <div class="panel">
        <div class="panel-head">
            <h2>Kotak Masuk</h2>
            <span class="tag">{{ $messages->total() }} pesan</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Pengirim</th>
                        <th>Subjek</th>
                        <th>Diterima</th>
                        <th>Status</th>
                        <th style="text-align:right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($messages as $m)
                    <tr style="{{ $m->is_read ? '' : 'background:rgba(231,178,76,.06)' }}">
                        <td>
                            <div class="nm" style="font-weight:{{ $m->is_read ? '500' : '700' }}">{{ $m->name }}</div>
                            <div class="br" style="font-size:.82rem;color:var(--graphite)">{{ $m->email }}</div>
                        </td>
                        <td>{{ $m->subject ?: '—' }}</td>
                        <td class="mono" style="font-size:.85rem">{{ $m->created_at->format('d M Y, H:i') }}</td>
                        <td><span class="pill {{ $m->is_read ? 'pill-no' : 'pill-pending' }}">{{ $m->is_read ? 'Dibaca' : 'Baru' }}</span></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.messages.show', $m) }}" class="icon-btn" aria-label="Buka"><x-icon name="eye" /></a>
                                <form action="{{ route('admin.messages.destroy', $m) }}" method="POST" data-confirm="Hapus pesan dari {{ $m->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" aria-label="Hapus"><x-icon name="trash" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-row">Belum ada pesan masuk.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($messages->hasPages())
            {{ $messages->links() }}
        @endif
    </div>
@endsection
