@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi — Lajur')
@section('heading', 'Lupa kata sandi?')
@section('sub', 'Masukkan email akun Anda. Kami kirim tautan untuk mengatur ulang kata sandi.')

@section('content')
<form action="{{ route('password.email') }}" method="POST" novalidate>
    @csrf
    <div class="field">
        <label for="email">Email</label>
        <input class="input @error('email') has-error @enderror" type="email" id="email" name="email"
            value="{{ old('email') }}" required autofocus autocomplete="username">
    </div>
    <button type="submit" class="btn btn-primary btn-block">
        <x-icon name="mail" /> Kirim Tautan Reset
    </button>
</form>
@endsection
