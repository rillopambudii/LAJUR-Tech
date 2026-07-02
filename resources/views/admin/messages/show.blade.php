@extends('layouts.admin')

@section('title', 'Detail Pesan')
@section('crumb', 'Manajemen / Pesan')
@section('heading', 'Detail Pesan')

@section('topbar-action')
    <a href="{{ route('admin.messages.index') }}" class="btn btn-ghost">&larr; Kembali</a>
@endsection

@section('content')
<div class="panel-grid">
    <div class="panel">
        <div class="panel-head">
            <h2>{{ $message->subject ?: 'Tanpa subjek' }}</h2>
            <span class="pill {{ $message->is_read ? 'pill-no' : 'pill-pending' }}">{{ $message->is_read ? 'Dibaca' : 'Baru' }}</span>
        </div>
        <div class="panel-body">
            <div class="detail-grid" style="margin-bottom:20px">
                <div class="detail-item"><div class="k">Nama</div><div class="v">{{ $message->name }}</div></div>
                <div class="detail-item"><div class="k">Email</div><div class="v"><a href="mailto:{{ $message->email }}">{{ $message->email }}</a></div></div>
                @if ($message->phone)
                    <div class="detail-item"><div class="k">Nomor HP</div><div class="v"><a href="tel:{{ $message->phone }}">{{ $message->phone }}</a></div></div>
                @endif
                <div class="detail-item"><div class="k">Diterima</div><div class="v">{{ $message->created_at->format('d M Y, H:i') }}</div></div>
            </div>
            <div class="detail-item">
                <div class="k">Pesan</div>
                <div class="v" style="font-weight:400;white-space:pre-line;margin-top:6px;line-height:1.7">{{ $message->message }}</div>
            </div>
        </div>
    </div>

    <div class="panel preview-card">
        <div class="panel-head"><h2 style="font-size:1rem">Tindakan</h2></div>
        <div class="panel-body">
            <a href="mailto:{{ $message->email }}?subject=Re: {{ $message->subject }}" class="btn btn-primary btn-block">
                <x-icon name="reply" /> Balas via Email
            </a>
            <form action="{{ route('admin.messages.toggle', $message) }}" method="POST" style="margin-top:12px">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-ghost btn-block">
                    Tandai {{ $message->is_read ? 'Belum Dibaca' : 'Sudah Dibaca' }}
                </button>
            </form>
            <hr style="border:0;border-top:1px solid var(--ivory-200);margin:18px 0">
            <form action="{{ route('admin.messages.destroy', $message) }}" method="POST" data-confirm="Hapus pesan ini?">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-block" style="color:var(--danger);border-color:rgba(200,69,59,.3)">
                    <x-icon name="trash" /> Hapus Pesan
                </button>
            </form>
            @if ($message->ip_address)
                <p style="color:var(--graphite-300);font-size:.8rem;margin-top:16px;font-family:var(--font-mono)">IP: {{ $message->ip_address }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
