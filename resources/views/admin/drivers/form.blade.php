@extends('layouts.admin')

@php $isEdit = $driver->exists; @endphp

@section('title', $isEdit ? 'Edit Driver' : 'Tambah Driver')
@section('crumb', 'Manajemen / Driver')
@section('heading', $isEdit ? 'Edit Driver' : 'Tambah Driver')

@section('topbar-action')
    <a href="{{ route('admin.drivers.index') }}" class="btn btn-ghost">&larr; Kembali</a>
@endsection

@section('content')
<form action="{{ $isEdit ? route('admin.drivers.update', $driver) : route('admin.drivers.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="admin-form">
        <div class="panel">
            <div class="panel-head"><h2>Foto Profil</h2><p>Tampil di dashboard driver dan daftar driver. Opsional, maks 2 MB.</p></div>
            <div class="panel-body">
                <div class="avatar-uploader">
                    <img id="avatar-preview-img" class="avatar avatar-preview" style="display:none" alt="Preview foto">
                    <x-avatar :user="$driver" size="preview" id="avatar-preview-fallback" />
                    <div class="avatar-actions">
                        <label for="avatar" class="btn btn-ghost btn-sm" style="width:fit-content">
                            <x-icon name="edit" /> {{ $isEdit && $driver->avatarUrl() ? 'Ganti Foto' : 'Pilih Foto' }}
                        </label>
                        <input class="avatar-file-input" type="file" id="avatar" name="avatar" accept="image/*">
                        @if ($isEdit && $driver->avatarUrl())
                            <label class="toggle-row" style="cursor:pointer">
                                <input type="checkbox" name="remove_avatar" value="1">
                                <span><strong>Hapus foto saat ini</strong></span>
                            </label>
                        @endif
                        <small>JPG/PNG, disarankan rasio persegi.</small>
                        @error('avatar')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="form-row">
                    <div class="field">
                        <label for="name">Nama Driver <span class="req">*</span></label>
                        <input class="input @error('name') has-error @enderror" id="name" name="name" value="{{ old('name', $driver->name) }}" required>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="phone">Nomor HP / WhatsApp</label>
                        <input class="input @error('phone') has-error @enderror" id="phone" name="phone" value="{{ old('phone', $driver->phone) }}" placeholder="0812xxxxxxx">
                        @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="email">Email <span class="req">*</span></label>
                        <input class="input @error('email') has-error @enderror" type="email" id="email" name="email" value="{{ old('email', $driver->email) }}" required>
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="password">Kata Sandi @unless($isEdit)<span class="req">*</span>@endunless</label>
                        <input class="input @error('password') has-error @enderror" type="password" id="password" name="password" autocomplete="new-password" @unless($isEdit) required @endunless>
                        <small style="display:block;margin-top:6px;color:rgba(0,0,0,.5)">@if($isEdit) Kosongkan jika tidak ingin mengubah. @else Minimal 8 karakter. @endif</small>
                        @error('password')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <x-icon name="check" /> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Driver' }}
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('avatar');
        var img = document.getElementById('avatar-preview-img');
        var fallback = document.getElementById('avatar-preview-fallback');
        if (!input) return;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                img.src = e.target.result;
                img.style.display = 'block';
                if (fallback) fallback.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });
    })();
</script>
@endpush
