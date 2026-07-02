<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Admin — Lajur</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .auth-wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px;
            background: radial-gradient(120% 90% at 80% -10%, var(--petrol-600), var(--petrol)); }
        .auth-card { width: 100%; max-width: 420px; background: var(--ivory); border-radius: var(--radius-lg);
            padding: 38px 34px; box-shadow: var(--shadow-lg); }
        .auth-card .brand { justify-content: center; margin-bottom: 26px; }
        .auth-head { text-align: center; margin-bottom: 26px; }
        .auth-head h1 { font-size: 1.55rem; }
        .auth-head p { color: var(--graphite); margin-top: 6px; font-size: .95rem; }
        .back-home { display: block; text-align: center; margin-top: 18px; color: var(--graphite); font-size: .9rem; }
        .back-home:hover { color: var(--petrol); }
        .check-row { display: flex; align-items: center; gap: 8px; margin-bottom: 18px; font-size: .92rem; color: var(--graphite); }
    </style>
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <a href="{{ route('home') }}" class="brand">
                <span class="mark"><x-icon name="route" /></span> Lajur
            </a>
            <div class="auth-head">
                <h1>Masuk Admin</h1>
                <p>Panel pengelolaan Lajur</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <x-icon name="alert" />
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form action="{{ route('login.attempt') }}" method="POST" novalidate>
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input @error('email') has-error @enderror" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="field">
                    <label for="password">Kata Sandi</label>
                    <input class="input @error('password') has-error @enderror" type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <label class="check-row">
                    <input type="checkbox" name="remember" value="1"> Ingat saya
                </label>
                <button type="submit" class="btn btn-primary btn-block">
                    <x-icon name="key" /> Masuk
                </button>
            </form>

            <a href="{{ route('home') }}" class="back-home">&larr; Kembali ke beranda</a>
        </div>
    </div>
</body>
</html>
