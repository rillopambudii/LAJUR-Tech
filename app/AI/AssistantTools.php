<?php

namespace App\AI;

use App\Analytics\ReportService;
use App\Models\Booking;
use App\Models\Car;
use Illuminate\Support\Carbon;

/**
 * The *only* things the AI assistant can do. Each tool maps to a read-only,
 * tenant-scoped query (via ReportService / Eloquent). Claude picks a tool and
 * arguments; this class runs the query. Claude never sees or writes SQL, so
 * there is no path to touch another tenant's data or mutate anything.
 */
class AssistantTools
{
    public function __construct(private ReportService $reports)
    {
    }

    /**
     * JSON-schema tool definitions sent to the Claude API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        $dateRange = [
            'from' => ['type' => 'string', 'description' => 'Tanggal awal (YYYY-MM-DD). Kosongkan untuk awal bulan ini.'],
            'to' => ['type' => 'string', 'description' => 'Tanggal akhir (YYYY-MM-DD). Kosongkan untuk hari ini.'],
        ];

        return [
            [
                'name' => 'business_summary',
                'description' => 'Ringkasan bisnis pada rentang tanggal: total pendapatan (booking confirmed+selesai), jumlah booking, rata-rata nilai booking, okupansi armada, dan rincian booking per status. Gunakan untuk pertanyaan seperti "pendapatan bulan ini", "berapa booking minggu ini".',
                'input_schema' => ['type' => 'object', 'properties' => $dateRange, 'required' => []],
            ],
            [
                'name' => 'revenue_trend',
                'description' => 'Pendapatan per bulan untuk beberapa bulan terakhir. Gunakan untuk pertanyaan tentang tren/grafik pendapatan.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'months' => ['type' => 'integer', 'description' => 'Jumlah bulan ke belakang (1-24). Default 6.'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'top_cars',
                'description' => 'Mobil dengan pendapatan tertinggi pada rentang tanggal. Gunakan untuk "mobil terlaris", "mobil paling menghasilkan".',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => $dateRange + [
                        'limit' => ['type' => 'integer', 'description' => 'Jumlah mobil (1-10). Default 5.'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'fleet_status',
                'description' => 'Status armada saat ini: jumlah total mobil, mobil tersedia, dan booking yang masih menunggu (pending).',
                'input_schema' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
        ];
    }

    /**
     * Execute a tool by name with Claude-supplied arguments. Returns a plain
     * array that is JSON-encoded back to Claude as the tool result.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function run(string $name, array $input): array
    {
        return match ($name) {
            'business_summary' => $this->businessSummary($input),
            'revenue_trend' => $this->revenueTrend($input),
            'top_cars' => $this->topCars($input),
            'fleet_status' => $this->fleetStatus(),
            default => ['error' => "Tool tidak dikenal: {$name}"],
        };
    }

    /** @param array<string, mixed> $input */
    private function businessSummary(array $input): array
    {
        [$from, $to] = $this->range($input);
        $s = $this->reports->summary($from, $to);

        return [
            'periode' => $from->toDateString().' s/d '.$to->toDateString(),
            'pendapatan' => $s['revenue'],
            'total_booking' => $s['bookings_total'],
            'booking_pendapatan' => $s['bookings_revenue'],
            'rata_rata_nilai_booking' => $s['avg_value'],
            'okupansi_armada_persen' => $s['utilization'],
            'booking_per_status' => $s['status_breakdown'],
        ];
    }

    /** @param array<string, mixed> $input */
    private function revenueTrend(array $input): array
    {
        $months = (int) ($input['months'] ?? 6);
        $months = max(1, min(24, $months));

        return [
            'pendapatan_per_bulan' => $this->reports->revenueByMonth($months)
                ->map(fn ($m) => ['bulan' => $m['full'], 'pendapatan' => $m['value']])
                ->all(),
        ];
    }

    /** @param array<string, mixed> $input */
    private function topCars(array $input): array
    {
        [$from, $to] = $this->range($input);
        $limit = max(1, min(10, (int) ($input['limit'] ?? 5)));

        return [
            'periode' => $from->toDateString().' s/d '.$to->toDateString(),
            'mobil_terlaris' => $this->reports->topCars($from, $to, $limit)
                ->map(fn ($c) => ['mobil' => $c->car_name, 'booking' => (int) $c->bookings, 'pendapatan' => (int) $c->revenue])
                ->all(),
        ];
    }

    private function fleetStatus(): array
    {
        return [
            'total_mobil' => Car::query()->count(),
            'mobil_tersedia' => Car::query()->available()->count(),
            'booking_pending' => Booking::query()->where('status', 'pending')->count(),
        ];
    }

    /**
     * Resolve from/to inputs, defaulting to the start of the current month → today.
     *
     * @param array<string, mixed> $input
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(array $input): array
    {
        $from = $this->parse($input['from'] ?? null) ?? Carbon::today()->startOfMonth();
        $to = $this->parse($input['to'] ?? null) ?? Carbon::today();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    private function parse(?string $value): ?Carbon
    {
        if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
