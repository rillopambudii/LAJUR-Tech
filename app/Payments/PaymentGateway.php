<?php

namespace App\Payments;

use App\Models\Booking;

/**
 * Contract for online payment providers (Midtrans / Xendit / Tripay, …).
 *
 * Implementations are intentionally deferred — payment gateway integration is a
 * later milestone. Bind a concrete driver in AppServiceProvider when ready; the
 * rest of the app depends only on this interface.
 */
interface PaymentGateway
{
    /** Machine name of the driver, e.g. "midtrans". */
    public function name(): string;

    /**
     * Create a checkout/charge for a booking and return the payment URL the
     * customer should be redirected to (or null for manual/offline handling).
     */
    public function createCheckout(Booking $booking): ?string;

    /**
     * Verify a provider webhook/callback payload and return the resolved status
     * ('paid', 'pending', 'failed'), or null when it cannot be verified.
     *
     * @param array<string, mixed> $payload
     */
    public function verifyCallback(array $payload): ?string;
}
