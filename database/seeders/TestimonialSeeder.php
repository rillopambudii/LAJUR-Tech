<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Rizky Pratama', 'role' => 'Pengusaha, Samarinda', 'rating' => 5,
                'quote' => 'Pelayanan cepat dan mobilnya bersih terawat. Proses sewa gampang banget, tinggal isi form langsung dihubungi. Recommended!'],
            ['name' => 'Dewi Anggraini', 'role' => 'Karyawan Swasta, Balikpapan', 'rating' => 5,
                'quote' => 'Harga transparan, tidak ada biaya tersembunyi. Innova-nya nyaman untuk perjalanan keluarga ke Berau. Pasti sewa lagi.'],
            ['name' => 'Budi Santoso', 'role' => 'PNS', 'rating' => 4,
                'quote' => 'Untuk perjalanan dinas sangat membantu. Unit selalu siap dan tim responsif. Sedikit menunggu konfirmasi tapi worth it.'],
            ['name' => 'Maria Yulianti', 'role' => 'Event Organizer', 'rating' => 5,
                'quote' => 'Sewa Alphard untuk tamu VIP acara kami, semuanya berjalan mulus. Sopir profesional, mobil mewah. Terima kasih Lajur!'],
            ['name' => 'Ahmad Fauzi', 'role' => 'Wiraswasta, Tenggarong', 'rating' => 5,
                'quote' => 'Sudah langganan beberapa kali. Konsisten bagus dari segi pelayanan dan kondisi kendaraan. Mantap.'],
            ['name' => 'Siti Nurhaliza', 'role' => 'Dokter', 'rating' => 4,
                'quote' => 'Booking online-nya praktis, estimasi harga langsung muncul jadi tidak bingung. Mobil sesuai ekspektasi.'],
        ];

        foreach ($items as $i => $data) {
            Testimonial::updateOrCreate(
                ['name' => $data['name'], 'quote' => $data['quote']],
                array_merge($data, ['is_published' => true, 'sort_order' => $i]),
            );
        }
    }
}
