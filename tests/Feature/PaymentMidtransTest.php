<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Payments\ManualPaymentGateway;
use App\Payments\MidtransGateway;
use App\Payments\PaymentGateway;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentMidtransTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);

        config()->set('services.payment.gateway', 'midtrans');
        config()->set('services.midtrans.server_key', 'SB-Mid-server-TEST');
        config()->set('services.midtrans.is_production', false);
    }

    private function makeCar(): Car
    {
        return Car::create([
            'name' => 'Innova', 'brand' => 'Toyota', 'type' => 'MPV', 'transmission' => 'Automatic',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 400000, 'is_available' => true,
        ]);
    }

    private function makeBooking(Car $car, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Ani',
            'customer_email' => 'ani@x.id', 'customer_phone' => '081234567',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 400000, 'total_price' => 800000, 'status' => 'pending',
        ], $overrides));
    }

    public function test_create_checkout_returns_redirect_url_and_sets_ref(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([
                'token' => 'snap-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/xyz',
            ]),
        ]);

        $booking = $this->makeBooking($this->makeCar());
        $url = app(MidtransGateway::class)->createCheckout($booking);

        $this->assertSame('https://app.sandbox.midtrans.com/snap/v2/vtweb/xyz', $url);
        $booking->refresh();
        $this->assertSame('pending', $booking->payment_status);
        $this->assertStringStartsWith('LAJUR-'.$booking->id.'-', $booking->payment_ref);
    }

    public function test_public_booking_redirects_to_payment(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response([
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/abc',
            ]),
        ]);
        $car = $this->makeCar();

        $this->post('/booking', [
            'car_id' => $car->id, 'customer_name' => 'Ani', 'customer_email' => 'ani@x.id',
            'customer_phone' => '081234567', 'start_date' => '2026-09-10', 'end_date' => '2026-09-12',
        ])->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/abc');
    }

    public function test_webhook_marks_booking_paid_and_confirmed(): void
    {
        $booking = $this->makeBooking($this->makeCar(), [
            'payment_ref' => 'LAJUR-99-1700000000', 'payment_status' => 'pending', 'status' => 'pending',
        ]);

        $payload = $this->signedPayload('LAJUR-99-1700000000', '200', '800000.00', 'settlement');

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->status);
        $this->assertNotNull($booking->paid_at);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $booking = $this->makeBooking($this->makeCar(), [
            'payment_ref' => 'LAJUR-77-1700000000', 'payment_status' => 'pending', 'status' => 'pending',
        ]);

        $payload = $this->signedPayload('LAJUR-77-1700000000', '200', '800000.00', 'settlement');
        $payload['signature_key'] = 'tampered';

        $this->postJson('/payment/midtrans/webhook', $payload)->assertOk();

        $booking->refresh();
        $this->assertSame('pending', $booking->payment_status);
        $this->assertSame('pending', $booking->status);
    }

    public function test_manual_gateway_returns_null(): void
    {
        config()->set('services.payment.gateway', 'manual');
        $gateway = app(PaymentGateway::class);

        $this->assertInstanceOf(ManualPaymentGateway::class, $gateway);
        $this->assertNull($gateway->createCheckout($this->makeBooking($this->makeCar())));
    }

    /** @return array<string, string> */
    private function signedPayload(string $orderId, string $statusCode, string $gross, string $txStatus): array
    {
        $serverKey = config('services.midtrans.server_key');

        return [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => $txStatus,
            'fraud_status' => 'accept',
            'signature_key' => hash('sha512', $orderId.$statusCode.$gross.$serverKey),
        ];
    }
}
