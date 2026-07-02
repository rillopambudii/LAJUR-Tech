@extends('layouts.admin')

@section('title', 'Testimoni')
@section('crumb', 'Manajemen')
@section('heading', 'Testimoni')

@section('topbar-action')
    <a href="{{ route('admin.testimonials.create') }}" class="btn btn-primary"><x-icon name="plus" /> Tambah Testimoni</a>
@endsection

@section('content')
    <div class="panel">
        <div class="panel-head">
            <h2>Daftar Testimoni</h2>
            <span class="tag">{{ $testimonials->total() }} entri</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Rating</th>
                        <th>Kutipan</th>
                        <th>Status</th>
                        <th style="text-align:right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($testimonials as $t)
                    <tr>
                        <td>
                            <div class="cell-car">
                                @if ($t->avatar_url)
                                    <img src="{{ $t->avatar_url }}" alt="" data-fallback style="border-radius:50%;width:40px;height:40px">
                                @endif
                                <div>
                                    <div class="nm">{{ $t->name }}</div>
                                    @if ($t->role)<div class="br">{{ $t->role }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="stars-static" aria-label="{{ $t->rating }} bintang">
                                @for ($i = 0; $i < $t->rating; $i++)<x-icon name="star" />@endfor
                            </span>
                        </td>
                        <td style="max-width:320px;color:var(--graphite)">{{ \Illuminate\Support\Str::limit($t->quote, 80) }}</td>
                        <td><span class="pill {{ $t->is_published ? 'pill-yes' : 'pill-no' }}">{{ $t->is_published ? 'Terbit' : 'Draft' }}</span></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.testimonials.edit', $t) }}" class="icon-btn" aria-label="Edit"><x-icon name="edit" /></a>
                                <form action="{{ route('admin.testimonials.destroy', $t) }}" method="POST" data-confirm="Hapus testimoni dari {{ $t->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" aria-label="Hapus"><x-icon name="trash" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-row">Belum ada testimoni.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($testimonials->hasPages())
            {{ $testimonials->links() }}
        @endif
    </div>
@endsection
