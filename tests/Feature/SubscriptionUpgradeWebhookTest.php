<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionUpgradeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.payment.gateway', 'midtrans');
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    private function signedPayload(string $orderId, string $transactionStatus, string $grossAmount = '350000.00'): array
    {
        $statusCode = '200';
        $serverKey = 'SB-Mid-server-TEST';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return [
            'order_id' => $orderId, 'status_code' => $statusCode, 'gross_amount' => $grossAmount,
            'signature_key' => $signature, 'transaction_status' => $transactionStatus, 'fraud_status' => 'accept',
        ];
    }

    public function test_webhook_promotes_pending_plan_on_upgrade_payment(): void
    {
        $tenant = Tenant::create([
            'name' => 'Existing Co', 'slug' => 'existing-co', 'plan' => 'basic',
            'subscription_status' => 'active', 'pending_plan' => 'pro',
        ]);
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-9990001']);

        $payload = $this->signedPayload($tenant->payment_ref, 'settlement');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $tenant->refresh();
        $this->assertSame('pro', $tenant->plan);
        $this->assertNull($tenant->pending_plan);
        $this->assertSame('active', $tenant->subscription_status);
        $this->assertTrue($tenant->subscription_ends_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_webhook_does_not_touch_plan_when_pending_plan_absent(): void
    {
        // Mirrors the existing new-signup case: plan already correct at creation, no pending_plan set.
        $tenant = Tenant::create([
            'name' => 'New Co', 'slug' => 'new-co', 'plan' => 'business',
            'subscription_status' => 'pending_payment',
        ]);
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-9990002']);

        $payload = $this->signedPayload($tenant->payment_ref, 'settlement');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $tenant->refresh();
        $this->assertSame('business', $tenant->plan);
        $this->assertNull($tenant->pending_plan);
    }

    public function test_pending_plan_not_promoted_when_payment_status_is_not_paid(): void
    {
        $tenant = Tenant::create([
            'name' => 'Waiting Co', 'slug' => 'waiting-co', 'plan' => 'basic',
            'subscription_status' => 'active', 'pending_plan' => 'business',
        ]);
        $tenant->update(['payment_ref' => 'LAJUR-SUB-'.$tenant->id.'-9990003']);

        $payload = $this->signedPayload($tenant->payment_ref, 'pending');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $tenant->refresh();
        $this->assertSame('basic', $tenant->plan);
        $this->assertSame('business', $tenant->pending_plan);
    }
}
