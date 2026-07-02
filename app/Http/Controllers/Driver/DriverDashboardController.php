<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DriverDashboardController extends Controller
{
    public function index(): View
    {
        $driver = auth()->user();
        $today = Carbon::today()->toDateString();

        // Assignments for this driver (bookings are tenant-scoped automatically).
        $base = Booking::query()->where('driver_id', $driver->id);

        $upcoming = (clone $base)
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')
            ->get();

        $past = (clone $base)
            ->where(fn ($q) => $q->whereDate('end_date', '<', $today)
                ->orWhereIn('status', ['completed', 'cancelled']))
            ->latest('start_date')
            ->take(10)
            ->get();

        return view('driver.dashboard', compact('driver', 'upcoming', 'past'));
    }
}
