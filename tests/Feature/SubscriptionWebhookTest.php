<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.payment.gateway', 'midtrans');
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    private function signedPayload(string $orderId, string $transactionStatus): array
    {
        $statusCode = '200';
        $grossAmount = '350000.00';
        $serverKey = 'SB-Mid-server-TEST';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return [
            'order_id' => $orderId, 'status_code' => $statusCode, 'gross_amount' => $grossAmount,
            'signature_key' => $signature, 'transaction_status' => $transactionStatus, 'fraud_status' => 'accept',
        ];
    }

    public function test_webhook_activates_pending_tenant_on_settlement(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co', 'slug' => 'pending-co', 'plan' => 'pro',
            'subscription_status' => 'pending_payment', 'payment_ref' => 'LAJUR-SUB-' . 1,
        ]);
        // payment_ref must match exactly what's signed/looked-up below.
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-1234567890']);

        $payload = $this->signedPayload($tenant->payment_ref, 'settlement');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $tenant->refresh();
        $this->assertSame('active', $tenant->subscription_status);
        $this->assertNotNull($tenant->subscription_ends_at);
        $this->assertTrue($tenant->subscription_ends_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_webhook_does_not_activate_on_pending_status(): void
    {
        $tenant = Tenant::create([
            'name' => 'Pending Co 2', 'slug' => 'pending-co-2', 'plan' => 'pro',
            'subscription_status' => 'pending_payment',
        ]);
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-1234567891']);

        $payload = $this->signedPayload($tenant->payment_ref, 'pending');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $this->assertSame('pending_payment', $tenant->fresh()->subscription_status);
    }

    public function test_webhook_ignores_unknown_subscription_order_id(): void
    {
        $payload = $this->signedPayload('LAJUR-SUB-99999-000', 'settlement');

        // Must not throw even though no tenant matches this payment_ref.
        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();
    }
}
