<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * "Profil Saya" untuk owner & admin — beda dari profil driver (baca-saja,
 * "hubungi admin"): owner/admin tak punya atasan di dalam tenant untuk
 * diminta ubah data, jadi halaman ini bisa-edit-sendiri.
 */
class ProfileController extends Controller
{
    public function show(): View
    {
        return view('admin.profile', ['user' => auth()->user()]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;

        if ($request->boolean('remove_avatar')) {
            $this->deleteFile($user->avatar_path);
            $user->avatar_path = null;
        } elseif ($request->hasFile('avatar')) {
            $this->deleteFile($user->avatar_path);
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return redirect()->route('admin.profile.show')->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Password lama wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password lama tidak cocok.',
            ]);
        }

        $user->password = $request->input('password'); // hashed via cast
        $user->save();

        return redirect()->route('admin.profile.show')->with('success', 'Password berhasil diubah.');
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
