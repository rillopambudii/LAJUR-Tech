<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Unit tracking page. Reads positions from vehicle_positions (populated by the
 * Traccar integration later). Until then, TRACKING_DEMO=true fabricates positions
 * so the map is demonstrable. Everything is tenant-scoped.
 */
class TrackingController extends Controller
{
    /** Map centre when there is no data (Samarinda, Kalimantan Timur). */
    private const CENTER = ['lat' => -0.502106, 'lng' => 117.153709];

    public function index(): View
    {
        return view('admin.tracking', [
            'cars' => Car::query()->ordered()->get(['id', 'name', 'plate_number']),
            'mapsKey' => config('services.google.maps_key'),
            'demo' => (bool) config('services.tracking.demo'),
            'center' => self::CENTER,
        ]);
    }

    /** Latest known position per car (for the live map). */
    public function live(): JsonResponse
    {
        $positions = Car::query()
            ->with('latestPosition')
            ->get()
            ->filter(fn (Car $car) => $car->latestPosition !== null)
            ->map(function (Car $car) {
                $p = $car->latestPosition;

                return [
                    'car_id' => $car->id,
                    'name' => $car->name,
                    'plate' => $car->plate_number,
                    'lat' => $p->latitude,
                    'lng' => $p->longitude,
                    'speed' => $p->speed,
                    'course' => $p->course,
                    'device_time' => optional($p->device_time)->toIso8601String(),
                    'minutes_ago' => $p->device_time ? (int) $p->device_time->diffInMinutes() : null,
                    'demo' => false,
                ];
            })
            ->values();

        if ($positions->isEmpty() && config('services.tracking.demo')) {
            $positions = $this->demoPositions();
        }

        return response()->json([
            'positions' => $positions,
            'demo' => (bool) ($positions->first()['demo'] ?? false),
        ]);
    }

    /** Historical track (polyline) for one car over a time range. */
    public function history(Request $request): JsonResponse
    {
        $car = Car::query()->find((int) $request->query('car'));
        if (! $car) {
            return response()->json(['car' => null, 'points' => []]);
        }

        $from = $this->parseDate($request->query('from')) ?? now()->subDay();
        $to = $this->parseDate($request->query('to')) ?? now();

        $points = $car->positions()
            ->whereBetween('device_time', [$from, $to])
            ->orderBy('device_time')
            ->get(['latitude', 'longitude', 'device_time'])
            ->map(fn ($p) => [
                'lat' => $p->latitude,
                'lng' => $p->longitude,
                'time' => optional($p->device_time)->toIso8601String(),
            ]);

        return response()->json(['car' => $car->name, 'points' => $points]);
    }

    /**
     * Fabricate one position per available car near the map centre, with a slow
     * drift so the markers look alive. Never persisted.
     */
    private function demoPositions(): Collection
    {
        return Car::query()->available()->ordered()->get()->map(function (Car $car) {
            $seed = crc32((string) $car->id);
            $latOff = (($seed % 1000) / 1000 - 0.5) * 0.08;
            $lngOff = ((intdiv($seed, 1000) % 1000) / 1000 - 0.5) * 0.08;
            $drift = sin(now()->timestamp / 60 + $car->id) * 0.0015;

            return [
                'car_id' => $car->id,
                'name' => $car->name,
                'plate' => $car->plate_number,
                'lat' => round(self::CENTER['lat'] + $latOff + $drift, 6),
                'lng' => round(self::CENTER['lng'] + $lngOff + $drift, 6),
                'speed' => ($seed % 2) ? ($seed % 61) : 0,
                'course' => $seed % 360,
                'device_time' => now()->toIso8601String(),
                'minutes_ago' => 0,
                'demo' => true,
            ];
        })->values();
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
