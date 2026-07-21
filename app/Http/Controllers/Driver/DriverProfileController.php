<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
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

        return view('driver.profile', compact('driver', 'completedTrips', 'activeTrips'));
    }
}
