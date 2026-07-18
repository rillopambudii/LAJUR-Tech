<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Car;
use App\Models\FuelLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehiclePosition;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data demo BBM untuk rekaman video pemasaran: sebulan pengisian yang wajar,
 * dengan SATU baris yang sengaja memicu dua flag merah (overfill + guzzling).
 *
 * Deterministik (tanpa random) supaya rekaman ulang menghasilkan layar identik.
 * Harga seragam Rp 10.000/L (Pertalite) — sekaligus menjaga price_outlier diam,
 * karena median harga tenant tidak membedakan jenis BBM.
 *
 * ponytail: seeder sekali-pakai untuk aset pemasaran, bukan fixture test.
 */
class DemoFuelSeeder extends Seeder
{
    /** Spesifikasi armada: nama => [kapasitas tangki L, baseline km/L]. */
    private const FLEET_SPECS = [
        'All New Avanza' => [45, 12.0],
        'All New Xenia' => [45, 12.5],
        'Brio RS' => [38, 15.0],
        'Agya' => [33, 16.0],
        'Camry' => [60, 12.0],
        'Alphard' => [75, 8.0],
        'Innova Zenix' => [52, 17.0],
        'Fortuner VRZ' => [80, 11.0],
        'Pajero Sport Dakar' => [68, 10.0],
        'Triton' => [75, 10.0],
        'Hilux Double Cabin' => [80, 10.0],
    ];

    private const PRICE_PER_LITER = 10000;

    /**
     * Pengisian per mobil: [hari lalu, odometer, liter].
     * Semua isi penuh. Baris HERO Avanza (d-8): 60 L di tangki 45 L dan
     * 310 km / 60 L = 5,2 km/L vs baseline 12 → overfill + guzzling.
     */
    private const FILLS = [
        'All New Avanza' => [
            [28, 41200, 42], [24, 41680, 40], [20, 42150, 39],
            [16, 42600, 38], [12, 43080, 41], [8, 43390, 60], [4, 43850, 38],
        ],
        'All New Xenia' => [
            [27, 40200, 41], [21, 40690, 39], [15, 41180, 40],
            [9, 41660, 39], [3, 42140, 38],
        ],
        'Brio RS' => [
            [26, 30100, 34], [19, 30640, 36], [12, 31180, 35], [5, 31700, 35],
        ],
        'Agya' => [
            [25, 22400, 30], [17, 22880, 30], [9, 23360, 30], [2, 23840, 30],
        ],
    ];

    /**
     * Penyewa demo. Email sengaja wajar (bukan @example.com) karena layar ini
     * masuk video pemasaran — domain contoh langsung terbaca sebagai data karangan.
     * Nama & email dipilih agar TIDAK bentrok dengan booking asli tenant, sebab
     * daftar inilah yang dipakai sebagai penanda hapus saat seeder diulang.
     */
    private const CUSTOMERS = [
        ['Bayu Nugroho', 'bayu.nugroho@gmail.com', '081234567801'],
        ['Sari Wulandari', 'sari.wulandari@gmail.com', '081234567802'],
        ['Eko Prasetyo', 'eko.prasetyo@gmail.com', '081234567803'],
        ['Lina Marlina', 'lina.marlina@gmail.com', '081234567804'],
        ['Agus Setiawan', 'agus.setiawan@gmail.com', '081234567805'],
        ['Ratna Sari', 'ratna.sari@gmail.com', '081234567806'],
        ['Yusuf Hakim', 'yusuf.hakim@gmail.com', '081234567807'],
    ];

    /** @return list<string> */
    private static function demoEmails(): array
    {
        return array_column(self::CUSTOMERS, 1);
    }

    public function run(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        $admin = User::where('email', 'admin@lajur.id')->firstOrFail();

        // Spesifikasi armada belum pernah diisi — tanpa ini overfill & guzzling
        // mustahil menyala (keduanya dijaga null-check di FuelService).
        foreach (self::FLEET_SPECS as $name => [$tank, $baseline]) {
            Car::where('name', $name)->update([
                'tank_capacity_liters' => $tank,
                'fuel_baseline_km_per_l' => $baseline,
            ]);
        }

        FuelLog::query()->delete();
        Booking::whereIn('customer_email', self::demoEmails())->delete();

        // Titik GPS sisa data test pada mobil demo memicu gps_mismatch — flag yang
        // tidak sahih selama integrasi Traccar belum ada, dan bikin bingung di rekaman.
        VehiclePosition::whereIn('car_id', Car::whereIn('name', array_keys(self::FILLS))->pluck('id'))->delete();

        $customerIndex = 0;

        foreach (self::FILLS as $carName => $fills) {
            $car = Car::where('name', $carName)->firstOrFail();

            foreach ($fills as [$daysAgo, $odometer, $liters]) {
                $filledAt = Carbon::today()->subDays($daysAgo)->setTime(9, 30);

                // Pengisian harus jatuh dalam masa sewa, kalau tidak setiap baris
                // kena idle_fill dan layar penuh flag kuning yang bukan intinya.
                [$name, $email, $phone] = self::CUSTOMERS[$customerIndex % count(self::CUSTOMERS)];
                $customerIndex++;

                $start = $filledAt->copy()->subDay()->startOfDay();
                $end = $filledAt->copy()->addDays(2)->startOfDay();
                $days = 4;

                Booking::create([
                    'tenant_id' => $tenant->id,
                    'car_id' => $car->id,
                    'car_name' => $car->name,
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'customer_phone' => $phone,
                    'start_date' => $start,
                    'end_date' => $end,
                    'days' => $days,
                    'price_per_day' => (int) $car->price_per_day,
                    'total_price' => (int) $car->price_per_day * $days,
                    'status' => 'completed',
                    'booking_code' => Booking::generateBookingCode(),
                ]);

                FuelLog::create([
                    'tenant_id' => $tenant->id,
                    'car_id' => $car->id,
                    'filled_at' => $filledAt,
                    'liters' => $liters,
                    'price_per_liter' => self::PRICE_PER_LITER,
                    'total_cost' => $liters * self::PRICE_PER_LITER,
                    'odometer_km' => $odometer,
                    'full_tank' => true,
                    'station' => 'SPBU Pertamina',
                    'created_by' => $admin->id,
                ]);
            }
        }
    }
}
