<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Payments\SubscriptionCheckout;
use Illuminate\Console\Command;

/**
 * Booking online yang checkout-nya ditinggalkan tetap berstatus `pending`, dan
 * pending MEMBLOKIR tanggal mobilnya (Booking::BLOCKING_STATUSES). Tanpa ini,
 * setiap pengunjung yang menutup halaman Midtrans mengunci satu mobil selamanya.
 * Webhook sudah menangani kasus failed/expired; command ini menutup sisanya
 * (webhook tak pernah datang / pelanggan kabur begitu saja).
 */
class CancelAbandonedBookings extends Command
{
    protected $signature = 'bookings:cancel-abandoned {--hours=24 : Umur booking sebelum dianggap terbengkalai}';

    protected $description = 'Batalkan booking online yang tak dibayar sesudah batas waktu, agar mobilnya kembali bisa dipesan.';

    public function handle(SubscriptionCheckout $midtrans): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));

        // HANYA booking online: sudah punya payment_ref (checkout gateway dibuat)
        // dan belum lunas. Booking manual/offline (payment_ref null) sengaja TIDAK
        // disentuh — itu menunggu konfirmasi admin, bukan menunggu pembayaran.
        $abandoned = Booking::query()
            ->where('status', 'pending')
            ->whereNotNull('payment_ref')
            ->where('payment_status', '!=', 'paid')
            ->where('created_at', '<', $cutoff)
            ->get();

        $cancelled = 0;

        foreach ($abandoned as $booking) {
            // Jaring pengaman uang: kalau webhook hilang, pelanggan sudah bayar
            // tapi DB masih "pending" — tanya Midtrans dulu. verifyPaid() cuma
            // butuh order_id, endpoint statusnya sama untuk booking & langganan.
            $paid = $midtrans->verifyPaid((string) $booking->payment_ref);

            if ($paid === true) {
                $booking->update(['payment_status' => 'paid', 'paid_at' => now(), 'status' => 'confirmed']);
                $this->warn("Booking {$booking->booking_code}: ternyata SUDAH dibayar (webhook tak sampai) — dikonfirmasi.");

                continue;
            }

            if ($paid === null) {
                // Midtrans tak bisa dihubungi: jangan menebak. Biarkan pending,
                // jalankan lagi di jadwal berikutnya.
                $this->warn("Booking {$booking->booking_code}: status pembayaran tak bisa diverifikasi, ditunda.");

                continue;
            }

            $booking->update(['status' => 'cancelled', 'payment_status' => 'expired']);
            $cancelled++;
            $this->line("Booking {$booking->booking_code}: tak dibayar, dibatalkan.");
        }

        $this->info("{$cancelled} booking terbengkalai dibatalkan.");

        return self::SUCCESS;
    }
}
