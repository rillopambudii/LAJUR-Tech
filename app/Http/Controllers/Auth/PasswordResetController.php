<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Lupa & reset kata sandi memakai broker bawaan Laravel (token di
 * password_reset_tokens, kedaluwarsa & sekali-pakai ditangani framework).
 *
 * Prinsip privasi (sama dgn login, NFR-11): jangan pernah membocorkan apakah
 * sebuah email terdaftar — pesan sukses selalu sama, terdaftar atau tidak.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']], [], ['email' => 'email']);

        // Kirim tautan bila email ada; abaikan hasilnya untuk tidak membocorkan
        // keberadaan akun. Di dev (MAIL_MAILER=log) tautan masuk ke laravel.log.
        Password::sendResetLink($request->only('email'));

        return back()->with('status',
            'Jika email tersebut terdaftar, kami telah mengirim tautan untuk mengatur ulang kata sandi. Silakan cek kotak masuk Anda.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status === Password::PasswordReset) {
            return redirect()->route('login')->with('status',
                'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru Anda.');
        }

        throw ValidationException::withMessages([
            'email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.',
        ]);
    }
}
