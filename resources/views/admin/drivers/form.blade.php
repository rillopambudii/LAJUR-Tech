@extends('layouts.admin')

@php $isEdit = $driver->exists; @endphp

@section('title', $isEdit ? 'Edit Driver' : 'Tambah Driver')
@section('crumb', 'Manajemen / Driver')
@section('heading', $isEdit ? 'Edit Driver' : 'Tambah Driver')

@section('topbar-action')
    <a href="{{ route('admin.drivers.index') }}" class="btn btn-ghost">&larr; Kembali</a>
@endsection

@section('content')
<form action="{{ $isEdit ? route('admin.drivers.update', $driver) : route('admin.drivers.store') }}" method="POST">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="admin-form">
        <div class="panel">
            <div class="panel-body">
                <div class="form-row">
                    <div class="field">
                        <label for="name">Nama Driver <span class="req">*</span></label>
                        <input class="input @error('name') has-error @enderror" id="name" name="name" value="{{ old('name', $driver->name) }}" required>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="phone">Nomor HP</label>
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
