<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Car;
use App\Payments\PaymentGateway;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(private PaymentGateway $gateway)
    {
    }

    public function store(BookingRequest $request): RedirectResponse
    {
        // Honeypot anti-bot: silently accept but discard if the trap is filled.
        if (filled($request->input('website'))) {
            return back()->with('booking_success', 'Permintaan sewa Anda telah kami terima. Tim kami akan segera menghubungi Anda.');
        }

        // Etalase contoh (/demo): tunjukkan alurnya, tapi JANGAN buat booking &
        // pembayaran Midtrans sungguhan atas tenant default. Pengunjung yang
        // sekadar mencoba demo tak boleh membuat pesanan nyata.
        if ($request->boolean('demo')) {
            return back()->with('booking_success', 'Ini demo — pesanan tidak benar-benar dibuat. Di storefront milik Anda sendiri, permintaan sewa pelanggan akan langsung masuk ke dashboard. Daftar gratis untuk mencobanya.');
        }

        // Storefront milik tenant yang langganannya mati tak boleh menerima
        // pesanan baru — kalau tidak, pelanggan mengira sudah memesan padahal
        // owner terkunci dari back-office dan tak pernah melihatnya.
        $tenant = app(TenantManager::class)->current();
        if ($tenant && ! in_array($tenant->subscription_status, ['trial', 'active'], true)) {
            return back()
                ->withInput()
                ->withErrors(['car_id' => 'Maaf, rental ini sedang tidak menerima pesanan.'], 'booking');
        }

        $data = $request->validated();

        // Recompute everything server-side — never trust client values (FR-15 / BR-03).
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $days = max(1, $start->diffInDays($end)); // minimum 1 day (BR-04)

        // Cek-lalu-tulis harus atomik: tanpa kunci, dua permintaan bersamaan
        // sama-sama lolos cek ketersediaan lalu sama-sama menyisipkan booking
        // untuk mobil & tanggal yang sama. Kunci baris mobil menyerialkan
        // pemeriksaan per-mobil (BR-01).
        $result = DB::transaction(function () use ($data, $start, $end, $days) {
            $car = Car::query()->lockForUpdate()->find($data['car_id']);

            if (! $car) {
                return ['error' => ['car_id' => 'Mobil yang dipilih tidak ditemukan.']];
            }

            if (! $car->is_available) {
                return ['error' => ['car_id' => 'Mobil ini sedang tidak tersedia untuk disewa.']];
            }

            if (! $car->isAvailableForRange($start->toDateString(), $end->toDateString())) {
                return ['error' => ['start_date' => 'Maaf, '.$car->name.' sudah dipesan pada rentang tanggal tersebut. Silakan pilih tanggal lain.']];
            }

            return ['booking' => Booking::create([
                'car_id' => $car->id,
                'car_name' => $car->name,            // snapshot (NFR-13 / BR-07)
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'price_per_day' => $car->price_per_day, // snapshot
                'total_price' => $days * $car->price_per_day,
                'status' => 'pending',
                'trip_status' => Booking::TRIP_NOT_STARTED,
                'booking_code' => Booking::generateBookingCode(),
                'notes' => $data['notes'] ?? null,
            ])];
        });

        if (isset($result['error'])) {
            return back()->withInput()->withErrors($result['error'], 'booking');
        }

        $booking = $result['booking'];

        // If an online gateway is active, send the customer straight to payment.
        $paymentUrl = $this->gateway->createCheckout($booking);
        if ($paymentUrl !== null) {
            return redirect()->away($paymentUrl);
        }

        // Offline/manual: keep the original confirmation flow, plus the tracking code.
        return back()
            ->with(
                'booking_success',
                'Permintaan sewa untuk '.$booking->car_name.' berhasil dikirim! Tim kami akan segera menghubungi Anda untuk konfirmasi.'
            )
            ->with('booking_code', $booking->booking_code);
    }
}
