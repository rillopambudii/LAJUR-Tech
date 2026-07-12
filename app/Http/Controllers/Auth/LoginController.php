<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        // Already-authenticated users go straight to their home (FR-27).
        if (Auth::check()) {
            return redirect($this->homeFor(Auth::user()));
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

        $user = Auth::user();

        // Only staff (owner/admin/driver/super_admin) may log in. Customers have no portal yet.
        if (! ($user->isManager() || $user->hasRole(User::ROLE_DRIVER, User::ROLE_SUPER_ADMIN) || $user->is_admin)) {
            // Log out, but keep the session so the validation error redirects
            // back to the login form (invalidating it would drop the previous URL).
            Auth::guard('web')->logout();

            throw ValidationException::withMessages($generic);
        }

        // Prevent session fixation (NFR-12).
        $request->session()->regenerate();

        return redirect()->intended($this->homeFor($user));
    }

    /** The landing route for a user based on their role. */
    private function homeFor(User $user): string
    {
        if ($user->hasRole(User::ROLE_DRIVER)) {
            return route('driver.dashboard');
        }

        if ($user->hasRole(User::ROLE_SUPER_ADMIN)) {
            return route('superadmin.plans.index');
        }

        return route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
