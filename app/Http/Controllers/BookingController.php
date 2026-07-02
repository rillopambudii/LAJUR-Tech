<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Car;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    public function store(BookingRequest $request): RedirectResponse
    {
        // Honeypot anti-bot: silently accept but discard if the trap is filled.
        if (filled($request->input('website'))) {
            return back()->with('booking_success', 'Permintaan sewa Anda telah kami terima. Tim kami akan segera menghubungi Anda.');
        }

        $data = $request->validated();

        $car = Car::query()->findOrFail($data['car_id']);

        // Reject booking for an unavailable car (FR-10 / edge case).
        if (! $car->is_available) {
            return back()
                ->withInput()
                ->withErrors(['car_id' => 'Mobil ini sedang tidak tersedia untuk disewa.'], 'booking');
        }

        // Recompute everything server-side — never trust client values (FR-15 / BR-03).
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $days = max(1, $start->diffInDays($end)); // minimum 1 day (BR-04)

        $booking = Booking::create([
            'car_id' => $car->id,
            'car_name' => $car->name,            // snapshot (NFR-13 / BR-07)
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'price_per_day' => $car->price_per_day, // snapshot
            'total_price' => $days * $car->price_per_day,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with(
            'booking_success',
            'Permintaan sewa untuk '.$booking->car_name.' berhasil dikirim! Tim kami akan segera menghubungi Anda untuk konfirmasi.'
        );
    }
}
