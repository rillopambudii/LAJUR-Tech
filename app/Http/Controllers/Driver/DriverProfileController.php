<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverReview;
use Illuminate\Contracts\View\View;

class DriverProfileController extends Controller
{
    public function show(): View
    {
        $driver = auth()->user();

        $completedTrips = Booking::query()
            ->where('driver_id', $driver->id)
            ->where('status', 'completed')
            ->count();

        $activeTrips = Booking::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->count();

        $avgRating = DriverReview::published()
            ->where('driver_id', $driver->id)
            ->avg('rating_overall');

        return view('driver.profile', [
            'driver' => $driver,
            'completedTrips' => $completedTrips,
            'activeTrips' => $activeTrips,
            'avgRating' => $avgRating !== null ? round((float) $avgRating, 1) : null,
        ]);
    }
}
