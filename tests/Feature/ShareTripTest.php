<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareTripTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function booking(array $o = []): Booking
    {
        $car = Car::create([
            'name' => 'Avanza', 'plate_number' => 'KT 1 AB', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin', 'seats' => 7,
            'price_per_day' => 300000, 'is_available' => true,
        ]);

        return Booking::create(array_merge([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi Santoso', 'customer_email' => 'b@x.id', 'customer_phone' => '081',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 2,
            'price_per_day' => 300000, 'total_price' => 654321, 'status' => 'confirmed',
            'trip_status' => Booking::TRIP_ON_THE_WAY, 'booking_code' => Booking::generateBookingCode(),
        ], $o));
    }

    public function test_watch_shows_status_without_price(): void
    {
        $b = $this->booking();
        $res = $this->get('/pantau/'.$b->booking_code);
        $res->assertOk();
        $res->assertSee('Budi');                 // first name / warm title
        $res->assertSee($b->trip_status_label);  // status
        $res->assertSee('Avanza');               // car
        $res->assertDontSee('654.321');          // no price
        $res->assertDontSee('Total');            // no financial label
    }

    public function test_watch_unknown_code_redirects(): void
    {
        $this->get('/pantau/LJR-NOPE00')->assertRedirect(route('tracking.search'));
    }

    public function test_lacak_has_share_button(): void
    {
        $b = $this->booking();
        $res = $this->get('/lacak/'.$b->booking_code);
        $res->assertOk();
        $res->assertSee('Bagikan ke keluarga');
        $res->assertSee('/pantau/'.$b->booking_code, false);
    }
}
