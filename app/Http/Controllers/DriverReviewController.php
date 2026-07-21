<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\DriverReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverReviewController extends Controller
{
    public function store(Request $request, string $bookingCode): RedirectResponse
    {
        $booking = Booking::where('booking_code', strtoupper($bookingCode))->first();

        if ($booking === null) {
            return redirect()->route('tracking.search')->with('tracking_error', 'Kode booking tidak ditemukan.');
        }

        if ($booking->status !== 'completed' || $booking->driver_id === null) {
            abort(404);
        }

        if (DriverReview::where('booking_id', $booking->id)->exists()) {
            return redirect()->route('tracking.show', $bookingCode)
                ->with('review_error', 'Anda sudah memberi ulasan untuk driver pada booking ini.');
        }

        $data = $request->validate([
            'rating_punctuality' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_cleanliness' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_friendliness' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_safety' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ], [], [
            'rating_punctuality' => 'ketepatan waktu',
            'rating_cleanliness' => 'kebersihan mobil',
            'rating_friendliness' => 'keramahan',
            'rating_safety' => 'keamanan berkendara',
        ]);

        $overall = round(array_sum([
            $data['rating_punctuality'], $data['rating_cleanliness'],
            $data['rating_friendliness'], $data['rating_safety'],
        ]) / 4, 1);

        try {
            DriverReview::create([
                'booking_id' => $booking->id,
                'driver_id' => $booking->driver_id,
                'rating_punctuality' => $data['rating_punctuality'],
                'rating_cleanliness' => $data['rating_cleanliness'],
                'rating_friendliness' => $data['rating_friendliness'],
                'rating_safety' => $data['rating_safety'],
                'rating_overall' => $overall,
                'comment' => $data['comment'] ?? null,
                'status' => 'pending',
            ]);
        } catch (\Illuminate\Database\QueryException) {
            // Kemungkinan kecil dua submit nyaris bersamaan lolos pengecekan exists() di atas —
            // constraint UNIQUE di kolom booking_id jadi jaring pengaman terakhir.
            return redirect()->route('tracking.show', $bookingCode)
                ->with('review_error', 'Anda sudah memberi ulasan untuk driver pada booking ini.');
        }

        return redirect()->route('tracking.show', $bookingCode)
            ->with('review_success', 'Terima kasih! Ulasan driver Anda sedang ditinjau.');
    }
}
