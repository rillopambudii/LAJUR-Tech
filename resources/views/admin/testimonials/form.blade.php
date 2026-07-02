@extends('layouts.admin')

@php $isEdit = $testimonial->exists; @endphp

@section('title', $isEdit ? 'Edit Testimoni' : 'Tambah Testimoni')
@section('crumb', 'Manajemen / Testimoni')
@section('heading', $isEdit ? 'Edit Testimoni' : 'Tambah Testimoni')

@section('topbar-action')
    <a href="{{ route('admin.testimonials.index') }}" class="btn btn-ghost">&larr; Kembali</a>
@endsection

@section('content')
<form action="{{ $isEdit ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}"
      method="POST" enctype="multipart/form-data">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="admin-form">
        <div class="panel">
            <div class="panel-body">
                <div class="form-row">
                    <div class="field">
                        <label for="name">Nama <span class="req">*</span></label>
                        <input class="input @error('name') has-error @enderror" id="name" name="name" value="{{ old('name', $testimonial->name) }}" required>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="role">Jabatan / Perusahaan</label>
                        <input class="input @error('role') has-error @enderror" id="role" name="role" value="{{ old('role', $testimonial->role) }}">
                        @error('role')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="field">
                    <label for="rating">Rating <span class="req">*</span></label>
                    <select class="select @error('rating') has-error @enderror" id="rating" name="rating" required>
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" @selected(old('rating', $testimonial->rating ?? 5) == $i)>{{ $i }} bintang</option>
                        @endfor
                    </select>
                    @error('rating')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="field">
                    <label for="quote">Kutipan <span class="req">*</span></label>
                    <textarea class="textarea @error('quote') has-error @enderror" id="quote" name="quote" required>{{ old('quote', $testimonial->quote) }}</textarea>
                    @error('quote')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        <div>
            <div class="panel">
                <div class="panel-head"><h2 style="font-size:1rem">Foto / Avatar</h2></div>
                <div class="panel-body">
                    <img class="img-preview" data-image-preview style="aspect-ratio:1;max-width:140px;border-radius:50%"
                         src="{{ $testimonial->avatar_url ?? asset('img/placeholder-car.svg') }}" alt="Pratinjau avatar">
                    <label class="file-drop">
                        <x-icon name="plus" style="width:20px;height:20px;margin:0 auto 6px" />
                        Pilih foto (maks 2 MB)
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-image-input>
                    </label>
                    @error('avatar')<span class="field-error">{{ $message }}</span>@enderror
                    <div class="field" style="margin-top:14px">
                        <label for="avatar_url">atau URL Foto</label>
                        <input class="input @error('avatar_url') has-error @enderror" id="avatar_url" name="avatar_url" value="{{ old('avatar_url') }}" placeholder="https://...">
                        @error('avatar_url')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-body">
                    <div class="switch-row">
                        <span>Tampilkan di landing</span>
                        <label class="switch">
                            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $testimonial->is_published ?? true))>
                            <span class="track"></span>
                        </label>
                    </div>
                    <div class="field" style="margin-top:14px">
                        <label for="sort_order">Urutan Tampil</label>
                        <input class="input" type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $testimonial->sort_order ?? 0) }}">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <x-icon name="check" /> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Testimoni' }}
            </button>
        </div>
    </div>
</form>
@endsection
