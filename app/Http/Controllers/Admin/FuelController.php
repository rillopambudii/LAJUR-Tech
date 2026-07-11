<?php

namespace App\Http\Controllers\Admin;

use App\Fuel\FuelService;
use App\Http\Controllers\Concerns\ParsesDateRange;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\FuelLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FuelController extends Controller
{
    use ParsesDateRange;

    public function __construct(private FuelService $fuel)
    {
    }

    public function index(Request $request): View
    {
        // Default 30 hari terakhir: BBM dipantau harian, bukan tahunan.
        [$from, $to] = $this->range($request, Carbon::today()->subDays(30));

        $carId = $request->integer('car_id') ?: null;
        $analysis = $this->fuel->analyze($from, $to, $carId);

        return view('admin.fuel.index', [
            'from' => $from,
            'to' => $to,
            'carId' => $carId,
            'cars' => Car::query()->ordered()->get(['id', 'name', 'plate_number']),
            'summaries' => $analysis['summaries'],
            'logs' => $analysis['logs'],
        ]);
    }

    public function create(): View
    {
        return view('admin.fuel.create', [
            'cars' => Car::query()->ordered()->get(['id', 'name', 'plate_number', 'tank_capacity_liters']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'filled_at' => ['required', 'date'],
            'liters' => ['required', 'numeric', 'min:0.1', 'max:999'],
            'price_per_liter' => ['required', 'integer', 'min:1'],
            'total_cost' => ['nullable', 'integer', 'min:1'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'full_tank' => ['nullable', 'boolean'],
            'station' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // exists: di atas tidak tenant-scoped; pastikan mobilnya milik tenant aktif.
        Car::query()->findOrFail($data['car_id']);

        $data['total_cost'] = $data['total_cost']
            ?? (int) round($data['liters'] * $data['price_per_liter']);
        $data['full_tank'] = $request->boolean('full_tank');
        $data['created_by'] = $request->user()->id;

        // Sengaja TIDAK menolak liter > kapasitas tangki: catatan janggal justru
        // harus terekam dan otomatis ter-flag "overfill" di analisis.
        FuelLog::create($data);

        return redirect()->route('admin.fuel.index')->with('success', 'Pengisian BBM berhasil dicatat.');
    }

    public function destroy(FuelLog $fuelLog): RedirectResponse
    {
        $fuelLog->delete();

        return redirect()->route('admin.fuel.index')->with('success', 'Catatan pengisian dihapus.');
    }
}
