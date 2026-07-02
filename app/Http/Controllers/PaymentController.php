<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Payments\PaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        if ($status && $orderId) {
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
