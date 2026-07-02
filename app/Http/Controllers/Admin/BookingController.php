<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Models\Booking;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        if (! in_array($status, Booking::STATUSES, true)) {
            $status = null;
        }

        $bookings = Booking::query()
            ->status($status)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('car_name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'search', 'status'));
    }

    public function show(Booking $booking): View
    {
        $booking->load('car', 'driver');

        // Drivers of this tenant, for the assignment dropdown.
        $drivers = User::query()
            ->forTenant(app(TenantManager::class)->id())
            ->where('role', User::ROLE_DRIVER)
            ->orderBy('name')
            ->get();

        return view('admin.bookings.show', compact('booking', 'drivers'));
    }

    public function assignDriver(Request $request, Booking $booking): RedirectResponse
    {
        $tenantId = app(TenantManager::class)->id();

        $validated = $request->validate([
            'driver_id' => [
                'nullable',
                // The driver must be a driver within this tenant.
                Rule::exists('users', 'id')
                    ->where('role', User::ROLE_DRIVER)
                    ->where('tenant_id', $tenantId),
            ],
        ], [
            'driver_id.exists' => 'Driver yang dipilih tidak valid.',
        ]);

        $booking->update(['driver_id' => $validated['driver_id'] ?: null]);

        return back()->with('success', $validated['driver_id']
            ? 'Driver berhasil ditugaskan.'
            : 'Penugasan driver dihapus.');
    }

    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): RedirectResponse
    {
        $booking->update(['status' => $request->validated()['status']]);

        return back()->with('success', 'Status booking berhasil diperbarui.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $booking->delete();

        return redirect()->route('admin.bookings.index')->with('success', 'Booking berhasil dihapus.');
    }
}
