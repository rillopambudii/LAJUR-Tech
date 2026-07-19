<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Tenant;
use App\Payments\PaymentGateway;
use App\Payments\SubscriptionCheckout;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private PaymentGateway $gateway)
    {
    }

    /**
     * Gateway HTTP notification (webhook). Verifies the signature, then updates
     * the matching booking. The booking is looked up WITHOUT the tenant scope —
     * the request has no session/tenant context and payment_ref is globally
     * unique, so this safely resolves the right tenant's booking.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        $status = $this->gateway->verifyCallback($payload);
        $orderId = $payload['order_id'] ?? null;

        if ($status && $orderId && str_starts_with((string) $orderId, 'LAJUR-SUB-')) {
            $this->activateSubscription((string) $orderId, $status);
        } elseif ($status && $orderId) {
            $booking = Booking::withoutGlobalScopes()->where('payment_ref', $orderId)->first();

            if ($booking) {
                $booking->payment_status = $status;

                if ($status === 'paid') {
                    $booking->paid_at = now();
                    // Payment confirms a still-pending booking.
                    if ($booking->status === 'pending') {
                        $booking->status = 'confirmed';
                    }
                }

                $booking->save();
            }
        }

        // Always 200 so the gateway stops retrying; we only act on a valid,
        // signature-verified, mapped status.
        return response()->json(['ok' => true]);
    }

    /** Activates a tenant's paid subscription. No-op for any status other than 'paid' or if the order_id doesn't match a pending tenant. */
    private function activateSubscription(string $orderId, string $status): void
    {
        if ($status !== 'paid') {
            return;
        }

        $tenant = Tenant::where('payment_ref', $orderId)->first();

        if (! $tenant) {
            // Bisa terjadi kalau tenant ganti pilihan plan (payment_ref lama
            // tertimpa) lalu pembayaran pertama tetap diselesaikan di Midtrans —
            // uang sudah masuk tapi tak ada tenant yang cocok untuk diaktifkan.
            // Dicatat supaya bisa direkonsiliasi manual, bukan hilang senyap.
            Log::warning('Midtrans subscription webhook: no tenant matches payment_ref', ['order_id' => $orderId]);

            return;
        }

        // Satu sumber aktivasi, dipakai bersama halaman "selesai" (verifikasi mandiri).
        app(SubscriptionCheckout::class)->activate($tenant);
    }

    /**
     * Where the customer lands after the hosted payment page. The webhook is the
     * source of truth; this page reflects the current status (which may still be
     * "pending" if the notification hasn't arrived yet).
     */
    public function finish(Request $request): View
    {
        $orderId = (string) $request->query('order_id', '');
        $booking = $orderId !== ''
            ? Booking::withoutGlobalScopes()->where('payment_ref', $orderId)->first()
            : null;

        return view('payment.finish', ['booking' => $booking]);
    }
}
