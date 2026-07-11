<?php

namespace App\Mileage;

use App\Models\Car;
use App\Models\CarMileageDaily;
use Illuminate\Support\Carbon;

class MileageService
{
    public const MIN_SEGMENT_METERS = 15;
    public const MAX_SEGMENT_METERS = 8000;

    /**
     * Km presisi antara dua waktu dari titik GPS mentah (filter jitter/teleport
     * sama dengan syncCar). Null bila titiknya kurang dari 2 atau tak bergerak —
     * pemakai diharapkan fallback ke bucket harian car_mileage_daily.
     */
    public function kmBetween(Car $car, Carbon $from, Carbon $to): ?float
    {
        $positions = $car->positions()
            ->whereBetween('device_time', [$from, $to])
            ->orderBy('device_time')
            ->get(['latitude', 'longitude', 'device_time']);

        if ($positions->count() < 2) {
            return null;
        }

        $meters = 0.0;
        $prev = null;
        foreach ($positions as $p) {
            if ($prev !== null) {
                $d = $this->haversine((float) $prev->latitude, (float) $prev->longitude, (float) $p->latitude, (float) $p->longitude);
                if ($d >= self::MIN_SEGMENT_METERS && $d <= self::MAX_SEGMENT_METERS) {
                    $meters += $d;
                }
            }
            $prev = $p;
        }

        return $meters > 0 ? $meters / 1000 : null;
    }

    /** Recompute daily mileage buckets for one car from all its positions. */
    public function syncCar(Car $car): void
    {
        $positions = $car->positions()
            ->orderBy('device_time')
            ->get(['latitude', 'longitude', 'device_time']);

        $buckets = []; // 'Y-m-d' => meters
        $prev = null;
        foreach ($positions as $p) {
            if ($prev !== null) {
                $d = $this->haversine((float) $prev->latitude, (float) $prev->longitude, (float) $p->latitude, (float) $p->longitude);
                if ($d >= self::MIN_SEGMENT_METERS && $d <= self::MAX_SEGMENT_METERS) {
                    $date = optional($p->device_time)->toDateString();
                    if ($date) {
                        $buckets[$date] = ($buckets[$date] ?? 0) + $d;
                    }
                }
            }
            $prev = $p;
        }

        foreach ($buckets as $date => $meters) {
            CarMileageDaily::updateOrCreate(
                ['car_id' => $car->id, 'date' => $date],
                ['tenant_id' => $car->tenant_id, 'km' => (int) round($meters / 1000)]
            );
        }

        $car->forceFill(['mileage_synced_at' => now()])->save();
    }

    /** Sync all cars of the active tenant. Returns the number synced. */
    public function syncAll(): int
    {
        $cars = Car::query()->get();
        foreach ($cars as $car) {
            $this->syncCar($car);
        }

        return $cars->count();
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(sqrt($a));
    }
}
