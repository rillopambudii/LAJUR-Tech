<?php

namespace App\Exports;

use App\Analytics\ReportService;
use App\Fuel\FuelService;
use App\Models\Booking;
use App\Models\Car;
use App\Models\CarMileageDaily;
use App\Models\FuelLog;
use Illuminate\Support\Carbon;

/**
 * Registry tunggal dataset operasional yang bisa diekspor (PDF/Excel).
 * Semua query lewat model ber-global-scope tenant, jadi otomatis terkurung
 * pada tenant aktif.
 */
class OperationalDatasets
{
    public function __construct(
        private ReportService $reports,
        private FuelService $fuel,
    ) {
    }

    /**
     * @return array{title: string, headings: list<string>, rows: list<array<int, mixed>>, dated: bool}|null
     *         dated=false berarti dataset snapshot (filter tanggal tidak berlaku).
     */
    public function get(string $key, Carbon $from, Carbon $to): ?array
    {
        return match ($key) {
            'bookings' => $this->bookings($from, $to),
            'cars' => $this->cars(),
            'fuel' => $this->fuelLogs($from, $to),
            'mileage' => $this->mileage($from, $to),
            'report' => $this->report($from, $to),
            default => null,
        };
    }

    /** @return array{title: string, headings: list<string>, rows: list<array<int, mixed>>, dated: bool} */
    private function bookings(Carbon $from, Carbon $to): array
    {
        $rows = [];
        Booking::query()
            ->createdBetween($from->toDateString(), $to->toDateString())
            ->with('driver')
            ->orderBy('created_at')
            ->chunk(200, function ($bookings) use (&$rows) {
                foreach ($bookings as $b) {
                    $rows[] = [
                        $b->invoiceNumber(),
                        (string) $b->booking_code,
                        optional($b->created_at)->format('Y-m-d H:i'),
                        $b->car_name,
                        $b->customer_name,
                        $b->customer_email,
                        $b->customer_phone,
                        $b->start_date->format('Y-m-d'),
                        $b->end_date->format('Y-m-d'),
                        (int) $b->days,
                        (int) $b->total_price,
                        $b->status_label,
                        $b->trip_status_label,
                        $b->driver?->name ?? '',
                    ];
                }
            });

        return [
            'title' => 'Data Booking',
            'headings' => ['Invoice', 'Kode', 'Dibuat', 'Mobil', 'Penyewa', 'Email', 'HP', 'Mulai', 'Selesai', 'Hari', 'Total (Rp)', 'Status', 'Perjalanan', 'Driver'],
            'rows' => $rows,
            'dated' => true,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<array<int, mixed>>, dated: bool} */
    private function cars(): array
    {
        $rows = Car::query()->ordered()->get()->map(fn (Car $c) => [
            $c->name,
            (string) $c->plate_number,
            $c->brand,
            $c->type,
            $c->transmission,
            $c->fuel_type,
            $c->tank_capacity_liters,
            $c->fuel_baseline_km_per_l,
            (int) $c->seats,
            (int) $c->price_per_day,
            $c->odometerKm(),
            $c->kmUntilService(),
            optional($c->tax_due_date)->format('Y-m-d'),
            optional($c->service_due_date)->format('Y-m-d'),
            $c->is_available ? 'Ya' : 'Tidak',
        ])->all();

        return [
            'title' => 'Data Armada',
            'headings' => ['Nama', 'Plat', 'Merek', 'Tipe', 'Transmisi', 'BBM', 'Tangki (L)', 'Baseline km/L', 'Kursi', 'Harga/Hari (Rp)', 'Odometer (km)', 'Sisa km Servis', 'Pajak s/d', 'Servis s/d', 'Tersedia'],
            'rows' => $rows,
            'dated' => false,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<array<int, mixed>>, dated: bool} */
    private function fuelLogs(Carbon $from, Carbon $to): array
    {
        $analysis = $this->fuel->analyze($from, $to);

        $rows = $analysis['logs']->map(fn (FuelLog $log) => [
            $log->filled_at->format('Y-m-d H:i'),
            $log->car->name,
            (string) $log->car->plate_number,
            (float) $log->liters,
            (int) $log->price_per_liter,
            (int) $log->total_cost,
            $log->odometer_km,
            $log->full_tank ? 'Ya' : 'Tidak',
            $log->segment_km_per_l,
            (string) $log->station,
            collect($log->flags)->map(fn ($f) => FuelService::FLAG_LABELS[$f])->implode('; '),
            $log->creator?->name ?? '',
            (string) $log->notes,
        ])->all();

        return [
            'title' => 'Data Pengisian BBM',
            'headings' => ['Waktu', 'Mobil', 'Plat', 'Liter', 'Harga/L (Rp)', 'Total (Rp)', 'Odometer (km)', 'Penuh', 'km/L', 'SPBU', 'Anomali', 'Pencatat', 'Catatan'],
            'rows' => $rows,
            'dated' => true,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<array<int, mixed>>, dated: bool} */
    private function mileage(Carbon $from, Carbon $to): array
    {
        $rows = CarMileageDaily::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('car:id,name,plate_number')
            ->orderBy('date')
            ->get()
            ->map(fn (CarMileageDaily $d) => [
                $d->date,
                $d->car?->name ?? '',
                (string) ($d->car?->plate_number ?? ''),
                (int) $d->km,
            ])->all();

        return [
            'title' => 'Km Harian (GPS)',
            'headings' => ['Tanggal', 'Mobil', 'Plat', 'Km'],
            'rows' => $rows,
            'dated' => true,
        ];
    }

    /** @return array{title: string, headings: list<string>, rows: list<array<int, mixed>>, dated: bool} */
    private function report(Carbon $from, Carbon $to): array
    {
        $summary = $this->reports->summary($from, $to);

        $rows = [
            ['Pendapatan (Rp)', (int) $summary['revenue']],
            ['Total booking', (int) $summary['bookings_total']],
            ['Booking menghasilkan', (int) $summary['bookings_revenue']],
            ['Rata-rata nilai booking (Rp)', (int) $summary['avg_value']],
            ['Okupansi armada (%)', (float) $summary['utilization']],
        ];

        foreach ($summary['status_breakdown'] as $status => $count) {
            $rows[] = ['Booking '.Booking::STATUS_LABELS[$status], (int) $count];
        }

        foreach ($this->reports->topCars($from, $to) as $car) {
            $rows[] = ['Top mobil: '.$car->car_name, (int) $car->revenue];
        }

        return [
            'title' => 'Ringkasan Laporan',
            'headings' => ['Indikator', 'Nilai'],
            'rows' => $rows,
            'dated' => true,
        ];
    }
}
