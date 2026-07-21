<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FuelLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Driver mencatat pengisian BBM sendiri — v2 dari [[fuel-export-navbar-2026-07-11]].
 * Prinsip anti-kebocoran tetap sama (input janggal tak diblokir, hanya ditandai);
 * yang beda dari sisi admin: (1) mobil dibatasi ke tugas yang SEDANG berjalan
 * hari ini (bukan bebas pilih), (2) foto struk wajib (penyeimbang insentif
 * skimming), (3) tidak ada akses hapus (cegah catat-lalu-tutupi-jejak).
 */
class FuelController extends Controller
{
    /** Mobil dari booking driver ini yang sedang berjalan hari ini. */
    private function ongoingCars(int $driverId): Collection
    {
        $today = Carbon::today()->toDateString();

        return Booking::query()
            ->where('driver_id', $driverId)
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->with('car:id,name,plate_number,tank_capacity_liters')
            ->get()
            ->pluck('car')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function create(): View
    {
        return view('driver.fuel-create', ['cars' => $this->ongoingCars(auth()->id())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $driver = $request->user();
        $carIds = $this->ongoingCars($driver->id)->pluck('id');

        $data = $request->validate([
            'car_id' => ['required', 'integer', Rule::in($carIds)],
            'filled_at' => ['required', 'date'],
            'liters' => ['required', 'numeric', 'min:0.1', 'max:999'],
            'price_per_liter' => ['required', 'integer', 'min:1'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'full_tank' => ['nullable', 'boolean'],
            'station' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Wajib bagi driver — beda dari form admin yang opsional.
            'receipt' => ['required', 'image', 'max:4096'],
        ], [
            'car_id.required' => 'Pilih mobil.',
            'car_id.in' => 'Mobil ini bukan tugas Anda yang sedang berjalan.',
            'liters.required' => 'Jumlah liter wajib diisi.',
            'price_per_liter.required' => 'Harga per liter wajib diisi.',
            'receipt.required' => 'Foto struk wajib dilampirkan.',
            'receipt.image' => 'Foto struk harus berupa gambar.',
            'receipt.max' => 'Ukuran foto struk maksimal 4 MB.',
        ]);

        FuelLog::create([
            'car_id' => $data['car_id'],
            'filled_at' => $data['filled_at'],
            'liters' => $data['liters'],
            'price_per_liter' => $data['price_per_liter'],
            'total_cost' => max(1, (int) round($data['liters'] * $data['price_per_liter'])),
            'odometer_km' => $data['odometer_km'] ?? null,
            'full_tank' => $request->boolean('full_tank'),
            'station' => $data['station'] ?? null,
            'notes' => $data['notes'] ?? null,
            'receipt_path' => $request->file('receipt')->store('fuel-receipts', 'public'),
            'created_by' => $driver->id,
        ]);

        return redirect()->route('driver.dashboard')->with('success', 'Pengisian BBM berhasil dicatat.');
    }
}
