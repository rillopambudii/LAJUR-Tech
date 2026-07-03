<?php

namespace App\Analytics;

use App\Models\Booking;
use App\Models\Car;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tenant-scoped analytics. Every query runs through tenant-scoped models, so
 * results are automatically limited to the active tenant. This is the single
 * place business metrics are computed — reused by the reports page today and by
 * the AI assistant ("pendapatan bulan ini?") later.
 *
 * Convention: revenue and booking counts are measured by booking creation date
 * (created_at); fleet utilisation is measured by actual rental dates.
 */
class ReportService
{
    /**
     * Headline metrics for an inclusive [from, to] date range.
     *
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $fromD = $from->toDateString();
        $toD = $to->toDateString();

        $revenue = (int) Booking::query()->revenue()->createdBetween($fromD, $toD)->sum('total_price');
        $revenueBookings = (int) Booking::query()->revenue()->createdBetween($fromD, $toD)->count();
        $totalBookings = (int) Booking::query()->createdBetween($fromD, $toD)->count();

        return [
            'revenue' => $revenue,
            'bookings_total' => $totalBookings,
            'bookings_revenue' => $revenueBookings,
            'avg_value' => $revenueBookings > 0 ? intdiv($revenue, $revenueBookings) : 0,
            'status_breakdown' => $this->statusBreakdown($fromD, $toD),
            'utilization' => $this->utilization($from, $to),
        ];
    }

    /**
     * Booking counts per status within the range.
     *
     * @return array<string, int>
     */
    public function statusBreakdown(string $from, string $to): array
    {
        $counts = Booking::query()
            ->createdBetween($from, $to)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $out = [];
        foreach (Booking::STATUSES as $status) {
            $out[$status] = (int) ($counts[$status] ?? 0);
        }

        return $out;
    }

    /**
     * Fleet utilisation for the range: booked car-days ÷ (fleet size × range days),
     * as a percentage capped at 100. Uses actual rental dates.
     */
    public function utilization(Carbon $from, Carbon $to): float
    {
        $fleet = (int) Car::query()->count();
        $rangeDays = $from->diffInDays($to) + 1; // inclusive

        if ($fleet === 0 || $rangeDays === 0) {
            return 0.0;
        }

        $bookedDays = Booking::query()
            ->revenue()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->get(['start_date', 'end_date'])
            ->sum(function (Booking $b) use ($from, $to) {
                $start = $b->start_date->max($from);
                $end = $b->end_date->min($to);

                return $start->lte($end) ? $start->diffInDays($end) + 1 : 0;
            });

        return round(min(100, $bookedDays / ($fleet * $rangeDays) * 100), 1);
    }

    /**
     * Revenue per month for the last $months months (oldest first).
     *
     * @return Collection<int, array{label: string, full: string, value: int}>
     */
    public function revenueByMonth(int $months = 12): Collection
    {
        return collect(range($months - 1, 0))->map(function (int $offset) {
            $month = Carbon::now()->startOfMonth()->subMonths($offset);

            $value = (int) Booking::query()
                ->revenue()
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('total_price');

            return [
                'label' => $month->translatedFormat('M'),
                'full' => $month->translatedFormat('F Y'),
                'value' => $value,
            ];
        });
    }

    /**
     * Top cars by revenue within the range.
     *
     * @return Collection<int, object>
     */
    public function topCars(Carbon $from, Carbon $to, int $limit = 5): Collection
    {
        return Booking::query()
            ->revenue()
            ->createdBetween($from->toDateString(), $to->toDateString())
            ->selectRaw('car_name, COUNT(*) as bookings, SUM(total_price) as revenue')
            ->groupBy('car_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }
}
