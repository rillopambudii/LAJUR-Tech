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
            [
                'name' => 'check_availability',
                'description' => 'Cek apakah sebuah mobil masih tersedia (belum dibooking) untuk rentang tanggal. Gunakan untuk "apakah [mobil] tersedia tanggal ...".',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'car' => ['type' => 'string', 'description' => 'Nama atau merek mobil (boleh sebagian, mis. "Avanza").'],
                    'start' => ['type' => 'string', 'description' => 'Tanggal mulai (YYYY-MM-DD).'],
                    'end' => ['type' => 'string', 'description' => 'Tanggal selesai (YYYY-MM-DD).'],
                ], 'required' => ['car', 'start', 'end']],
            ],
            [
                'name' => 'list_pending_bookings',
                'description' => 'Daftar booking yang masih menunggu konfirmasi (status pending), terbaru dulu. Gunakan untuk "booking pending siapa saja / ada berapa".',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'limit' => ['type' => 'integer', 'description' => 'Maks jumlah baris (1-20). Default 10.'],
                ], 'required' => []],
            ],
            [
                'name' => 'fleet_reminders',
                'description' => 'Mobil yang pajak (STNK) atau servisnya sudah lewat atau jatuh tempo dalam 30 hari ke depan.',
                'input_schema' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'compare_revenue',
                'description' => 'Bandingkan pendapatan dua periode dan hitung selisih + persentase. Gunakan untuk "bandingkan bulan ini vs bulan lalu".',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'current_from' => ['type' => 'string', 'description' => 'Periode sekarang: tanggal awal (YYYY-MM-DD).'],
                    'current_to' => ['type' => 'string', 'description' => 'Periode sekarang: tanggal akhir (YYYY-MM-DD).'],
                    'previous_from' => ['type' => 'string', 'description' => 'Periode pembanding: tanggal awal (YYYY-MM-DD).'],
                    'previous_to' => ['type' => 'string', 'description' => 'Periode pembanding: tanggal akhir (YYYY-MM-DD).'],
                ], 'required' => ['current_from', 'current_to', 'previous_from', 'previous_to']],
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
            'check_availability' => $this->checkAvailability($input),
            'list_pending_bookings' => $this->listPendingBookings($input),
            'fleet_reminders' => $this->fleetReminders(),
            'compare_revenue' => $this->compareRevenue($input),
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

    /** @param array<string, mixed> $input */
    private function checkAvailability(array $input): array
    {
        $name = trim((string) ($input['car'] ?? ''));
        $start = $this->parse($input['start'] ?? null);
        $end = $this->parse($input['end'] ?? null);

        if ($name === '' || ! $start || ! $end) {
            return ['error' => 'Butuh nama mobil serta tanggal mulai & selesai (format YYYY-MM-DD).'];
        }
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        // Grouped where keeps the tenant global scope intact.
        $car = Car::query()
            ->where(fn ($q) => $q->where('name', 'like', "%{$name}%")->orWhere('brand', 'like', "%{$name}%"))
            ->first();

        if (! $car) {
            return ['error' => "Mobil \"{$name}\" tidak ditemukan."];
        }

        return [
            'mobil' => $car->name,
            'periode' => $start->toDateString().' s/d '.$end->toDateString(),
            'tersedia' => $car->isAvailableForRange($start->toDateString(), $end->toDateString()),
            'aktif_untuk_disewa' => (bool) $car->is_available,
        ];
    }

    /** @param array<string, mixed> $input */
    private function listPendingBookings(array $input): array
    {
        $limit = max(1, min(20, (int) ($input['limit'] ?? 10)));

        $bookings = Booking::query()->where('status', 'pending')->latest()->take($limit)->get();

        return [
            'total_pending' => Booking::query()->where('status', 'pending')->count(),
            'daftar' => $bookings->map(fn (Booking $b) => [
                'penyewa' => $b->customer_name,
                'mobil' => $b->car_name,
                'periode' => $b->start_date->toDateString().' s/d '.$b->end_date->toDateString(),
                'total' => (int) $b->total_price,
            ])->all(),
        ];
    }

    private function fleetReminders(): array
    {
        return [
            'pengingat' => Car::query()->withDueReminders()->get()->map(fn (Car $c) => [
                'mobil' => $c->name,
                'plat' => $c->plate_number,
                'pajak_jatuh_tempo' => optional($c->tax_due_date)->toDateString(),
                'status_pajak' => $c->taxStatus(),      // overdue / soon / ok
                'servis_jatuh_tempo' => optional($c->service_due_date)->toDateString(),
                'status_servis' => $c->serviceStatus(),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $input */
    private function compareRevenue(array $input): array
    {
        $cf = $this->parse($input['current_from'] ?? null);
        $ct = $this->parse($input['current_to'] ?? null);
        $pf = $this->parse($input['previous_from'] ?? null);
        $pt = $this->parse($input['previous_to'] ?? null);

        if (! $cf || ! $ct || ! $pf || ! $pt) {
            return ['error' => 'Butuh empat tanggal (YYYY-MM-DD): current_from, current_to, previous_from, previous_to.'];
        }

        $current = $this->reports->summary($cf->startOfDay(), $ct->startOfDay())['revenue'];
        $previous = $this->reports->summary($pf->startOfDay(), $pt->startOfDay())['revenue'];
        $delta = $current - $previous;

        return [
            'periode_sekarang' => $cf->toDateString().' s/d '.$ct->toDateString(),
            'pendapatan_sekarang' => $current,
            'periode_pembanding' => $pf->toDateString().' s/d '.$pt->toDateString(),
            'pendapatan_pembanding' => $previous,
            'selisih' => $delta,
            'persen_perubahan' => $previous > 0 ? round($delta / $previous * 100, 1) : null,
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
