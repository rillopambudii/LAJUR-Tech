<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Booking demo untuk mengisi kalender armada (/admin/calendar) agar terlihat
 * seperti rental yang sibuk — untuk demo/rekaman.
 *
 * Kalender HANYA menampilkan booking aktif (pending + confirmed); booking dari
 * DemoFuelSeeder berstatus 'completed' sehingga tak muncul. Seeder ini menaruh
 * booking pending/confirmed tersebar di BULAN BERJALAN, jadi jalankan ulang
 * kapan saja untuk mengisi bulan yang sedang dilihat.
 *
 * Deterministik (tanpa random) + penanda email khusus agar bisa diulang tanpa
 * menyentuh booking asli maupun booking DemoFuelSeeder.
 *
 * ponytail: seeder aset demo sekali-pakai, bukan fixture test.
 */
class DemoCalendarSeeder extends Seeder
{
    /**
     * Penyewa demo — email natural (masuk daftar booking yg terlihat di video).
     * Penanda hapus-ulang pakai PREFIX TELEPON `08129001xxxx` (kolom telepon tak
     * tampil di daftar, jadi tak mengotori tampilan) — distinktif, tak menabrak
     * booking asli maupun DemoFuelSeeder.
     */
    private const PHONE_PREFIX = '08129001';

    private const RENTERS = [
        ['Hendra Wijaya', 'hendra.wijaya88@gmail.com', '081290010001'],
        ['Melati Kusuma', 'melati.kusuma@gmail.com', '081290010002'],
        ['Rangga Saputra', 'rangga.saputra@gmail.com', '081290010003'],
        ['Intan Permata', 'intan.permata21@gmail.com', '081290010004'],
        ['Fajar Ramadhan', 'fajar.ramadhan@gmail.com', '081290010005'],
        ['Wulan Sari', 'wulan.sari.wd@gmail.com', '081290010006'],
        ['Dimas Anggara', 'dimas.anggara@gmail.com', '081290010007'],
        ['Citra Dewi', 'citra.dewi90@gmail.com', '081290010008'],
        ['Bagas Pratama', 'bagas.pratama@gmail.com', '081290010009'],
        ['Ayu Lestari', 'ayu.lestari@gmail.com', '081290010010'],
    ];

    public function run(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        // Bersihkan booking demo-kalender sebelumnya (penanda prefix telepon), lalu isi ulang.
        Booking::where('customer_phone', 'like', self::PHONE_PREFIX.'%')->delete();

        $monthStart = Carbon::now()->startOfMonth();
        $lastDay = $monthStart->daysInMonth;
        $cars = Car::available()->ordered()->get();
        $r = 0;

        foreach ($cars->values() as $i => $car) {
            // Dua blok per mobil, digeser diagonal agar grid terisi merata:
            // blok 1 di paruh awal bulan, blok 2 di paruh akhir, tanpa tumpang tindih.
            $blocks = [
                [1 + ($i * 2) % 12, 2 + ($i % 3)],       // [hari mulai, panjang]
                [16 + ($i * 3) % 10, 2 + (($i + 1) % 3)],
            ];

            foreach ($blocks as $b => [$startDay, $len]) {
                if ($startDay + $len - 1 > $lastDay) {
                    continue; // jangan meluber ke bulan berikutnya
                }

                // Warna berselang-seling: sebagian confirmed (hijau), sebagian pending (kuning).
                $status = (($i + $b) % 2 === 0) ? 'confirmed' : 'pending';

                $start = $monthStart->copy()->addDays($startDay - 1);
                $end = $start->copy()->addDays($len - 1);
                [$name, $email, $phone] = self::RENTERS[$r % count(self::RENTERS)];
                $r++;

                Booking::create([
                    'tenant_id' => $tenant->id,
                    'car_id' => $car->id,
                    'car_name' => $car->name,
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'customer_phone' => $phone,
                    'start_date' => $start,
                    'end_date' => $end,
                    'days' => $len,
                    'price_per_day' => (int) $car->price_per_day,
                    'total_price' => (int) $car->price_per_day * $len,
                    'status' => $status,
                    'booking_code' => Booking::generateBookingCode(),
                ]);
            }
        }
    }
}
