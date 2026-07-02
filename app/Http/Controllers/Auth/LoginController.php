<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        // Already-authenticated admins go straight to the dashboard (FR-27).
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $remember = $request->boolean('remember');

        // Generic failure message — never reveal whether the email exists (NFR-11 / NFR-25).
        $generic = ['email' => 'Email atau kata sandi salah.'];

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages($generic);
        }

        // Only administrators may proceed (FR-24).
        if (! Auth::user()->is_admin) {
            // Log out, but keep the session so the validation error redirects
            // back to the login form (invalidating it would drop the previous URL).
            Auth::guard('web')->logout();

            throw ValidationException::withMessages($generic);
        }

        // Prevent session fixation (NFR-12).
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
