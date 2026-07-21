<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialBookingSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_testimonial_can_link_to_a_booking(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);
        $booking = Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Rina',
            'customer_email' => 'r@x.id', 'customer_phone' => '0811', 'start_date' => '2026-09-01',
            'end_date' => '2026-09-03', 'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000,
            'status' => 'completed',
        ]);

        $testimonial = Testimonial::create([
            'name' => 'Rina', 'rating' => 5, 'quote' => 'Mantap!', 'is_published' => false,
            'booking_id' => $booking->id,
        ]);

        $this->assertSame($booking->id, $testimonial->booking->id);
    }

    public function test_manual_testimonial_without_booking_still_works(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);

        $testimonial = Testimonial::create(['name' => 'Owner Manual', 'rating' => 5, 'quote' => 'Testimoni manual', 'is_published' => true]);

        $this->assertNull($testimonial->booking_id);
        $this->assertNull($testimonial->booking);
    }
}
