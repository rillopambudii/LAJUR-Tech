<?php

namespace App\Payments;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Creates a Midtrans Snap transaction for a tenant's plan subscription. Kept
 * separate from MidtransGateway (which is Booking-shaped) so the existing
 * booking payment flow is never touched by subscription billing changes.
 * Order IDs use the "LAJUR-SUB-" prefix so PaymentController::webhook() can
 * route notifications to a Tenant instead of a Booking.
 */
class SubscriptionCheckout
{
    public function createCheckout(Tenant $tenant, Plan $plan, ?string $finishUrl = null): ?string
    {
        $serverKey = (string) config('services.midtrans.server_key');
        if ($serverKey === '') {
            return null;
        }

        $orderId = 'LAJUR-SUB-'.$tenant->id.'-'.time();

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $plan->price,
            ],
            'item_details' => [[
                'id' => 'plan-'.$plan->key,
                'price' => (int) $plan->price,
                'quantity' => 1,
                'name' => mb_substr('Langganan Lajur - '.$plan->name.' (30 hari)', 0, 50),
            ]],
            'customer_details' => [
                'first_name' => $tenant->name,
            ],
            'callbacks' => [
                'finish' => $finishUrl ?? route('signup.finish'),
            ],
        ];

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->timeout(30)
                ->post($this->snapUrl(), $payload);
        } catch (\Throwable $e) {
            Log::warning('Midtrans subscription checkout unreachable', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed() || ! $response->json('redirect_url')) {
            Log::warning('Midtrans subscription checkout failed', ['tenant' => $tenant->id, 'body' => $response->body()]);

            return null;
        }

        $tenant->forceFill(['payment_ref' => $orderId])->save();

        return $response->json('redirect_url');
    }

    private function snapUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }
}
