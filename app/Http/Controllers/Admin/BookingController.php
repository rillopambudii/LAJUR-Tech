<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Mail\BookingInvoiceMail;
use App\Models\Booking;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
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
        $booking->load('car.latestPosition', 'driver');

        // Drivers of this tenant, for the assignment dropdown.
        $drivers = User::query()
            ->forTenant(app(TenantManager::class)->id())
            ->where('role', User::ROLE_DRIVER)
            ->orderBy('name')
            ->get();

        return view('admin.bookings.show', compact('booking', 'drivers'));
    }

    /** GPS track for a booking's rental window (Trip Replay). */
    public function replay(Booking $booking): JsonResponse
    {
        $from = $booking->start_date->copy()->startOfDay();
        $to = $booking->end_date->copy()->endOfDay();

        $points = collect();
        if ($booking->car) {
            $points = $booking->car->positions()
                ->whereBetween('device_time', [$from, $to])
                ->orderBy('device_time')
                ->get(['latitude', 'longitude', 'speed', 'device_time'])
                ->map(fn ($p) => [
                    'lat' => (float) $p->latitude,
                    'lng' => (float) $p->longitude,
                    'speed' => (int) $p->speed,
                    'time' => optional($p->device_time)->toIso8601String(),
                ]);
        }

        if ($points->isEmpty() && config('services.tracking.demo')) {
            $points = $this->fabricateReplay($booking, $from, $to);
        }

        return response()->json(['car' => $booking->car_name, 'points' => $points->values()]);
    }

    /**
     * Fabricate a deterministic demo route for one booking (seeded by id), with
     * timestamps spread across the rental window. Never persisted.
     */
    private function fabricateReplay(Booking $booking, $from, $to): Collection
    {
        $centerLat = -0.502106;
        $centerLng = 117.153709;
        $n = 40;
        $seed = (int) $booking->id;
        $lat = $centerLat + (($seed % 100) / 100 - 0.5) * 0.05;
        $lng = $centerLng + ((intdiv($seed, 100) % 100) / 100 - 0.5) * 0.05;
        $span = max(1, $to->getTimestamp() - $from->getTimestamp());

        $points = collect();
        for ($i = 0; $i < $n; $i++) {
            $lat += sin($i / 3 + $seed) * 0.0016;
            $lng += cos($i / 4 + $seed) * 0.0016;
            $t = $from->copy()->addSeconds((int) ($span * $i / ($n - 1)));
            $points->push([
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'speed' => 20 + (($seed + $i) % 40),
                'time' => $t->toIso8601String(),
            ]);
        }

        return $points;
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
            'destination' => ['nullable', 'string', 'max:200'],
        ], [
            'driver_id.exists' => 'Driver yang dipilih tidak valid.',
        ]);

        // Satu driver tak boleh ditugaskan ke dua perjalanan yang tanggalnya
        // bertumpang-tindih (analog dgn ketersediaan mobil).
        if (! empty($validated['driver_id'])
            && Booking::query()
                ->where('driver_id', $validated['driver_id'])
                ->where('id', '!=', $booking->id)
                ->active()
                ->overlapping($booking->start_date->toDateString(), $booking->end_date->toDateString())
                ->exists()) {
            return back()->withErrors([
                'driver_id' => 'Driver ini sudah ditugaskan ke perjalanan lain pada rentang tanggal yang sama.',
            ]);
        }

        $booking->update([
            'driver_id' => $validated['driver_id'] ?: null,
            'destination' => $validated['destination'] ?? null,
        ]);

        return back()->with('success', $validated['driver_id']
            ? 'Driver berhasil ditugaskan.'
            : 'Penugasan driver dihapus.');
    }

    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): RedirectResponse
    {
        $new = $request->validated()['status'];

        // Mengembalikan booking ke status yang mem-blokir (pending/confirmed)
        // harus lolos cek ketersediaan lagi — kalau tidak, meng-"un-cancel"
        // booking bisa menabrak booking lain yang sudah mengisi tanggalnya.
        if (! in_array($booking->status, Booking::BLOCKING_STATUSES, true)
            && in_array($new, Booking::BLOCKING_STATUSES, true)
            && $booking->car
            && ! $booking->car->isAvailableForRange(
                $booking->start_date->toDateString(),
                $booking->end_date->toDateString(),
                $booking->id,
            )) {
            return back()->withErrors([
                'status' => 'Tidak bisa mengaktifkan booking ini: mobil sudah dipesan pada rentang tanggal tersebut.',
            ]);
        }

        $booking->update(['status' => $new]);

        return back()->with('success', 'Status booking berhasil diperbarui.');
    }

    public function updateTripStatus(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'trip_status'     => ['required', Rule::in(Booking::TRIP_STATUSES)],
            'eta_manual_note' => ['nullable', 'string', 'max:100'],
        ], [
            'trip_status.required' => 'Status perjalanan wajib dipilih.',
            'trip_status.in'       => 'Status perjalanan tidak valid.',
            'eta_manual_note.max'  => 'Catatan ETA maksimal 100 karakter.',
        ]);

        // Booking yang dibatalkan tak punya perjalanan — jangan biarkan halaman
        // lacak pelanggan menampilkan progres "Selesai" untuk rental yang batal.
        if ($booking->status === 'cancelled') {
            return back()->withErrors([
                'trip_status' => 'Booking ini sudah dibatalkan, status perjalanannya tidak bisa diubah.',
            ]);
        }

        $booking->update($validated);

        return back()->with('success', 'Status perjalanan diperbarui menjadi "'.$booking->trip_status_label.'".');
    }

    public function invoice(Booking $booking): View
    {
        $booking->load('car', 'driver', 'tenant');

        return view('admin.bookings.invoice', compact('booking'));
    }

    public function emailInvoice(Booking $booking): RedirectResponse
    {
        $booking->loadMissing('tenant');

        // SMTP bisa gagal (kredensial, rate-limit Gmail, timeout). Jangan
        // lempar 500 ke admin — laporkan gagal agar bisa dicoba ulang.
        try {
            Mail::to($booking->customer_email)->send(new BookingInvoiceMail($booking));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal kirim invoice', ['booking' => $booking->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Invoice gagal dikirim (masalah server email). Silakan coba lagi nanti.');
        }

        return back()->with('success', 'Invoice telah dikirim ke '.$booking->customer_email.'.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $booking->delete();

        return redirect()->route('admin.bookings.index')->with('success', 'Booking berhasil dihapus.');
    }
}
