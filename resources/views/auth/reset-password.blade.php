@extends('layouts.auth')

@section('title', 'Atur Ulang Kata Sandi — Lajur')
@section('heading', 'Atur ulang kata sandi')
@section('sub', 'Buat kata sandi baru untuk akun Anda.')

@section('content')
<form action="{{ route('password.update') }}" method="POST" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field">
        <label for="email">Email</label>
        <input class="input @error('email') has-error @enderror" type="email" id="email" name="email"
            value="{{ old('email', $email) }}" required autocomplete="username" readonly>
    </div>
    <div class="field">
        <label for="password">Kata Sandi Baru</label>
        <input class="input @error('password') has-error @enderror" type="password" id="password" name="password"
            minlength="8" required autofocus autocomplete="new-password">
        <span style="display:block;margin-top:6px;font-size:.84rem;color:var(--graphite)">Minimal 8 karakter.</span>
    </div>
    <div class="field">
        <label for="password_confirmation">Ulangi Kata Sandi Baru</label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation"
            minlength="8" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary btn-block">
        <x-icon name="key" /> Ubah Kata Sandi
    </button>
</form>
@endsection
