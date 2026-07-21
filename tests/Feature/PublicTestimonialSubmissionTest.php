<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTestimonialSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(string $status = 'completed'): Booking
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);

        return Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Rina Wijaya',
            'customer_email' => 'r@x.id', 'customer_phone' => '0811', 'start_date' => '2026-09-01',
            'end_date' => '2026-09-03', 'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000,
            'status' => $status, 'booking_code' => Booking::generateBookingCode(),
        ]);
    }

    public function test_customer_can_submit_business_testimonial_for_completed_booking(): void
    {
        $booking = $this->makeBooking();

        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", [
            'rating' => 5, 'quote' => 'Prosesnya cepat dan mobilnya bersih!',
        ])->assertRedirect(route('tracking.show', $booking->booking_code));

        $testimonial = Testimonial::where('booking_id', $booking->id)->firstOrFail();
        $this->assertFalse($testimonial->is_published);
        $this->assertSame('Rina Wijaya', $testimonial->name);
        $this->assertSame(5, $testimonial->rating);
    }

    public function test_cannot_submit_twice_for_the_same_booking(): void
    {
        $booking = $this->makeBooking();
        $payload = ['rating' => 4, 'quote' => 'Bagus.'];

        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", $payload);
        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", $payload);

        $this->assertSame(1, Testimonial::where('booking_id', $booking->id)->count());
    }

    public function test_cannot_submit_for_booking_not_completed(): void
    {
        $booking = $this->makeBooking('confirmed');

        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", ['rating' => 5, 'quote' => 'Test']);

        $this->assertSame(0, Testimonial::where('booking_id', $booking->id)->count());
    }

    public function test_quote_is_required(): void
    {
        $booking = $this->makeBooking();

        $this->post("/lacak/{$booking->booking_code}/ulasan-bisnis", ['rating' => 5, 'quote' => ''])
            ->assertSessionHasErrors('quote');

        $this->assertSame(0, Testimonial::where('booking_id', $booking->id)->count());
    }
}
