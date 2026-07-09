<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFinishTrackTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function paidBooking(): Booking
    {
        $car = Car::create([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ]);

        return Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 2,
            'price_per_day' => 300000, 'total_price' => 600000, 'status' => 'confirmed',
            'trip_status' => Booking::TRIP_NOT_STARTED, 'booking_code' => 'LJR-PAY123',
            'payment_status' => 'paid', 'payment_ref' => 'ORDER-XYZ-1',
        ]);
    }

    public function test_finish_shows_tracking_link_and_code_for_booking(): void
    {
        $this->paidBooking();

        $res = $this->get('/payment/finish?order_id=ORDER-XYZ-1');

        $res->assertOk();
        $res->assertSee('LJR-PAY123');                 // the booking code
        $res->assertSee('/lacak/LJR-PAY123', false);   // the tracking link
    }

    public function test_finish_without_booking_has_no_tracking_link(): void
    {
        $res = $this->get('/payment/finish?order_id=DOES-NOT-EXIST');

        $res->assertOk();
        $res->assertDontSee('/lacak/', false);
    }
}
