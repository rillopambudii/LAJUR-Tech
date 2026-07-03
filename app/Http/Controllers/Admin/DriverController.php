<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriverRequest;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    /** Drivers are Users with role=driver. User has no global tenant scope,
     *  so every query is constrained to the current tenant by hand. */
    private function tenantId(): ?int
    {
        return app(TenantManager::class)->id();
    }

    /** Ensure the bound driver belongs to this tenant and really is a driver. */
    private function guard(User $driver): void
    {
        if ($driver->tenant_id !== $this->tenantId() || $driver->role !== User::ROLE_DRIVER) {
            abort(404);
        }
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $drivers = User::query()
            ->forTenant($this->tenantId())
            ->where('role', User::ROLE_DRIVER)
            ->when($search, fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->withCount('driverBookings')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.drivers.index', compact('drivers', 'search'));
    }

    public function create(): View
    {
        return view('admin.drivers.form', ['driver' => new User]);
    }

    public function store(DriverRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'tenant_id' => $this->tenantId(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'], // hashed via cast
            'role' => User::ROLE_DRIVER,
            'is_admin' => false,
        ]);

        return redirect()->route('admin.drivers.index')->with('success', 'Driver berhasil ditambahkan.');
    }

    public function edit(User $driver): View
    {
        $this->guard($driver);

        return view('admin.drivers.form', ['driver' => $driver]);
    }

    public function update(DriverRequest $request, User $driver): RedirectResponse
    {
        $this->guard($driver);

        $data = $request->validated();
        $driver->name = $data['name'];
        $driver->email = $data['email'];
        $driver->phone = $data['phone'] ?? null;

        if (filled($data['password'] ?? null)) {
            $driver->password = $data['password']; // hashed via cast
        }

        $driver->save();

        return redirect()->route('admin.drivers.index')->with('success', 'Data driver berhasil diperbarui.');
    }

    public function destroy(User $driver): RedirectResponse
    {
        $this->guard($driver);

        // Bookings keep their history; driver_id is set null via FK (nullOnDelete).
        $driver->delete();

        return redirect()->route('admin.drivers.index')->with('success', 'Driver berhasil dihapus.');
    }
}
