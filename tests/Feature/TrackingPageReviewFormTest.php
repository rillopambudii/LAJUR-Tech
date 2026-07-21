<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Car;
use App\Models\DriverReview;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingPageReviewFormTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompletedBookingWithDriver(): Booking
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $driver = User::create(['tenant_id' => $tenant->id, 'name' => 'Rahmat', 'email' => 'r-'.uniqid().'@lajur.id', 'password' => 'password', 'role' => User::ROLE_DRIVER, 'is_admin' => false]);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);

        return Booking::create([
            'car_id' => $car->id, 'driver_id' => $driver->id, 'car_name' => $car->name,
            'customer_name' => 'Budi', 'customer_email' => 'c@x.id', 'customer_phone' => '0811',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'days' => 2,
            'price_per_day' => 250000, 'total_price' => 500000, 'status' => 'completed',
            'booking_code' => Booking::generateBookingCode(),
        ]);
    }

    public function test_review_forms_show_for_completed_booking_with_driver(): void
    {
        $booking = $this->makeCompletedBookingWithDriver();

        $this->get("/lacak/{$booking->booking_code}")
            ->assertOk()
            ->assertSee('rating_punctuality', false)
            ->assertSee('ulasan-bisnis', false);
    }

    public function test_driver_review_form_hidden_once_submitted_pending(): void
    {
        $booking = $this->makeCompletedBookingWithDriver();
        DriverReview::create([
            'booking_id' => $booking->id, 'driver_id' => $booking->driver_id,
            'rating_punctuality' => 5, 'rating_cleanliness' => 5, 'rating_friendliness' => 5,
            'rating_safety' => 5, 'rating_overall' => 5.0, 'status' => 'pending',
        ]);

        $this->get("/lacak/{$booking->booking_code}")
            ->assertOk()
            ->assertSee('sedang ditinjau')
            ->assertDontSee('name="rating_punctuality"', false);
    }

    public function test_testimonial_form_hidden_once_submitted(): void
    {
        $booking = $this->makeCompletedBookingWithDriver();
        Testimonial::create(['name' => 'Budi', 'rating' => 5, 'quote' => 'Bagus', 'is_published' => false, 'booking_id' => $booking->id]);

        $this->get("/lacak/{$booking->booking_code}")
            ->assertOk()
            ->assertDontSee('name="quote"', false);
    }

    public function test_review_forms_hidden_when_booking_not_completed(): void
    {
        $tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($tenant);
        $car = Car::create(['name' => 'Xenia', 'brand' => 'Daihatsu', 'type' => 'MPV', 'transmission' => 'Manual', 'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 250000]);
        $booking = Booking::create([
            'car_id' => $car->id, 'car_name' => $car->name, 'customer_name' => 'Budi',
            'customer_email' => 'c@x.id', 'customer_phone' => '0811', 'start_date' => '2026-09-01',
            'end_date' => '2026-09-03', 'days' => 2, 'price_per_day' => 250000, 'total_price' => 500000,
            'status' => 'confirmed', 'booking_code' => Booking::generateBookingCode(),
        ]);

        $this->get("/lacak/{$booking->booking_code}")
            ->assertOk()
            ->assertDontSee('name="quote"', false)
            ->assertDontSee('name="rating_punctuality"', false);
    }
}
