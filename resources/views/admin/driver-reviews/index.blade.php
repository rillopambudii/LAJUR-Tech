@extends('layouts.admin')

@section('title', 'Ulasan Driver')
@section('crumb', 'Manajemen')
@section('heading', 'Ulasan Driver')

@section('content')
    @php
        $statusPill = ['pending' => 'pill-pending', 'published' => 'pill-yes', 'rejected' => 'pill-no'];
        $statusLabel = ['pending' => 'Menunggu', 'published' => 'Terbit', 'rejected' => 'Ditolak'];
    @endphp
    <div class="panel">
        <div class="panel-head">
            <h2>Daftar Ulasan</h2>
            <span class="tag">{{ $reviews->total() }} entri</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Penilai</th>
                        <th>Rating</th>
                        <th>Komentar</th>
                        <th>Status</th>
                        <th style="text-align:right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($reviews as $r)
                    <tr>
                        <td><div class="nm">{{ $r->driver?->name ?? '—' }}</div></td>
                        <td>{{ $r->maskedCustomerName() }}</td>
                        <td>
                            <span class="stars-static" aria-label="{{ $r->rating_overall }} bintang">
                                @for ($i = 0; $i < round($r->rating_overall); $i++)<x-icon name="star" />@endfor
                            </span>
                        </td>
                        <td style="max-width:260px;color:var(--graphite)">{{ \Illuminate\Support\Str::limit($r->comment, 70) ?: '—' }}</td>
                        <td><span class="pill {{ $statusPill[$r->status] ?? '' }}">{{ $statusLabel[$r->status] ?? $r->status }}</span></td>
                        <td>
                            <div class="row-actions">
                                @if ($r->status !== 'published')
                                    <form action="{{ route('admin.driver-reviews.approve', $r) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="icon-btn" aria-label="Setujui"><x-icon name="check" /></button>
                                    </form>
                                @endif
                                @if ($r->status !== 'rejected')
                                    <form action="{{ route('admin.driver-reviews.reject', $r) }}" method="POST" data-confirm="Tolak ulasan ini? Tidak akan tampil di profil driver.">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="icon-btn danger" aria-label="Tolak"><x-icon name="close" /></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6" style="padding-top:0;border-top:0">
                            <details>
                                <summary style="cursor:pointer;color:var(--petrol-600);font-size:.86rem">
                                    {{ $r->admin_reply ? 'Ubah balasan' : 'Balas ulasan' }}
                                </summary>
                                <form action="{{ route('admin.driver-reviews.reply', $r) }}" method="POST" style="margin-top:8px;display:flex;gap:8px;align-items:flex-start">
                                    @csrf @method('PATCH')
                                    <textarea class="input" name="admin_reply" rows="2" maxlength="1000" placeholder="Tulis balasan...">{{ $r->admin_reply }}</textarea>
                                    <button type="submit" class="btn btn-ghost btn-sm">Simpan</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row">Belum ada ulasan driver.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($reviews->hasPages())
            {{ $reviews->links() }}
        @endif
    </div>
@endsection
