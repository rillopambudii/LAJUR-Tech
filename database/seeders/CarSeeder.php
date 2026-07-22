<?php

namespace Database\Seeders;

use App\Models\Car;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CarSeeder extends Seeder
{
    public function run(): void
    {
        $this->publishImages();

        $cars = [
            [
                'name' => 'Fortuner VRZ', 'brand' => 'Toyota', 'type' => 'SUV',
                'transmission' => 'Automatic', 'fuel_type' => 'Diesel', 'seats' => 7,
                'price_per_day' => 950000, 'is_featured' => true,
                'image' => 'cars/toyota-fortuner-vrz.jpg',
                'description' => 'SUV tangguh untuk medan Kalimantan, nyaman untuk keluarga maupun perjalanan dinas.',
            ],
            [
                'name' => 'Innova Zenix', 'brand' => 'Toyota', 'type' => 'MPV',
                'transmission' => 'Automatic', 'fuel_type' => 'Hybrid', 'seats' => 7,
                'price_per_day' => 750000, 'is_featured' => true,
                'image' => 'cars/toyota-innova-zenix.jpg',
                'description' => 'MPV hybrid irit dan lega, pilihan favorit untuk perjalanan keluarga.',
            ],
            [
                'name' => 'Alphard', 'brand' => 'Toyota', 'type' => 'Luxury',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 7,
                'price_per_day' => 2500000, 'is_featured' => true,
                'image' => 'cars/toyota-alphard.jpg',
                'description' => 'Kemewahan untuk tamu VIP, acara penting, dan perjalanan eksekutif.',
            ],
            [
                'name' => 'Pajero Sport Dakar', 'brand' => 'Mitsubishi', 'type' => 'SUV',
                'transmission' => 'Automatic', 'fuel_type' => 'Diesel', 'seats' => 7,
                'price_per_day' => 1000000, 'is_featured' => false,
                'image' => 'cars/mitsubishi-pajero-sport-dakar.jpg',
                'description' => 'Performa bertenaga dengan kabin premium dan fitur keselamatan lengkap.',
            ],
            [
                'name' => 'All New Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 7,
                'price_per_day' => 450000, 'is_featured' => false,
                'image' => 'cars/toyota-all-new-avanza.jpg',
                'description' => 'MPV keluarga generasi terbaru, ekonomis dan andal untuk mobilitas harian.',
            ],
            [
                'name' => 'Brio RS', 'brand' => 'Honda', 'type' => 'Hatchback',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 5,
                'price_per_day' => 350000, 'is_featured' => false,
                'image' => 'cars/honda-brio-rs.jpg',
                'description' => 'Lincah untuk dalam kota, hemat bahan bakar, mudah dikendarai.',
            ],
            [
                'name' => 'Camry', 'brand' => 'Toyota', 'type' => 'Sedan',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 5,
                'price_per_day' => 1200000, 'is_featured' => false,
                'image' => 'cars/toyota-camry.jpg',
                'description' => 'Sedan elegan untuk perjalanan bisnis yang berkelas.',
            ],
            [
                'name' => 'Triton', 'brand' => 'Mitsubishi', 'type' => 'Pickup',
                'transmission' => 'Manual', 'fuel_type' => 'Diesel', 'seats' => 5,
                'price_per_day' => 650000, 'is_featured' => false, 'is_available' => false,
                'image' => null, // foto asli belum ada — tampil placeholder bermerek
                'description' => 'Pickup tangguh untuk angkutan barang dan medan berat.',
            ],
            [
                'name' => 'Agya', 'brand' => 'Toyota', 'type' => 'Hatchback',
                'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 5,
                'price_per_day' => 300000, 'is_featured' => false,
                'image' => 'cars/toyota-agya.jpg',
                'description' => 'City car irit dan gesit, cocok untuk mobilitas harian di dalam kota.',
            ],
            [
                'name' => 'Hilux Double Cabin', 'brand' => 'Toyota', 'type' => 'Pickup',
                'transmission' => 'Manual', 'fuel_type' => 'Diesel', 'seats' => 5,
                'price_per_day' => 700000, 'is_featured' => false,
                'image' => 'cars/toyota-hilux-double-cabin.jpg',
                'description' => 'Pickup tangguh 4x4 untuk medan berat dan angkutan operasional tambang.',
            ],
            [
                'name' => 'All New Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 7,
                'price_per_day' => 400000, 'is_featured' => false,
                'image' => 'cars/daihatsu-all-new-xenia.jpg',
                'description' => 'MPV keluarga generasi terbaru yang lega dan irit, ideal untuk perjalanan bersama.',
            ],
        ];

        foreach ($cars as $i => $data) {
            Car::updateOrCreate(
                ['name' => $data['name'], 'brand' => $data['brand']],
                array_merge($data, ['sort_order' => $i]),
            );
        }
    }

    /**
     * Salin foto armada demo dari repo ke disk publik.
     *
     * Foto disimpan di database/seeders/assets/cars (ikut versi git) karena
     * storage/app/public sengaja tidak diversikan. Tanpa ini, instalasi baru
     * kehilangan foto — dulu dipecahkan dengan hotlink ke Unsplash/Wikimedia,
     * tapi satu per satu URL-nya mati (soak 2026-07-22 menemukan foto Triton
     * sudah 404 dan tampil rusak di /admin/cars).
     */
    private function publishImages(): void
    {
        $source = database_path('seeders/assets/cars');

        if (! is_dir($source)) {
            return;
        }

        foreach (glob($source.'/*.jpg') as $file) {
            $target = 'cars/'.basename($file);

            if (! Storage::disk('public')->exists($target)) {
                Storage::disk('public')->put($target, file_get_contents($file));
            }
        }
    }
}
