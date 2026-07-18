<?php

namespace App\Http\Controllers\Admin;

use App\Fuel\FuelService;
use App\Http\Controllers\Concerns\ParsesDateRange;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\FuelLog;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

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

        // Mobil aktif tanpa kapasitas tangki / baseline: deteksi overfill & boros
        // dijaga null-check di FuelService, jadi diam-diam tak menyala untuk mobil
        // ini. Ditonjolkan agar owner melengkapinya, bukan mengira fitur mati.
        $carsMissingSpecs = Car::query()
            ->available()
            ->where(fn ($q) => $q->whereNull('tank_capacity_liters')->orWhereNull('fuel_baseline_km_per_l'))
            ->ordered()
            ->get(['id', 'name', 'tank_capacity_liters', 'fuel_baseline_km_per_l']);

        return view('admin.fuel.index', [
            'from' => $from,
            'to' => $to,
            'carId' => $carId,
            'cars' => Car::query()->ordered()->get(['id', 'name', 'plate_number']),
            'carsMissingSpecs' => $carsMissingSpecs,
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
            'car_id' => [
                'required', 'integer',
                // Mobil harus milik tenant aktif (idiom sama dengan assignDriver).
                Rule::exists('cars', 'id')->where('tenant_id', app(TenantManager::class)->id()),
            ],
            'filled_at' => ['required', 'date'],
            'liters' => ['required', 'numeric', 'min:0.1', 'max:999'],
            'price_per_liter' => ['required', 'integer', 'min:1'],
            'total_cost' => ['nullable', 'integer', 'min:1'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'full_tank' => ['nullable', 'boolean'],
            'station' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Selaras dengan aturan min:1 pada input manual — hasil hitung tak boleh 0.
        $data['total_cost'] = $data['total_cost']
            ?? max(1, (int) round($data['liters'] * $data['price_per_liter']));
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
