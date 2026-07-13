@extends('layouts.admin')

@section('title', 'Pengaturan Situs')
@section('crumb', 'Situs Publik')
@section('heading', 'Pengaturan Situs')

@section('topbar-action')
    <a href="{{ route('home') }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener">
        <x-icon name="eye" /> Lihat Situs
    </a>
@endsection

@section('content')
<div class="panel" style="max-width:760px">
    <div class="panel-head">
        <h2>Branding Storefront</h2>
        <span class="tag">Tampil di situs publik Anda</span>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.site.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="form-row">
                <div class="field">
                    <label for="display_name">Nama Tampilan</label>
                    <input class="input @error('display_name') has-error @enderror" type="text" id="display_name"
                        name="display_name" value="{{ old('display_name', $tenant->display_name) }}" placeholder="{{ $tenant->name }}">
                    @error('display_name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="tagline">Tagline</label>
                    <input class="input @error('tagline') has-error @enderror" type="text" id="tagline"
                        name="tagline" value="{{ old('tagline', $tenant->tagline) }}" placeholder="Rental Mobil Premium · Kalimantan Timur">
                    @error('tagline')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="contact_phone">Telepon / WhatsApp</label>
                    <input class="input @error('contact_phone') has-error @enderror" type="text" id="contact_phone"
                        name="contact_phone" value="{{ old('contact_phone', $tenant->contact_phone) }}" placeholder="+62 812-0000-0000">
                    @error('contact_phone')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="contact_email">Email</label>
                    <input class="input @error('contact_email') has-error @enderror" type="email" id="contact_email"
                        name="contact_email" value="{{ old('contact_email', $tenant->contact_email) }}" placeholder="halo@bisnisanda.id">
                    @error('contact_email')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field">
                <label for="contact_address">Alamat</label>
                <input class="input @error('contact_address') has-error @enderror" type="text" id="contact_address"
                    name="contact_address" value="{{ old('contact_address', $tenant->contact_address) }}" placeholder="Samarinda, Kalimantan Timur">
                @error('contact_address')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="accent_color">Warna Aksen</label>
                    <div style="display:flex;gap:10px;align-items:center">
                        <input type="color" id="accent_color_picker"
                            value="{{ old('accent_color', $tenant->accent_color ?? '#E7B24C') }}"
                            style="width:46px;height:46px;border:1.5px solid var(--ivory-200);border-radius:10px;padding:2px;background:var(--white);cursor:pointer"
                            oninput="document.getElementById('accent_color').value = this.value">
                        <input class="input mono @error('accent_color') has-error @enderror" type="text" id="accent_color"
                            name="accent_color" value="{{ old('accent_color', $tenant->accent_color) }}" placeholder="#E7B24C"
                            oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('accent_color_picker').value = this.value">
                    </div>
                    @error('accent_color')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="logo">Logo (opsional)</label>
                    @if ($tenant->logo_path)
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($tenant->logo_path) }}"
                                 alt="Logo" style="width:52px;height:52px;border-radius:12px;object-fit:cover;border:1px solid var(--ivory-200)">
                            <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;color:var(--graphite);cursor:pointer">
                                <input type="checkbox" name="remove_logo" value="1"> Hapus logo
                            </label>
                        </div>
                    @endif
                    <input class="input" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
                    @error('logo')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:8px">Simpan Pengaturan</button>
            <p style="margin-top:12px;font-size:.86rem;color:var(--graphite)">Kosongkan kolom untuk memakai tampilan bawaan.</p>
        </form>
    </div>
</div>
@endsection
