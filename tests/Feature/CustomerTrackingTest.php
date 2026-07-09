<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function makeCar(): Car
    {
        return Car::create([
            'name' => 'Avanza', 'brand' => 'Toyota', 'type' => 'MPV',
            'transmission' => 'Automatic', 'fuel_type' => 'Bensin',
            'seats' => 7, 'price_per_day' => 300000, 'is_available' => true,
        ]);
    }

    private function book(Car $car, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'car_id' => $car->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'b@x.id', 'customer_phone' => '081234567',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12', 'days' => 2,
            'price_per_day' => 300000, 'total_price' => 600000, 'status' => 'pending',
            'trip_status' => Booking::TRIP_NOT_STARTED,
            'booking_code' => Booking::generateBookingCode(),
        ], $overrides));
    }

    public function test_generated_code_is_unique_and_well_formed(): void
    {
        $code = Booking::generateBookingCode();

        $this->assertMatchesRegularExpression('/^LJR-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $code);
    }

    public function test_public_booking_gets_code_and_success_flash_carries_it(): void
    {
        $car = $this->makeCar();

        $response = $this->post('/booking', [
            'car_id' => $car->id,
            'customer_name' => 'Ani',
            'customer_email' => 'ani@x.id',
            'customer_phone' => '081234567',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-22',
        ]);

        $response->assertSessionHas('booking_success');
        $response->assertSessionHas('booking_code');

        $booking = Booking::where('car_id', $car->id)->firstOrFail();
        $this->assertNotNull($booking->booking_code);
        $this->assertSame(Booking::TRIP_NOT_STARTED, $booking->trip_status);
        $this->assertSame($booking->booking_code, session('booking_code'));
    }

    public function test_tracking_page_shows_booking_by_code(): void
    {
        $booking = $this->book($this->makeCar());

        $this->get('/lacak/'.$booking->booking_code)
            ->assertOk()
            ->assertSee($booking->booking_code)
            ->assertSee('Belum Diproses');
    }

    public function test_lowercase_code_still_resolves(): void
    {
        $booking = $this->book($this->makeCar());

        $this->get('/lacak/'.strtolower($booking->booking_code))->assertOk();
    }

    public function test_unknown_code_redirects_to_search_with_error(): void
    {
        $this->get('/lacak/LJR-XXXXXX')
            ->assertRedirect(route('tracking.search'))
            ->assertSessionHas('tracking_error');
    }

    public function test_find_requires_both_code_and_phone_to_match(): void
    {
        $booking = $this->book($this->makeCar(), ['customer_phone' => '081200001111']);

        // Wrong phone -> no reveal.
        $this->from(route('tracking.search'))->post('/lacak', [
            'booking_code' => $booking->booking_code,
            'customer_phone' => '080000000000',
        ])->assertRedirect(route('tracking.search'))->assertSessionHas('tracking_error');

        // Correct pair -> redirect to the tracking page.
        $this->post('/lacak', [
            'booking_code' => strtolower($booking->booking_code),
            'customer_phone' => '081200001111',
        ])->assertRedirect(route('tracking.show', $booking->booking_code));
    }

    public function test_admin_can_update_trip_status_and_it_shows_publicly(): void
    {
        $booking = $this->book($this->makeCar());
        $owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'secret', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->patch(route('admin.bookings.trip-status', $booking), [
            'trip_status' => Booking::TRIP_ON_THE_WAY,
            'eta_manual_note' => 'Estimasi tiba 30 menit lagi',
        ])->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame(Booking::TRIP_ON_THE_WAY, $booking->trip_status);
        $this->assertSame(70, $booking->trip_progress);

        $this->get('/lacak/'.$booking->booking_code)
            ->assertOk()
            ->assertSee('Dalam Perjalanan')
            ->assertSee('Estimasi tiba 30 menit lagi');
    }

    public function test_has_live_gps_is_false_without_fresh_position(): void
    {
        $booking = $this->book($this->makeCar());

        $this->assertFalse($booking->fresh()->load('car.latestPosition')->has_live_gps);
    }
}
