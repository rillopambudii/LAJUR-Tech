<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public order-tracking ("Lacak Pesanan"). Customers reach a booking through its
 * unguessable public code — no login. Lookups stay tenant-scoped by the global
 * scope (default tenant on the public site), which doubles as a safety boundary.
 */
class TrackingController extends Controller
{
    public function show(string $bookingCode): View|RedirectResponse
    {
        $booking = Booking::query()
            ->with('car.latestPosition')
            ->where('booking_code', strtoupper($bookingCode))
            ->first();

        if ($booking === null) {
            return redirect()
                ->route('tracking.search')
                ->with('tracking_error', 'Kode booking tidak ditemukan. Coba cek kembali kodenya.');
        }

        return view('tracking.show', [
            'booking' => $booking,
            'demo' => (bool) config('services.tracking.demo'),
        ]);
    }

    /**
     * Family view ("Pantau Perjalanan"). Same unguessable code as /lacak, but a
     * stripped read-only page a customer shares with family: live map + status +
     * ETA + car/driver, and deliberately NO price/financial detail.
     */
    public function watch(string $bookingCode): View|RedirectResponse
    {
        $booking = Booking::query()
            ->with('car.latestPosition', 'driver')
            ->where('booking_code', strtoupper($bookingCode))
            ->first();

        if ($booking === null) {
            return redirect()
                ->route('tracking.search')
                ->with('tracking_error', 'Kode booking tidak ditemukan. Coba cek kembali kodenya.');
        }

        return view('tracking.watch', [
            'booking' => $booking,
            'demo' => (bool) config('services.tracking.demo'),
        ]);
    }

    public function search(): View
    {
        return view('tracking.search');
    }

    public function find(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_code'   => ['required', 'string', 'max:20'],
            'customer_phone' => ['required', 'string', 'max:30'],
        ], [
            'required' => ':attribute wajib diisi.',
        ], [
            'booking_code'   => 'kode booking',
            'customer_phone' => 'nomor HP',
        ]);

        // Require BOTH the code AND the phone to match — a code alone must not
        // reveal someone else's booking.
        $booking = Booking::query()
            ->where('booking_code', strtoupper($data['booking_code']))
            ->where('customer_phone', $data['customer_phone'])
            ->first();

        if ($booking === null) {
            return back()
                ->withInput()
                ->with('tracking_error', 'Kombinasi kode booking dan nomor HP tidak cocok. Periksa kembali.');
        }

        return redirect()->route('tracking.show', $booking->booking_code);
    }
}
