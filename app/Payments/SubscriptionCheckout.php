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
                'gross_amount' => $plan->effectivePrice(),
            ],
            'item_details' => [[
                'id' => 'plan-'.$plan->key,
                'price' => $plan->effectivePrice(),
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

    /**
     * Verifikasi status transaksi LANGSUNG ke Midtrans (server-to-server, pakai
     * server key). Dipakai halaman "selesai" agar tak menunggu webhook — penting
     * di lokal (Midtrans tak bisa menjangkau localhost) dan menutup balapan
     * redirect-vs-webhook di produksi. Aman: verifikasi di sisi server, bukan
     * percaya redirect dari browser.
     */
    public function verifyPaid(string $orderId): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');
        if ($serverKey === '' || $orderId === '') {
            return false;
        }

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->timeout(15)
                ->get($this->statusUrl($orderId));
        } catch (\Throwable $e) {
            Log::warning('Midtrans status check unreachable', ['order' => $orderId, 'error' => $e->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            return false;
        }

        $tx = (string) $response->json('transaction_status');
        $fraud = (string) ($response->json('fraud_status') ?? 'accept');

        return in_array($tx, ['settlement', 'capture'], true) && $fraud === 'accept';
    }

    /**
     * Aktifkan langganan tenant: 30 hari sejak sekarang, terapkan pending_plan
     * bila ada (alur upgrade dari dashboard). Idempoten — aman dipanggil ulang.
     */
    public function activate(Tenant $tenant): void
    {
        $data = [
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addDays(30),
        ];

        if ($tenant->pending_plan) {
            $data['plan'] = $tenant->pending_plan;
            $data['pending_plan'] = null;
        }

        $tenant->update($data);
    }

    private function snapUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /** Core API (api.*, bukan app.*) untuk cek status transaksi. */
    private function statusUrl(string $orderId): string
    {
        $base = config('services.midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        return $base.'/v2/'.$orderId.'/status';
    }
}
