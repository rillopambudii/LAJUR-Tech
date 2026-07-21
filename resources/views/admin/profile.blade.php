@extends('layouts.admin')

@section('title', 'Profil Saya')
@section('crumb', 'Akun')
@section('heading', 'Profil Saya')

@section('content')
    <form action="{{ route('admin.profile.update') }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="admin-form">
            <div class="panel">
                <div class="panel-head"><h2>Foto Profil</h2><p>Opsional, maks 2 MB.</p></div>
                <div class="panel-body">
                    <div class="avatar-uploader">
                        <img id="avatar-preview-img" class="avatar avatar-preview" style="display:none" alt="Preview foto">
                        <x-avatar :user="$user" size="preview" id="avatar-preview-fallback" />
                        <div class="avatar-actions">
                            <label for="avatar" class="btn btn-ghost btn-sm" style="width:fit-content">
                                <x-icon name="edit" /> {{ $user->avatarUrl() ? 'Ganti Foto' : 'Pilih Foto' }}
                            </label>
                            <input class="avatar-file-input" type="file" id="avatar" name="avatar" accept="image/*">
                            @if ($user->avatarUrl())
                                <label class="toggle-row" style="cursor:pointer">
                                    <input type="checkbox" name="remove_avatar" value="1">
                                    <span><strong>Hapus foto saat ini</strong></span>
                                </label>
                            @endif
                            <small>JPG/PNG, disarankan rasio persegi.</small>
                            @error('avatar')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <p style="margin:14px 0 0;font-size:.85rem;color:rgba(0,0,0,.5)">
                        <span class="tag">{{ $user->isOwner() ? 'Owner' : 'Admin' }}</span>
                        Bergabung sejak {{ $user->created_at->translatedFormat('F Y') }}
                    </p>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>Data Akun</h2></div>
                <div class="panel-body">
                    <div class="form-row">
                        <div class="field">
                            <label for="name">Nama <span class="req">*</span></label>
                            <input class="input @error('name') has-error @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                            @error('name')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="phone">Nomor HP / WhatsApp</label>
                            <input class="input @error('phone') has-error @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="0812xxxxxxx">
                            @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="field">
                        <label for="email">Email <span class="req">*</span></label>
                        <input class="input @error('email') has-error @enderror" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <x-icon name="check" /> Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="panel" style="max-width:640px;margin-top:20px">
        <div class="panel-head"><h2>Ubah Password</h2></div>
        <div class="panel-body">
            <form action="{{ route('admin.profile.password') }}" method="POST">
                @csrf @method('PUT')
                <div class="field">
                    <label for="current_password">Password Lama <span class="req">*</span></label>
                    <input class="input @error('current_password') has-error @enderror" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                    @error('current_password')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="new_password">Password Baru <span class="req">*</span></label>
                        <input class="input @error('password') has-error @enderror" type="password" id="new_password" name="password" autocomplete="new-password" required>
                        <small style="display:block;margin-top:6px;color:rgba(0,0,0,.5)">Minimal 8 karakter.</small>
                        @error('password')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Ulangi Password Baru <span class="req">*</span></label>
                        <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-ghost">
                    <x-icon name="key" /> Ubah Password
                </button>
            </form>
        </div>
    </div>
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
