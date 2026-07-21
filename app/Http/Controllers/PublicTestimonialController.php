<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicTestimonialController extends Controller
{
    public function store(Request $request, string $bookingCode): RedirectResponse
    {
        $booking = Booking::where('booking_code', strtoupper($bookingCode))->first();

        if ($booking === null) {
            return redirect()->route('tracking.search')->with('tracking_error', 'Kode booking tidak ditemukan.');
        }

        if ($booking->status !== 'completed') {
            abort(404);
        }

        if (Testimonial::where('booking_id', $booking->id)->exists()) {
            return redirect()->route('tracking.show', $bookingCode)
                ->with('testimonial_error', 'Anda sudah mengirim ulasan untuk booking ini.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'quote' => ['required', 'string', 'max:2000'],
        ]);

        try {
            Testimonial::create([
                'name' => $booking->customer_name,
                'rating' => $data['rating'],
                'quote' => $data['quote'],
                'is_published' => false,
                'sort_order' => 0,
                'booking_id' => $booking->id,
            ]);
        } catch (\Illuminate\Database\QueryException) {
            return redirect()->route('tracking.show', $bookingCode)
                ->with('testimonial_error', 'Anda sudah mengirim ulasan untuk booking ini.');
        }

        return redirect()->route('tracking.show', $bookingCode)
            ->with('testimonial_success', 'Terima kasih! Ulasan Anda sedang ditinjau tim kami.');
    }
}
