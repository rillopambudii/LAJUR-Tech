<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffRequest;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Staf admin (role=admin) = akun bantu pengelola tenant, bukan pendiri.
 * Hanya owner yang boleh membuat/mengubah/menghapus (route: role:owner) —
 * mencegah admin menambah admin lain atau menyingkirkan sesama staf.
 */
class StaffController extends Controller
{
    private function tenantId(): ?int
    {
        return app(TenantManager::class)->id();
    }

    /** Pastikan staf terikat ke tenant ini dan memang role=admin (bukan owner/driver). */
    private function guard(User $staff): void
    {
        if ($staff->tenant_id !== $this->tenantId() || $staff->role !== User::ROLE_ADMIN) {
            abort(404);
        }
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $staff = User::query()
            ->forTenant($this->tenantId())
            ->where('role', User::ROLE_ADMIN)
            ->when($search, fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.staff.index', compact('staff', 'search'));
    }

    public function create(): View
    {
        return view('admin.staff.form', ['staff' => new User]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'tenant_id' => $this->tenantId(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'avatar_path' => $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'public') : null,
            'password' => $data['password'], // hashed via cast
            'role' => User::ROLE_ADMIN,
            'is_admin' => true,
        ]);

        return redirect()->route('admin.staff.index')->with('success', 'Staf admin berhasil ditambahkan.');
    }

    public function edit(User $staff): View
    {
        $this->guard($staff);

        return view('admin.staff.form', ['staff' => $staff]);
    }

    public function update(StaffRequest $request, User $staff): RedirectResponse
    {
        $this->guard($staff);

        $data = $request->validated();
        $staff->name = $data['name'];
        $staff->email = $data['email'];
        $staff->phone = $data['phone'] ?? null;

        if ($request->boolean('remove_avatar')) {
            $this->deleteFile($staff->avatar_path);
            $staff->avatar_path = null;
        } elseif ($request->hasFile('avatar')) {
            $this->deleteFile($staff->avatar_path);
            $staff->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        if (filled($data['password'] ?? null)) {
            $staff->password = $data['password']; // hashed via cast
        }

        $staff->save();

        return redirect()->route('admin.staff.index')->with('success', 'Data staf berhasil diperbarui.');
    }

    public function destroy(User $staff): RedirectResponse
    {
        $this->guard($staff);

        $this->deleteFile($staff->avatar_path);
        $staff->delete();

        return redirect()->route('admin.staff.index')->with('success', 'Staf admin berhasil dihapus.');
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
