<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Car;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    /**
     * Fleet availability calendar: cars as rows, days of the month as columns,
     * cells shaded where an active (pending/confirmed) booking reserves the car.
     */
    public function index(Request $request): View
    {
        // Resolve the requested month (?month=YYYY-MM), default to the current month.
        $month = $this->resolveMonth($request->query('month'));
        $monthEnd = $month->copy()->endOfMonth();
        $daysInMonth = $month->daysInMonth;

        $cars = Car::query()->ordered()->get();

        // Active bookings that touch this month, grouped by car.
        $bookings = Booking::query()
            ->active()
            ->overlapping($month->toDateString(), $monthEnd->toDateString())
            ->get()
            ->groupBy('car_id');

        // Build per-car map: day-of-month => booking covering that day.
        $grid = [];
        foreach ($cars as $car) {
            $days = [];
            foreach ($bookings->get($car->id, collect()) as $booking) {
                $from = Carbon::parse($booking->start_date)->max($month);
                $to = Carbon::parse($booking->end_date)->min($monthEnd);

                for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                    $days[(int) $d->day] = $booking;
                }
            }
            $grid[$car->id] = $days;
        }

        return view('admin.calendar', [
            'month' => $month,
            'daysInMonth' => $daysInMonth,
            'cars' => $cars,
            'grid' => $grid,
            'monthLabel' => $month->translatedFormat('F Y'),
            'prev' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
            'today' => Carbon::today(),
        ]);
    }

    private function resolveMonth(?string $value): Carbon
    {
        if ($value && preg_match('/^\d{4}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
            } catch (\Throwable) {
                // fall through to default
            }
        }

        return Carbon::now()->startOfMonth();
    }
}
