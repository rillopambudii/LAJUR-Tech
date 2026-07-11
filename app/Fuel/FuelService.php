<?php

namespace App\Fuel;

use App\Mileage\MileageService;
use App\Models\Car;
use App\Models\FuelLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Analisis pengisian BBM per mobil: konsumsi nyata (metode full-to-full murni,
 * pengisian parsial diakumulasikan ke segmen), biaya per km, dan flag anomali
 * untuk mendeteksi kebocoran/penyalahgunaan.
 *
 * Tiga sumber km saling menyilang-periksa: odometer manual (diisi saat mengisi
 * BBM), km GPS (presisi antar-waktu dari titik mentah, fallback bucket harian),
 * dan jadwal booking. Semua keputusan flag memakai nilai MENTAH — pembulatan
 * hanya untuk tampilan. Lihat docs/superpowers/specs/2026-07-11-fuel-tracking-design.md.
 */
class FuelService
{
    public function __construct(private MileageService $mileage)
    {
    }

    /** Segmen lebih boros dari baseline × (1 − toleransi) → guzzling. */
    public const GUZZLING_TOLERANCE = 0.20;

    /** Hari tenggang isi BBM sekitar masa sewa (persiapan H-1 / pengembalian H+1) yang dianggap sah. */
    public const IDLE_FILL_GRACE_DAYS = 1;

    /** Selisih relatif odometer vs GPS yang dianggap mencurigakan. */
    public const GPS_MISMATCH_RATIO = 0.30;

    /** Di bawah km ini perbandingan odometer vs GPS terlalu bising untuk di-flag. */
    public const GPS_MISMATCH_MIN_KM = 30;

    /** Harga/L menyimpang lebih dari rasio ini dari median → outlier. */
    public const PRICE_OUTLIER_RATIO = 0.15;

    /** Minimal sampel pembanding sebelum harga bisa dinilai menyimpang. */
    public const PRICE_MEDIAN_MIN_LOGS = 5;

    /** Jendela hari ke belakang untuk median harga pembanding. */
    public const PRICE_MEDIAN_WINDOW_DAYS = 90;

    /** Flag merah = indikasi kuat kecurangan; sisanya kuning (perlu diperiksa). */
    public const RED_FLAGS = ['overfill', 'odometer_backwards', 'guzzling'];

    public const FLAG_LABELS = [
        'overfill' => 'Liter melebihi kapasitas tangki',
        'odometer_backwards' => 'Odometer mundur dari pengisian sebelumnya',
        'guzzling' => 'Konsumsi jauh lebih boros dari baseline',
        'gps_mismatch' => 'Km odometer tak cocok dengan km GPS',
        'idle_fill' => 'Pengisian di luar masa sewa',
        'price_outlier' => 'Harga/L menyimpang dari kebiasaan',
    ];

    /**
     * Analisis semua mobil ber-log dalam rentang [from, to].
     *
     * @return array{summaries: Collection<int, array<string, mixed>>, logs: Collection<int, FuelLog>}
     *         summaries: satu baris indikator per mobil;
     *         logs: log dalam rentang (terbaru dulu), masing-masing diberi atribut
     *         dinamis `flags` (list kode) serta `segment_km`, `segment_liters`,
     *         `segment_cost`, `segment_km_per_l` (terisi hanya pada log isi-penuh
     *         yang menutup sebuah segmen full-to-full).
     */
    public function analyze(Carbon $from, Carbon $to, ?int $carId = null): array
    {
        $cars = Car::query()
            ->when($carId, fn ($q) => $q->where('id', $carId))
            ->whereHas('fuelLogs')
            ->with([
                // Seluruh riwayat log: segmen & flag butuh log sebelum rentang.
                'fuelLogs' => fn ($q) => $q->orderBy('filled_at')->orderBy('id'),
                'fuelLogs.creator:id,name',
                'mileageDaily:id,car_id,date,km',
                'bookings' => fn ($q) => $q->where('status', '!=', 'cancelled')
                    ->select('id', 'car_id', 'start_date', 'end_date', 'status'),
            ])
            ->get();

        // Median harga dihitung dari semua log tenant (lintas mobil) — pool
        // pembanding TIDAK boleh ikut menyempit saat analisis difilter satu mobil.
        $allLogs = $carId
            ? FuelLog::query()->orderBy('filled_at')->orderBy('id')->get()
            : $cars->flatMap->fuelLogs;

        $summaries = collect();
        $rangeLogs = collect();

        foreach ($cars as $car) {
            $carLogs = $this->annotateCarLogs($car, $allLogs);
            $inRange = $carLogs->filter(
                fn (FuelLog $log) => $log->filled_at->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay())
            );

            if ($inRange->isEmpty()) {
                continue;
            }

            $summaries->push($this->summarize($car, $inRange, $from, $to));
            $rangeLogs = $rangeLogs->merge($inRange);
        }

        return [
            'summaries' => $summaries->sortByDesc('cost')->values(),
            'logs' => $rangeLogs->sortByDesc('filled_at')->values(),
        ];
    }

    /**
     * Hitung segmen full-to-full + flag anomali untuk seluruh riwayat log satu
     * mobil (urut waktu). Mengembalikan koleksi log yang sama, teranotasi.
     *
     * @param Collection<int, FuelLog> $tenantLogs semua log tenant (pembanding harga)
     * @return Collection<int, FuelLog>
     */
    private function annotateCarLogs(Car $car, Collection $tenantLogs): Collection
    {
        $logs = $car->fuelLogs->values();
        $prev = null;

        // Full-to-full murni: segmen berjalan dari isi-penuh terakhir ($anchor).
        // Isi parsial di tengah TIDAK membentuk segmen sendiri — liternya
        // diakumulasikan, karena BBM itu ikut terbakar sepanjang segmen.
        $anchor = null;
        $partialLiters = 0.0;
        $partialCost = 0;

        foreach ($logs as $log) {
            // Relasi balik tidak di-backfill Eloquent dari eager load sisi Car;
            // set manual supaya view/export tidak memicu query per baris (N+1).
            $log->setRelation('car', $car);

            $flags = [];
            $log->segment_km = null;
            $log->segment_liters = null;
            $log->segment_cost = null;
            $log->segment_km_per_l = null;

            // M1 — struk digelembungkan: liter > kapasitas tangki.
            if ($car->tank_capacity_liters && $log->liters > $car->tank_capacity_liters) {
                $flags[] = 'overfill';
            }

            if ($prev !== null) {
                // Odometer mundur & silang-periksa GPS dinilai antar log
                // BERURUTAN (terlepas penuh/parsial) — anomalinya per input.
                $kmOdoPair = null;
                if ($log->odometer_km !== null && $prev->odometer_km !== null) {
                    if ($log->odometer_km < $prev->odometer_km) {
                        $flags[] = 'odometer_backwards';
                    } else {
                        $kmOdoPair = $log->odometer_km - $prev->odometer_km;
                    }
                }

                $kmGpsPair = $this->gpsKmBetween($car, $prev->filled_at, $log->filled_at);

                // Odometer vs GPS: keduanya ada & cukup besar tapi jauh beda →
                // odometer dimainkan ATAU GPS dicabut. Dua-duanya perlu diperiksa.
                if ($kmOdoPair !== null && $kmGpsPair !== null && max($kmOdoPair, $kmGpsPair) >= self::GPS_MISMATCH_MIN_KM
                    && abs($kmOdoPair - $kmGpsPair) / max($kmOdoPair, $kmGpsPair) > self::GPS_MISMATCH_RATIO) {
                    $flags[] = 'gps_mismatch';
                }
            }

            if ($anchor !== null && $log->full_tank) {
                // Km segmen: delta odometer anchor→sekarang; delta 0 (macet /
                // salah ketik ulang) tidak dipercaya — jatuh ke km GPS.
                $kmOdo = null;
                if ($log->odometer_km !== null && $anchor->odometer_km !== null && $log->odometer_km > $anchor->odometer_km) {
                    $kmOdo = (float) ($log->odometer_km - $anchor->odometer_km);
                }
                $km = $kmOdo ?? $this->gpsKmBetween($car, $anchor->filled_at, $log->filled_at);

                // Liter segmen = isi penuh ini + semua isi parsial sejak anchor.
                $liters = (float) $log->liters + $partialLiters;

                if ($km !== null && $km > 0 && $liters > 0) {
                    $rawKmPerLiter = $km / $liters;

                    $log->segment_km = round($km, 1);
                    $log->segment_liters = round($liters, 2);
                    $log->segment_cost = (int) $log->total_cost + $partialCost;
                    $log->segment_km_per_l = round($rawKmPerLiter, 1);

                    // M2/M3 — BBM hilang: efisiensi anjlok jauh di bawah baseline.
                    // Bandingkan nilai MENTAH, bukan yang sudah dibulatkan.
                    $baseline = (float) $car->fuel_baseline_km_per_l;
                    if ($baseline > 0 && $rawKmPerLiter < $baseline * (1 - self::GUZZLING_TOLERANCE)) {
                        $flags[] = 'guzzling';
                    }
                }
            } elseif ($anchor !== null && ! $log->full_tank) {
                $partialLiters += (float) $log->liters;
                $partialCost += (int) $log->total_cost;
            }

            if ($log->full_tank) {
                $anchor = $log;
                $partialLiters = 0.0;
                $partialCost = 0;
            }

            // M3 — pengisian di luar masa sewa (dengan hari tenggang persiapan/
            // pengembalian agar isi H-1/H+1 yang sah tidak ter-flag palsu).
            if (! $this->withinAnyBooking($car, $log->filled_at)) {
                $flags[] = 'idle_fill';
            }

            // M4 — markup harga: menyimpang dari median tenant 90 hari terakhir.
            if ($this->isPriceOutlier($log, $tenantLogs)) {
                $flags[] = 'price_outlier';
            }

            $log->flags = $flags;
            $prev = $log;
        }

        return $logs;
    }

    /**
     * Indikator agregat satu mobil dari log dalam rentang.
     *
     * @param Collection<int, FuelLog> $logs log teranotasi dalam rentang, urut waktu
     * @return array<string, mixed>
     */
    private function summarize(Car $car, Collection $logs, Carbon $from, Carbon $to): array
    {
        $liters = (float) $logs->sum('liters');
        $cost = (int) $logs->sum('total_cost');

        // Agregat = Σkm ÷ Σliter dari segmen valid (bukan rata-rata rasio),
        // supaya segmen panjang berbobot benar. Liter/biaya segmen sudah
        // termasuk isi parsial yang terbakar di dalamnya (full-to-full murni).
        $segments = $logs->whereNotNull('segment_km');
        $segmentKm = (float) $segments->sum('segment_km');
        $segmentLiters = (float) $segments->sum('segment_liters');
        $segmentCost = (int) $segments->sum('segment_cost');

        // Deviasi dihitung dari nilai MENTAH; pembulatan hanya untuk tampilan.
        $rawKmPerLiter = $segmentLiters > 0 ? $segmentKm / $segmentLiters : null;
        $kmPerLiter = $rawKmPerLiter !== null ? round($rawKmPerLiter, 1) : null;
        $baseline = (float) $car->fuel_baseline_km_per_l ?: null;

        // Deviasi: positif = lebih boros dari baseline.
        $deviationPct = ($rawKmPerLiter !== null && $baseline)
            ? (int) round(($baseline - $rawKmPerLiter) / $baseline * 100)
            : null;

        // Silang-periksa periode: Δodometer vs total km GPS.
        $withOdo = $logs->whereNotNull('odometer_km');
        $odoDelta = $withOdo->count() >= 2
            ? (int) $withOdo->max('odometer_km') - (int) $withOdo->min('odometer_km')
            : null;
        $gpsKm = $this->gpsKmInRange($car, $from, $to);
        $gpsGapPct = null;
        if ($odoDelta !== null && $gpsKm !== null && max($odoDelta, $gpsKm) >= self::GPS_MISMATCH_MIN_KM) {
            $gpsGapPct = (int) round(abs($odoDelta - $gpsKm) / max($odoDelta, $gpsKm) * 100);
        }

        $allFlags = $logs->flatMap(fn (FuelLog $log) => $log->flags);

        return [
            'car' => $car,
            'fills' => $logs->count(),
            'liters' => round($liters, 1),
            'cost' => $cost,
            'km' => (int) round($segmentKm),
            'km_per_liter' => $kmPerLiter,
            'baseline' => $baseline,
            'deviation_pct' => $deviationPct,
            'cost_per_km' => $segmentKm > 0 ? (int) round($segmentCost / $segmentKm) : null,
            'odo_delta_km' => $odoDelta,
            'gps_km' => $gpsKm,
            'gps_gap_pct' => $gpsGapPct,
            'red_flags' => $allFlags->filter(fn (string $f) => in_array($f, self::RED_FLAGS, true))->count(),
            'yellow_flags' => $allFlags->reject(fn (string $f) => in_array($f, self::RED_FLAGS, true))->count(),
        ];
    }

    /**
     * Km GPS antara dua waktu pengisian. Prioritas: jarak PRESISI dari titik
     * GPS mentah antar jam pengisian (akurat sampai menit, bisa dua pengisian
     * sehari); fallback: bucket harian car_mileage_daily pada (prev, now].
     */
    private function gpsKmBetween(Car $car, Carbon $prevAt, Carbon $nowAt): ?float
    {
        $precise = $this->mileage->kmBetween($car, $prevAt, $nowAt);
        if ($precise !== null) {
            return $precise;
        }

        $daily = $this->gpsKmSum($car, $prevAt->toDateString(), $nowAt->toDateString(), excludeFromDate: true);

        return $daily !== null ? (float) $daily : null;
    }

    /** Km GPS total dalam rentang [from, to] — null bila tidak ada data GPS. */
    private function gpsKmInRange(Car $car, Carbon $from, Carbon $to): ?int
    {
        return $this->gpsKmSum($car, $from->toDateString(), $to->toDateString());
    }

    /** Satu-satunya implementasi penjumlahan km GPS harian pada jendela tanggal. */
    private function gpsKmSum(Car $car, string $fromDate, string $toDate, bool $excludeFromDate = false): ?int
    {
        $rows = $car->mileageDaily->filter(
            fn ($d) => ($excludeFromDate ? $d->date > $fromDate : $d->date >= $fromDate) && $d->date <= $toDate
        );

        return $rows->isEmpty() ? null : (int) $rows->sum('km');
    }

    /**
     * Apakah tanggal pengisian jatuh dalam masa sewa booking (non-batal) mobil
     * ini, dengan tenggang IDLE_FILL_GRACE_DAYS di kedua sisi: isi H-1
     * (persiapan) dan H+1 (setelah pengembalian) adalah operasional yang sah.
     */
    private function withinAnyBooking(Car $car, Carbon $filledAt): bool
    {
        $date = $filledAt->toDateString();

        return $car->bookings->contains(function ($b) use ($date) {
            $start = $b->start_date->copy()->subDays(self::IDLE_FILL_GRACE_DAYS)->toDateString();
            $end = $b->end_date->copy()->addDays(self::IDLE_FILL_GRACE_DAYS)->toDateString();

            return $start <= $date && $end >= $date;
        });
    }

    /**
     * Harga/L menyimpang > PRICE_OUTLIER_RATIO dari median log tenant dalam
     * PRICE_MEDIAN_WINDOW_DAYS hari sebelum log ini (butuh minimal
     * PRICE_MEDIAN_MIN_LOGS sampel pembanding).
     *
     * @param Collection<int, FuelLog> $tenantLogs
     */
    private function isPriceOutlier(FuelLog $log, Collection $tenantLogs): bool
    {
        $windowStart = $log->filled_at->copy()->subDays(self::PRICE_MEDIAN_WINDOW_DAYS);

        $samples = $tenantLogs
            ->filter(fn (FuelLog $other) => $other->id !== $log->id
                && $other->filled_at->lte($log->filled_at)
                && $other->filled_at->gte($windowStart))
            ->pluck('price_per_liter')
            ->sort()
            ->values();

        if ($samples->count() < self::PRICE_MEDIAN_MIN_LOGS) {
            return false;
        }

        $median = (float) $samples->median();

        return $median > 0
            && abs($log->price_per_liter - $median) / $median > self::PRICE_OUTLIER_RATIO;
    }
}
