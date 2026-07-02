<?php

namespace App\Payments;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Midtrans Snap driver. createCheckout() creates a Snap transaction and returns
 * the hosted payment URL the customer is redirected to; verifyCallback()
 * validates the HTTP notification signature and maps it to a payment status.
 *
 * Credentials come from config('services.midtrans'); when the server key is
 * blank the driver is inert (createCheckout returns null) so the app degrades
 * gracefully to the offline flow.
 */
class MidtransGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'midtrans';
    }

    public function createCheckout(Booking $booking): ?string
    {
        $serverKey = (string) config('services.midtrans.server_key');
        if ($serverKey === '') {
            return null;
        }

        // Unique per attempt; stored so the webhook can find this booking.
        $orderId = 'LAJUR-'.$booking->id.'-'.time();

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $booking->total_price,
            ],
            'item_details' => [[
                'id' => (string) ($booking->car_id ?? 'car'),
                'price' => (int) $booking->price_per_day,
                'quantity' => (int) $booking->days,
                'name' => mb_substr('Sewa '.$booking->car_name, 0, 50),
            ]],
            'customer_details' => [
                'first_name' => $booking->customer_name,
                'email' => $booking->customer_email,
                'phone' => $booking->customer_phone,
            ],
            'callbacks' => [
                'finish' => route('payment.finish'),
            ],
        ];

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->timeout(30)
            ->post($this->snapUrl(), $payload);

        if ($response->failed() || ! $response->json('redirect_url')) {
            Log::warning('Midtrans checkout failed', ['booking' => $booking->id, 'body' => $response->body()]);

            return null;
        }

        $booking->forceFill([
            'payment_ref' => $orderId,
            'payment_status' => 'pending',
        ])->save();

        return $response->json('redirect_url');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function verifyCallback(array $payload): ?string
    {
        $serverKey = (string) config('services.midtrans.server_key');

        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $signature = $payload['signature_key'] ?? null;

        if (! $orderId || ! $statusCode || $grossAmount === null || ! $signature) {
            return null;
        }

        // Signature = sha512(order_id + status_code + gross_amount + server_key).
        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
        if (! hash_equals($expected, (string) $signature)) {
            Log::warning('Midtrans webhook signature mismatch', ['order_id' => $orderId]);

            return null;
        }

        return $this->mapStatus(
            (string) ($payload['transaction_status'] ?? ''),
            (string) ($payload['fraud_status'] ?? 'accept'),
        );
    }

    private function mapStatus(string $transactionStatus, string $fraudStatus): ?string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'accept' ? 'paid' : 'pending',
            'settlement' => 'paid',
            'pending' => 'pending',
            'deny', 'cancel' => 'failed',
            'expire' => 'expired',
            'refund', 'partial_refund', 'chargeback' => 'failed',
            default => null,
        };
    }

    private function snapUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }
}
