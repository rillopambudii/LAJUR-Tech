<?php

namespace App\Payments;

use App\Models\Booking;

/**
 * Default "no online gateway yet" driver: payments are handled offline
 * (bank transfer / cash) and confirmed manually by an admin. Acts as a safe
 * placeholder until a real provider (Midtrans/Xendit/Tripay) is wired in.
 */
class ManualPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function createCheckout(Booking $booking): ?string
    {
        // No hosted checkout — payment is arranged manually.
        return null;
    }

    public function verifyCallback(array $payload): ?string
    {
        return null;
    }
}
