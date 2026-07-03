<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Car;
use App\Models\ContactMessage;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'cars_total' => Car::query()->count(),
            'cars_available' => Car::query()->available()->count(),
            'bookings_total' => Booking::query()->count(),
            'bookings_pending' => Booking::query()->where('status', 'pending')->count(),
            'testimonials' => Testimonial::query()->count(),
            'messages_unread' => ContactMessage::query()->where('is_read', false)->count(),
            'revenue' => (int) Booking::query()->where('status', 'completed')->sum('total_price'),
        ];

        // Booking counts for the last 6 months (FR-29) — rendered as a pure-CSS chart.
        $chart = collect(range(5, 0))->map(function (int $offset) {
            $month = Carbon::now()->startOfMonth()->subMonths($offset);

            $count = Booking::query()
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            return [
                'label' => $month->translatedFormat('M'),
                'full' => $month->translatedFormat('F Y'),
                'count' => $count,
            ];
        });

        $maxChart = max(1, (int) $chart->max('count'));

        $recentBookings = Booking::query()->latest()->take(8)->get();

        // Fleet reminders: cars with tax/service due (or overdue) within the window,
        // nearest due date first (sorted in PHP for DB portability).
        $reminders = Car::query()
            ->withDueReminders()
            ->get()
            ->sortBy(fn (Car $car) => collect([$car->tax_due_date, $car->service_due_date])
                ->filter()->min())
            ->take(8)
            ->values();

        return view('admin.dashboard', compact('stats', 'chart', 'maxChart', 'recentBookings', 'reminders'));
    }
}
