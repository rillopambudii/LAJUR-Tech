<?php

namespace Database\Seeders;

use App\Models\Car;
use Illuminate\Database\Seeder;

class CarSeeder extends Seeder
{
    public function run(): void
    {
        $cars = [
            [
                'name' => 'Fortuner VRZ', 'brand' => 'Toyota', 'type' => 'SUV',
                'transmission' => 'Automatic', 'fuel_type' => 'Diesel', 'seats' => 7,
                'price_per_day' => 950000, 'is_featured' => true,
                'image' => 'https://images.unsplash.com/photo-1605559424843-9e4c228bf1c2?auto=format&fit=crop&w=800&q=70',
                'description' => 'SUV tangguh untuk medan Kalimantan, nyaman untuk keluarga maupun perjalanan dinas.',
            ],
            [
                'name' => 'Innova Zenix', 'brand' => 'Toyota', 'type' => 'MPV',
                'transmission' => 'Automatic', 'fuel_type' => 'Hybrid', 'seats' => 7,
                'price_per_day' => 750000, 'is_featured' => true,
                'image' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/90/2023_Kijang_Innova_Zenix_G_HV.jpg/960px-2023_Kijang_Innova_Zenix_G_HV.jpg',
                'description' => 'MPV hybrid irit dan lega, pilihan favorit untuk perjalanan keluarga.',
            ],
            [
                'name' => 'Alphard', 'brand' => 'Toyota', 'type' => 'Luxury',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 7,
                'price_per_day' => 2500000, 'is_featured' => true,
                'image' => 'https://images.unsplash.com/photo-1632245889029-e406faaa34cd?auto=format&fit=crop&w=800&q=70',
                'description' => 'Kemewahan untuk tamu VIP, acara penting, dan perjalanan eksekutif.',
            ],
            [
                'name' => 'Pajero Sport Dakar', 'brand' => 'Mitsubishi', 'type' => 'SUV',
                'transmission' => 'Automatic', 'fuel_type' => 'Diesel', 'seats' => 7,
                'price_per_day' => 1000000, 'is_featured' => false,
                'image' => 'https://images.unsplash.com/photo-1606016159991-dfe4f2746ad5?auto=format&fit=crop&w=800&q=70',
                'description' => 'Performa bertenaga dengan kabin premium dan fitur keselamatan lengkap.',
            ],
            [
                'name' => 'All New Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 7,
                'price_per_day' => 450000, 'is_featured' => false,
                'image' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9a/2021_Toyota_Avanza_1.5_G_Toyota_Safety_Sense_%28Indonesia%29_front_view_01.jpg/960px-2021_Toyota_Avanza_1.5_G_Toyota_Safety_Sense_%28Indonesia%29_front_view_01.jpg',
                'description' => 'MPV keluarga generasi terbaru, ekonomis dan andal untuk mobilitas harian.',
            ],
            [
                'name' => 'Brio RS', 'brand' => 'Honda', 'type' => 'Hatchback',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 5,
                'price_per_day' => 350000, 'is_featured' => false,
                'image' => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=800&q=70',
                'description' => 'Lincah untuk dalam kota, hemat bahan bakar, mudah dikendarai.',
            ],
            [
                'name' => 'Camry', 'brand' => 'Toyota', 'type' => 'Sedan',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 5,
                'price_per_day' => 1200000, 'is_featured' => false,
                'image' => 'https://images.unsplash.com/photo-1621007947382-bb3c3994e3fb?auto=format&fit=crop&w=800&q=70',
                'description' => 'Sedan elegan untuk perjalanan bisnis yang berkelas.',
            ],
            [
                'name' => 'Triton', 'brand' => 'Mitsubishi', 'type' => 'Pickup',
                'transmission' => 'Manual', 'fuel_type' => 'Diesel', 'seats' => 5,
                'price_per_day' => 650000, 'is_featured' => false, 'is_available' => false,
                'image' => 'https://images.unsplash.com/photo-1558618047-3c8c76ca7d13?auto=format&fit=crop&w=800&q=70',
                'description' => 'Pickup tangguh untuk angkutan barang dan medan berat.',
            ],
            [
                'name' => 'Agya', 'brand' => 'Toyota', 'type' => 'Hatchback',
                'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 5,
                'price_per_day' => 300000, 'is_featured' => false,
                'image' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/39/2017_Toyota_Agya_1.2_G_B101RA_%2820190515%29.jpg/960px-2017_Toyota_Agya_1.2_G_B101RA_%2820190515%29.jpg',
                'description' => 'City car irit dan gesit, cocok untuk mobilitas harian di dalam kota.',
            ],
            [
                'name' => 'Hilux Double Cabin', 'brand' => 'Toyota', 'type' => 'Pickup',
                'transmission' => 'Manual', 'fuel_type' => 'Diesel', 'seats' => 5,
                'price_per_day' => 700000, 'is_featured' => false,
                'image' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/48/2020_Toyota_Hilux_E_%28front_left_side_view%29.jpg/960px-2020_Toyota_Hilux_E_%28front_left_side_view%29.jpg',
                'description' => 'Pickup tangguh 4x4 untuk medan berat dan angkutan operasional tambang.',
            ],
            [
                'name' => 'All New Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV',
                'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 7,
                'price_per_day' => 400000, 'is_featured' => false,
                'image' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1d/2021_Daihatsu_Xenia_1.5_R_Greenish_Gun_Metal.jpg/960px-2021_Daihatsu_Xenia_1.5_R_Greenish_Gun_Metal.jpg',
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
}
