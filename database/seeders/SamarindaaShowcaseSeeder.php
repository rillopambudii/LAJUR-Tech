<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Isi tenant "samarindaa" (ucupadhy) dengan data lengkap: armada, driver,
 * booking (riwayat utk pendapatan + aktif utk kalender), dan testimoni —
 * supaya dashboard & etalasenya penuh, tidak kosong seperti tenant baru.
 *
 * Deterministik + idempoten (hapus data lama tenant ini lalu isi ulang).
 * ponytail: seeder showcase sekali-pakai, bukan fixture test.
 */
class SamarindaaShowcaseSeeder extends Seeder
{
    private const SLUG = 'samarindaa';

    /** [nama, merek, tipe, transmisi, bbm, kursi, tarif/hari, tangki, baseline km/L, unggulan] */
    private const FLEET = [
        ['Toyota Avanza', 'Toyota', 'MPV', 'Manual', 'Bensin', 7, 350000, 45, 12.0, true],
        ['Daihatsu Xenia', 'Daihatsu', 'MPV', 'Manual', 'Bensin', 7, 330000, 45, 12.5, false],
        ['Toyota Innova Reborn', 'Toyota', 'MPV', 'Automatic', 'Diesel', 7, 550000, 55, 11.0, true],
        ['Honda Brio RS', 'Honda', 'Hatchback', 'Automatic', 'Bensin', 5, 300000, 35, 18.0, false],
        ['Suzuki Ertiga', 'Suzuki', 'MPV', 'Manual', 'Bensin', 7, 340000, 45, 13.0, false],
        ['Toyota Fortuner VRZ', 'Toyota', 'SUV', 'Automatic', 'Diesel', 7, 900000, 80, 10.0, true],
        ['Mitsubishi Pajero Sport', 'Mitsubishi', 'SUV', 'Automatic', 'Diesel', 7, 950000, 68, 10.5, false],
        ['Toyota Alphard', 'Toyota', 'Luxury', 'Automatic', 'Bensin', 7, 1800000, 75, 8.0, true],
    ];

    private const DRIVERS = [
        ['Rahmat Hidayat', 'rahmat.driver@samarindaa.id', '081350010001'],
        ['Joko Santoso', 'joko.driver@samarindaa.id', '081350010002'],
        ['Andi Kurniawan', 'andi.driver@samarindaa.id', '081350010003'],
    ];

    private const CUSTOMERS = [
        ['Budi Prasetyo', 'budi.p@gmail.com', '081340020001'],
        ['Sari Mahardika', 'sari.m@gmail.com', '081340020002'],
        ['Eko Wahyudi', 'eko.w@gmail.com', '081340020003'],
        ['Ratna Kusuma', 'ratna.k@gmail.com', '081340020004'],
        ['Dedi Firmansyah', 'dedi.f@gmail.com', '081340020005'],
        ['Wulan Anggraini', 'wulan.a@gmail.com', '081340020006'],
        ['Hendra Gunawan', 'hendra.g@gmail.com', '081340020007'],
        ['Lisa Permata', 'lisa.p@gmail.com', '081340020008'],
    ];

    private const TESTIMONIALS = [
        ['Budi Prasetyo', 'Pelanggan Dinas', 5, 'Mobil bersih dan terawat, proses cepat. Sudah langganan buat perjalanan dinas ke Balikpapan.'],
        ['Sari Mahardika', 'Liburan Keluarga', 5, 'Innova-nya nyaman buat keluarga besar. Driver ramah dan tahu jalan. Recommended!'],
        ['Eko Wahyudi', 'Pengusaha', 4, 'Harga transparan, tidak ada biaya tersembunyi. Fortuner-nya mantap buat medan Kalimantan.'],
        ['Ratna Kusuma', 'Acara Pernikahan', 5, 'Sewa Alphard buat acara nikahan, semuanya mulus. Terima kasih banyak!'],
        ['Dedi Firmansyah', 'Karyawan Swasta', 5, 'Booking gampang, konfirmasi cepat lewat WhatsApp. Armada banyak pilihan.'],
        ['Wulan Anggraini', 'Ibu Rumah Tangga', 4, 'Avanza-nya irit dan bersih. Cocok buat antar anak dan belanja bulanan.'],
    ];

    public function run(): void
    {
        $tenant = Tenant::where('slug', self::SLUG)->firstOrFail();
        app(TenantManager::class)->set($tenant);

        // Bersihkan data showcase sebelumnya milik tenant ini (idempoten).
        Booking::query()->delete();
        Car::query()->delete();
        Testimonial::query()->delete();
        User::where('tenant_id', $tenant->id)->where('role', User::ROLE_DRIVER)->delete();

        // --- Driver ---
        foreach (self::DRIVERS as [$name, $email, $phone]) {
            User::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make('rahasia123'),
                'role' => User::ROLE_DRIVER,
            ]);
        }

        // --- Armada ---
        $cars = [];
        foreach (self::FLEET as $i => [$name, $brand, $type, $trans, $fuel, $seats, $price, $tank, $base, $featured]) {
            $cars[] = Car::create([
                'name' => $name,
                'plate_number' => 'KT '.(1200 + $i * 37).' '.chr(65 + $i).'B',
                'brand' => $brand,
                'type' => $type,
                'transmission' => $trans,
                'fuel_type' => $fuel,
                'seats' => $seats,
                'price_per_day' => $price,
                'tank_capacity_liters' => $tank,
                'fuel_baseline_km_per_l' => $base,
                'is_available' => true,
                'is_featured' => $featured,
                'sort_order' => $i,
                'description' => $brand.' '.$type.' terawat, siap antar untuk kebutuhan perjalanan Anda di Samarinda dan sekitarnya.',
            ]);
        }

        $this->makeBookings($tenant, $cars);
        $this->makeTestimonials();
    }

    /**
     * Booking: riwayat SELESAI beberapa bulan ke belakang (mengisi pendapatan &
     * grafik) + booking AKTIF (confirmed/pending) di bulan berjalan (mengisi
     * kalender). Non-tumpang-tindih per mobil.
     *
     * @param  array<int, Car>  $cars
     */
    private function makeBookings(Tenant $tenant, array $cars): void
    {
        $c = 0; // indeks pelanggan berputar
        $renter = fn () => self::CUSTOMERS[$c++ % count(self::CUSTOMERS)];

        // Riwayat selesai: 3 bulan ke belakang, ~2 booking per mobil per bulan.
        foreach ($cars as $ci => $car) {
            foreach ([1, 2, 3] as $monthsAgo) {
                $startDay = 3 + (($ci + $monthsAgo) * 5) % 20;
                $len = 2 + (($ci + $monthsAgo) % 4);
                $start = Carbon::now()->subMonths($monthsAgo)->startOfMonth()->addDays($startDay - 1);
                $this->booking($tenant, $car, $renter(), $start, $len, 'completed');
            }
        }

        // Aktif bulan ini: sebagian confirmed (hijau), sebagian pending (kuning).
        $monthStart = Carbon::now()->startOfMonth();
        $lastDay = $monthStart->daysInMonth;
        foreach ($cars as $ci => $car) {
            foreach ([[1 + ($ci * 3) % 12, 3], [17 + ($ci * 2) % 9, 2]] as $b => [$startDay, $len]) {
                if ($startDay + $len - 1 > $lastDay) {
                    continue;
                }
                $status = (($ci + $b) % 2 === 0) ? 'confirmed' : 'pending';
                $this->booking($tenant, $car, $renter(), $monthStart->copy()->addDays($startDay - 1), $len, $status);
            }
        }
    }

    /** @param array{0:string,1:string,2:string} $renter */
    private function booking(Tenant $tenant, Car $car, array $renter, Carbon $start, int $len, string $status): void
    {
        [$name, $email, $phone] = $renter;
        Booking::create([
            'tenant_id' => $tenant->id,
            'car_id' => $car->id,
            'car_name' => $car->name,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'start_date' => $start->copy()->startOfDay(),
            'end_date' => $start->copy()->addDays($len - 1)->startOfDay(),
            'days' => $len,
            'price_per_day' => (int) $car->price_per_day,
            'total_price' => (int) $car->price_per_day * $len,
            'status' => $status,
            'booking_code' => Booking::generateBookingCode(),
        ]);
    }

    private function makeTestimonials(): void
    {
        foreach (self::TESTIMONIALS as $i => [$name, $role, $rating, $quote]) {
            Testimonial::create([
                'name' => $name,
                'role' => $role,
                'rating' => $rating,
                'quote' => $quote,
                'is_published' => true,
                'sort_order' => $i,
            ]);
        }
    }
}
